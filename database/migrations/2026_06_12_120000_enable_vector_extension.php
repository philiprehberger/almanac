<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        $row = DB::selectOne("SELECT extversion FROM pg_extension WHERE extname = 'vector'");
        $version = $row?->extversion ?? '0';

        if (version_compare($version, '0.8.0', '<')) {
            throw new \RuntimeException(
                "pgvector >= 0.8.0 required for hnsw.iterative_scan; got {$version}. ".
                'Install from source per infra/server-setup.md.'
            );
        }
    }

    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS vector');
    }
};
