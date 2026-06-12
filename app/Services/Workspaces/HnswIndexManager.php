<?php

namespace App\Services\Workspaces;

use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class HnswIndexManager
{
    /**
     * Per-workspace partial HNSW index on doc_chunks(embedding).
     * Idempotent.
     */
    public function ensure(Workspace $workspace): void
    {
        $name = $this->indexName($workspace);
        // index name must be unquoted-safe, derived from ULID (alphanumeric)
        DB::statement(<<<SQL
            CREATE INDEX IF NOT EXISTS {$name}
            ON doc_chunks USING hnsw (embedding vector_cosine_ops)
            WHERE workspace_id = '{$workspace->id}'
        SQL);
    }

    public function drop(Workspace $workspace): void
    {
        $name = $this->indexName($workspace);
        DB::statement("DROP INDEX IF EXISTS {$name}");
    }

    private function indexName(Workspace $workspace): string
    {
        return 'doc_chunks_w_'.strtolower($workspace->id).'_hnsw';
    }
}
