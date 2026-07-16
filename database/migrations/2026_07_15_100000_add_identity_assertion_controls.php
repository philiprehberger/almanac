<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            // Only keys with this capability may assert an end-user identity
            // (raw `as_principal` or a mapped `caller_external_id`). Default
            // off: a plain chat key can never impersonate a principal.
            $table->boolean('allow_identity_assertion')->default(false)->after('scope');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            // Allowlist of raw synthetic principals a demo key may assert via
            // `as_principal`. Null/empty means synthetic assertion is refused.
            $table->jsonb('demo_principals')->nullable()->after('allowed_chat_origins');
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn('allow_identity_assertion');
        });
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('demo_principals');
        });
    }
};
