<?php

namespace App\Services\Workspaces;

use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class HnswIndexManager
{
    /**
     * Per-workspace partial HNSW index on doc_chunks(embedding). Idempotent.
     *
     * Built CONCURRENTLY so it never takes an ACCESS EXCLUSIVE lock on
     * doc_chunks — a plain CREATE INDEX would block every other workspace's
     * reads and writes for the duration of the build. CONCURRENTLY must run
     * outside a transaction, so never call this inside DB::transaction().
     */
    public function ensure(Workspace $workspace): void
    {
        $name = $this->indexName($workspace);
        // index name must be unquoted-safe, derived from ULID (alphanumeric)
        DB::statement(<<<SQL
            CREATE INDEX CONCURRENTLY IF NOT EXISTS {$name}
            ON doc_chunks USING hnsw (embedding vector_cosine_ops)
            WHERE workspace_id = '{$workspace->id}'
        SQL);
    }

    /**
     * Rebuild the index in place without blocking readers/writers. Use for
     * bloat or after a bulk re-embed; the HNSW index otherwise tracks row
     * changes incrementally and does not need rebuilding per reindex.
     */
    public function rebuild(Workspace $workspace): void
    {
        $this->ensure($workspace);
        $name = $this->indexName($workspace);
        DB::statement("REINDEX INDEX CONCURRENTLY {$name}");
    }

    public function drop(Workspace $workspace): void
    {
        $name = $this->indexName($workspace);
        DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$name}");
    }

    private function indexName(Workspace $workspace): string
    {
        return 'doc_chunks_w_'.strtolower($workspace->id).'_hnsw';
    }
}
