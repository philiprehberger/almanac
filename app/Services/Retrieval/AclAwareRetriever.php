<?php

namespace App\Services\Retrieval;

use App\Models\Workspace;
use App\Services\Llm\Contracts\EmbedProvider;
use Illuminate\Support\Facades\DB;

/**
 * The wedge.
 *
 * 1. Embed query.
 * 2. Estimate ACL selectivity (count of distinct documents the caller can see ÷ total).
 * 3. Over-fetch: k_request = clamp(target_K / max(selectivity, 0.01), min=50, max=500).
 * 4. SET LOCAL hnsw.iterative_scan = strict_order; SET LOCAL hnsw.max_scan_tuples = k_request * 4.
 * 5. Inside the same transaction, run a SQL query that applies the ACL filter
 *    via user_can_read() inside the iterative scan, ordered by distance.
 * 6. If post-filter count < min_results, run a second pass with a hard cap.
 * 7. If still thin, surface acl_thin = true.
 *
 * The per-workspace partial HNSW index is named
 *   doc_chunks_<workspace_id>_hnsw
 * and built by App\Services\Workspaces\HnswIndexManager at workspace-create
 * time.
 */
class AclAwareRetriever
{
    private const TARGET_K_DEFAULT = 8;
    private const MIN_RESULTS_DEFAULT = 3;
    private const K_MIN_OVERFETCH = 50;
    private const K_MAX_OVERFETCH = 500;
    private const K_HARD_CAP = 2000;

    public function __construct(
        private readonly EmbedProvider $embedder,
    ) {
    }

    /**
     * @param  array<int, array{kind:string, id:string}>  $principalSet
     */
    public function retrieve(
        Workspace $workspace,
        string $query,
        array $principalSet,
        int $targetK = self::TARGET_K_DEFAULT,
        int $minResults = self::MIN_RESULTS_DEFAULT,
    ): RetrievalResult {
        $vector = $this->embedder->embed([$query])[0] ?? null;
        if ($vector === null) {
            return new RetrievalResult([], true, 0.0, 0, 0);
        }
        $literal = $this->toVectorLiteral($vector);

        $selectivity = $this->estimateSelectivity($workspace, $principalSet);
        $kRequested = max(
            self::K_MIN_OVERFETCH,
            min(
                self::K_MAX_OVERFETCH,
                (int) ceil($targetK / max($selectivity, 0.01)),
            ),
        );

        $chunks = $this->scanWithIterative($workspace, $literal, $principalSet, $kRequested, $targetK);

        if (count($chunks) < $minResults) {
            // Second pass with hard cap before declaring acl_thin.
            $chunks = $this->scanWithIterative($workspace, $literal, $principalSet, self::K_HARD_CAP, $targetK);
        }

        $thin = count($chunks) < $minResults;

        return new RetrievalResult(
            chunks: $chunks,
            aclThin: $thin,
            estimatedSelectivity: $selectivity,
            kRequested: $kRequested,
            kReturned: count($chunks),
        );
    }

    /**
     * @param  array<int, array{kind:string, id:string}>  $principalSet
     */
    private function estimateSelectivity(Workspace $workspace, array $principalSet): float
    {
        // Count: docs reachable / total docs.
        $totalRow = DB::selectOne(
            'SELECT COUNT(*) AS n FROM documents WHERE workspace_id = ? AND deleted_at IS NULL',
            [$workspace->id]
        );
        $total = (int) ($totalRow->n ?? 0);
        if ($total === 0) {
            return 1.0;
        }

        $principalSetJson = json_encode($principalSet, JSON_UNESCAPED_UNICODE);
        $reachableRow = DB::selectOne(
            'SELECT COUNT(DISTINCT da.document_id) AS n
             FROM doc_acls da
             WHERE da.workspace_id = ?
               AND (
                   da.principal_kind = ?
                   OR da.principal_kind = ?
                   OR EXISTS (
                       SELECT 1
                       FROM jsonb_array_elements(?::jsonb) ps
                       WHERE ps->>\'kind\' = da.principal_kind
                         AND ps->>\'id\'   = da.principal_external_id
                   )
               )',
            [$workspace->id, 'public', 'workspace', $principalSetJson]
        );
        $reachable = (int) ($reachableRow->n ?? 0);
        $sel = $reachable / max($total, 1);
        return max(0.001, min(1.0, $sel));
    }

    /**
     * @return array<int, RetrievedChunk>
     */
    private function scanWithIterative(
        Workspace $workspace,
        string $vectorLiteral,
        array $principalSet,
        int $kRequested,
        int $targetK,
    ): array {
        return DB::transaction(function () use ($workspace, $vectorLiteral, $principalSet, $kRequested, $targetK) {
            // pgvector iterative scan controls; SET LOCAL persists for the
            // transaction only.
            DB::statement("SET LOCAL hnsw.iterative_scan = 'strict_order'");
            DB::statement('SET LOCAL hnsw.max_scan_tuples = '.(int) ($kRequested * 4));

            $principalSetJson = json_encode($principalSet, JSON_UNESCAPED_UNICODE);

            $rows = DB::select(
                'SELECT
                     c.id            AS chunk_id,
                     c.document_id   AS document_id,
                     c.text          AS text,
                     d.title         AS title,
                     d.source_url    AS source_url,
                     d.kind          AS kind,
                     (c.embedding <=> ?::vector) AS distance
                 FROM doc_chunks c
                 JOIN documents d ON d.id = c.document_id
                 WHERE c.workspace_id = ?
                   AND d.deleted_at IS NULL
                   AND user_can_read(?::jsonb, c.document_id)
                 ORDER BY c.embedding <=> ?::vector
                 LIMIT ?',
                [$vectorLiteral, $workspace->id, $principalSetJson, $vectorLiteral, $targetK]
            );

            return array_map(
                fn ($r) => new RetrievedChunk(
                    id: (string) $r->chunk_id,
                    documentId: (string) $r->document_id,
                    text: (string) $r->text,
                    score: 1.0 - (float) $r->distance,
                    sourceUrl: (string) $r->source_url,
                    title: (string) $r->title,
                    kind: (string) $r->kind,
                ),
                $rows
            );
        });
    }

    /**
     * @param  array<int, float>  $vector
     */
    private function toVectorLiteral(array $vector): string
    {
        return '['.implode(',', array_map(fn ($f) => rtrim(rtrim(number_format((float) $f, 6, '.', ''), '0'), '.'), $vector)).']';
    }
}
