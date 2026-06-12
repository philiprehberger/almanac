<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doc_acls', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('workspace_id', 26);
            $table->char('document_id', 26);
            $table->string('principal_kind', 16);
            $table->string('principal_external_id');
            $table->timestampsTz();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->index(['workspace_id', 'document_id']);
            $table->index(['workspace_id', 'principal_kind', 'principal_external_id'], 'doc_acls_principal_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_acls');
    }
};
