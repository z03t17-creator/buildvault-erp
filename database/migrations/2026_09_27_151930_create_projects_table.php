<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('location')->nullable();
            $table->decimal('total_budget_usd', 18, 2)->default(0);

            // Pool allocation defaults (blueprint split)
            $table->decimal('allocation_expenses_pct', 5, 2)->default(45.00);
            $table->decimal('allocation_payroll_pct', 5, 2)->default(30.00);
            $table->decimal('allocation_insurance_pct', 5, 2)->default(10.00);
            $table->decimal('allocation_penalty_pct', 5, 2)->default(5.00);
            $table->decimal('allocation_profit_pct', 5, 2)->default(10.00);

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('planning');
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
