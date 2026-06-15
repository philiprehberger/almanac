<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION user_can_read(
                principal_set jsonb,
                target_doc_id char(26)
            ) RETURNS boolean
            LANGUAGE plpgsql STABLE PARALLEL SAFE AS $$
            DECLARE
                hit boolean;
            BEGIN
                SELECT EXISTS (
                    SELECT 1
                    FROM doc_acls da
                    WHERE da.document_id = target_doc_id
                      AND (
                        da.principal_kind = 'public'
                        OR da.principal_kind = 'workspace'
                        OR EXISTS (
                            SELECT 1
                            FROM jsonb_array_elements(principal_set) AS ps
                            WHERE ps->>'kind' = da.principal_kind
                              AND ps->>'id'   = da.principal_external_id
                        )
                      )
                ) INTO hit;
                RETURN hit;
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP FUNCTION IF EXISTS user_can_read(jsonb, char)');
    }
};
