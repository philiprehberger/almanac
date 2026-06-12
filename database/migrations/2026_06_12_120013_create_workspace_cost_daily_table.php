<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_cost_daily', function (Blueprint $table) {
            $table->char('workspace_id', 26);
            $table->date('day');
            $table->decimal('cost_usd', 12, 6)->default(0);
            $table->unsignedInteger('query_count')->default(0);
            $table->unsignedBigInteger('tokens_in')->default(0);
            $table->unsignedBigInteger('tokens_out')->default(0);
            $table->timestampsTz();

            $table->primary(['workspace_id', 'day']);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_cost_daily');
    }
};
