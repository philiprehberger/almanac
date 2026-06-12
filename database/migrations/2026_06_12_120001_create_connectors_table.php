<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connectors', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('workspace_id', 26);
            $table->string('kind', 16);
            $table->string('label')->nullable();
            $table->text('oauth_token')->nullable();
            $table->jsonb('config')->default('{}');
            $table->string('status', 24)->default('active');
            $table->timestampTz('backoff_until')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestampTz('paused_at')->nullable();
            $table->timestampTz('last_sync_at')->nullable();
            $table->timestampsTz();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->index(['workspace_id', 'kind']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connectors');
    }
};
