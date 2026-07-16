<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Self-heal any duplicate (connector_id, external_id) rows a prior race
        // may have created — keep the earliest ULID, drop the rest with their
        // chunks and ACLs — before the unique index goes on.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                WITH dupes AS (
                    SELECT id FROM (
                        SELECT id, row_number() OVER (
                            PARTITION BY connector_id, external_id ORDER BY id
                        ) AS rn
                        FROM documents
                    ) ranked WHERE rn > 1
                )
                DELETE FROM doc_chunks WHERE document_id IN (SELECT id FROM dupes);
            SQL);
            DB::statement(<<<'SQL'
                WITH dupes AS (
                    SELECT id FROM (
                        SELECT id, row_number() OVER (
                            PARTITION BY connector_id, external_id ORDER BY id
                        ) AS rn
                        FROM documents
                    ) ranked WHERE rn > 1
                )
                DELETE FROM doc_acls WHERE document_id IN (SELECT id FROM dupes);
            SQL);
            DB::statement(<<<'SQL'
                DELETE FROM documents WHERE id IN (
                    SELECT id FROM (
                        SELECT id, row_number() OVER (
                            PARTITION BY connector_id, external_id ORDER BY id
                        ) AS rn
                        FROM documents
                    ) ranked WHERE rn > 1
                );
            SQL);
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['connector_id', 'external_id']);
        });
        Schema::table('documents', function (Blueprint $table) {
            $table->unique(['connector_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropUnique(['connector_id', 'external_id']);
        });
        Schema::table('documents', function (Blueprint $table) {
            $table->index(['connector_id', 'external_id']);
        });
    }
};
