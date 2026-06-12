<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('query_id', 26);
            $table->char('workspace_id', 26);
            $table->string('verdict', 8);
            $table->text('comment')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('query_id')->references('id')->on('queries')->cascadeOnDelete();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->index(['workspace_id', 'verdict']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
