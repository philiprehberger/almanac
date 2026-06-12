<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingest_runs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('workspace_id', 26);
            $table->char('connector_id', 26);
            $table->string('mode', 16)->default('incremental');
            $table->string('status', 16)->default('running');
            $table->unsignedInteger('docs_added')->default(0);
            $table->unsignedInteger('docs_updated')->default(0);
            $table->unsignedInteger('docs_removed')->default(0);
            $table->unsignedInteger('docs_failed')->default(0);
            $table->text('last_error')->nullable();
            $table->timestampTz('started_at')->useCurrent();
            $table->timestampTz('completed_at')->nullable();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('connector_id')->references('id')->on('connectors')->cascadeOnDelete();
            $table->index(['workspace_id', 'connector_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingest_runs');
    }
};
