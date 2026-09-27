<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Running pool balances per project (USD) after deposit splits.
     * Defaults align with locked 45/30/10/5/10 product rules (pct on Project;
     * amounts filled by VaultService in Phase 3.2).
     */
    public function up(): void
    {
        Schema::create('project_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->decimal('expenses_pool_usd', 18, 2)->default(0);
            $table->decimal('payroll_pool_usd', 18, 2)->default(0);
            $table->decimal('retention_pool_usd', 18, 2)->default(0); // shared 10% insurance
            $table->decimal('penalty_pool_usd', 18, 2)->default(0);
            $table->decimal('profit_pool_usd', 18, 2)->default(0);
            $table->timestamps();

            $table->unique('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_allocations');
    }
};
