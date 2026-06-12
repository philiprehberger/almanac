<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_mappings', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('workspace_id', 26);
            $table->foreignId('almanac_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('source_kind', 16);
            $table->string('source_principal_id');
            $table->string('source_principal_kind', 24);
            $table->timestampTz('refreshed_at')->nullable();
            $table->timestampsTz();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->index(['workspace_id', 'almanac_user_id']);
            $table->unique(['workspace_id', 'source_kind', 'source_principal_id', 'source_principal_kind'], 'identity_mappings_unique_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_mappings');
    }
};
