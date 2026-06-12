<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unanswered_questions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('workspace_id', 26);
            $table->char('query_id', 26);
            $table->string('reason', 32);
            $table->char('cluster_id', 26)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('query_id')->references('id')->on('queries')->cascadeOnDelete();
            $table->index(['workspace_id', 'created_at']);
            $table->index('cluster_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unanswered_questions');
    }
};
