<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('workspace_id', 26);
            $table->char('connector_id', 26);
            $table->string('external_id');
            $table->string('title');
            $table->string('kind', 24);
            $table->text('source_url');
            $table->string('etag')->nullable();
            $table->timestampTz('modified_at')->nullable();
            $table->timestampTz('embedded_at')->nullable();
            $table->string('embed_status', 16)->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampsTz();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('connector_id')->references('id')->on('connectors')->cascadeOnDelete();
            $table->index(['workspace_id', 'deleted_at']);
            $table->index(['connector_id', 'external_id']);
            $table->index('embed_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
