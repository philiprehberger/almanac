<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gap_clusters', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('workspace_id', 26);
            $table->text('centroid_text');
            $table->unsignedInteger('member_count')->default(0);
            $table->timestampTz('addressed_at')->nullable();
            $table->timestampTz('last_recomputed_at')->nullable();
            $table->timestampsTz();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->index(['workspace_id', 'addressed_at', 'member_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gap_clusters');
    }
};
