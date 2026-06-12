<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queries', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('workspace_id', 26);
            $table->char('conversation_id', 26)->nullable();
            $table->text('query_text');
            $table->jsonb('principal_set');
            $table->jsonb('retrieved_chunk_ids');
            $table->string('model')->nullable();
            $table->text('answer_text')->nullable();
            $table->jsonb('citations')->nullable();
            $table->string('confidence', 8)->nullable();
            $table->string('confidence_reason', 32)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->decimal('cost_usd', 12, 6)->default(0);
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('conversation_id')->references('id')->on('conversations')->nullOnDelete();
            $table->index(['workspace_id', 'created_at']);
            $table->index(['workspace_id', 'conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queries');
    }
};
