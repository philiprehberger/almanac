<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_injection_signals', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('workspace_id', 26);
            $table->char('query_id', 26)->nullable();
            $table->string('signal_kind', 32);
            $table->jsonb('details');
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('query_id')->references('id')->on('queries')->nullOnDelete();
            $table->index(['workspace_id', 'created_at']);
            $table->index('signal_kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_injection_signals');
    }
};
