<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 — Employee advances (سلفە). Additive; safe to merge alongside Phase 6 expenses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_iqd', 15, 2);
            $table->decimal('remaining_iqd', 15, 2);
            $table->date('advanced_on');
            $table->string('reason', 1000);
            $table->string('repayment_method', 40);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['worker_id', 'status']);
            $table->index(['project_id', 'advanced_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_advances');
    }
};
