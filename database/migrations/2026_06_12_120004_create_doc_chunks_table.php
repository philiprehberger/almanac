<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doc_chunks', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('workspace_id', 26);
            $table->char('document_id', 26);
            $table->unsignedInteger('seq');
            $table->text('text');
            $table->unsignedInteger('token_count');
            $table->string('chunker_version', 24);
            $table->string('embedder_version', 64);
            $table->timestampsTz();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->index(['document_id', 'seq']);
        });

        DB::statement('ALTER TABLE doc_chunks ADD COLUMN embedding vector(1536)');
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_chunks');
    }
};
