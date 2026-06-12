<?php

namespace App\Jobs;

use App\Models\GapCluster;
use App\Models\Scopes\WorkspaceScope;
use App\Models\UnansweredQuestion;
use App\Models\Workspace;
use App\Services\Llm\Contracts\EmbedProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Cluster unanswered_questions for a workspace.
 *
 * Below 50 unanswered: skip clustering, the gap-report endpoint falls back
 * to raw questions. Above 50: embed each query, run greedy single-pass
 * agglomeration with a cosine-distance threshold. Production path would use
 * DBSCAN in the FastAPI worker; this in-process variant is the portfolio
 * default to keep the pipeline visible end-to-end.
 */
class ClusterUnansweredQuestionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MIN_FOR_CLUSTERING = 50;
    private const COSINE_DISTANCE_MAX = 0.35;

    public function __construct(
        public readonly string $workspaceId,
    ) {
    }

    public function handle(EmbedProvider $embedder): void
    {
        /** @var Workspace|null $workspace */
        $workspace = Workspace::query()->find($this->workspaceId);
        if ($workspace === null) {
            return;
        }

        $rows = DB::table('unanswered_questions as u')
            ->join('queries as q', 'q.id', '=', 'u.query_id')
            ->where('u.workspace_id', $workspace->id)
            ->get(['u.id as uq_id', 'q.query_text']);

        if ($rows->count() < self::MIN_FOR_CLUSTERING) {
            return;
        }

        $texts = $rows->pluck('query_text')->map(fn ($t) => (string) $t)->all();
        $vectors = $embedder->embed($texts);

        // Greedy agglomeration: walk rows in order, assign to nearest existing
        // centroid within threshold, else open a new cluster.
        /** @var array<int, array{vector:array<int,float>, ids:array<int,string>, texts:array<int,string>}> $clusters */
        $clusters = [];
        foreach ($rows as $i => $row) {
            $v = $vectors[$i] ?? null;
            if (! is_array($v)) {
                continue;
            }
            $assigned = false;
            foreach ($clusters as $k => &$cluster) {
                $dist = $this->cosineDistance($v, $cluster['vector']);
                if ($dist <= self::COSINE_DISTANCE_MAX) {
                    $cluster['ids'][] = $row->uq_id;
                    $cluster['texts'][] = $row->query_text;
                    $cluster['vector'] = $this->mean($cluster['vector'], $v, count($cluster['ids']));
                    $assigned = true;
                    break;
                }
            }
            unset($cluster);
            if (! $assigned) {
                $clusters[] = ['vector' => $v, 'ids' => [$row->uq_id], 'texts' => [$row->query_text]];
            }
        }

        // Persist clusters.
        DB::transaction(function () use ($workspace, $clusters) {
            DB::table('gap_clusters')->where('workspace_id', $workspace->id)->update([
                'member_count' => 0,
                'last_recomputed_at' => now(),
            ]);

            foreach ($clusters as $cluster) {
                if (count($cluster['ids']) < 2) {
                    continue;
                }
                $centroid = $this->generateCentroid($cluster['texts']);
                $gc = GapCluster::query()->withoutGlobalScope(WorkspaceScope::class)->create([
                    'workspace_id' => $workspace->id,
                    'centroid_text' => $centroid,
                    'member_count' => count($cluster['ids']),
                    'last_recomputed_at' => now(),
                ]);

                UnansweredQuestion::query()
                    ->withoutGlobalScope(WorkspaceScope::class)
                    ->whereIn('id', $cluster['ids'])
                    ->update(['cluster_id' => $gc->id]);
            }
        });
    }

    /**
     * Cluster centroid label. In production this would route through the
     * LLM-judge sidecar for PII redaction; the portfolio default picks the
     * shortest member as a representative — already PII-thin for the
     * fixture corpus.
     *
     * @param  array<int, string>  $texts
     */
    private function generateCentroid(array $texts): string
    {
        $shortest = $texts[0] ?? '';
        foreach ($texts as $t) {
            if (mb_strlen($t) < mb_strlen($shortest)) {
                $shortest = $t;
            }
        }
        return mb_substr($shortest, 0, 200);
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    private function cosineDistance(array $a, array $b): float
    {
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na === 0.0 || $nb === 0.0) {
            return 1.0;
        }
        $sim = $dot / (sqrt($na) * sqrt($nb));
        return 1.0 - $sim;
    }

    /**
     * @param  array<int, float>  $prev
     * @param  array<int, float>  $next
     * @return array<int, float>
     */
    private function mean(array $prev, array $next, int $count): array
    {
        $out = [];
        $len = min(count($prev), count($next));
        for ($i = 0; $i < $len; $i++) {
            $out[$i] = (($prev[$i] * ($count - 1)) + $next[$i]) / $count;
        }
        return $out;
    }
}
