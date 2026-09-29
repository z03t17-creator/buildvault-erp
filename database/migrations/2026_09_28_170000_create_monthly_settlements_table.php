<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13 — audit snapshots of monthly financial settlement (IQD).
 *
 * project_scope_key mirrors project_id (0 = all-projects / null) so the
 * unique index remains enforceable when project_id is NULL (SQL NULLs are
 * distinct in UNIQUE indexes on both MySQL/MariaDB and SQLite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_id')->constrained()->cascadeOnDelete();
            $table->string('year_month', 7); // YYYY-MM
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('project_scope_key')->default(0);
            $table->decimal('money_received_iqd', 18, 2)->default(0);
            $table->decimal('available_vault_balance_iqd', 18, 2)->default(0);
            $table->decimal('project_expenses_iqd', 18, 2)->default(0);
            $table->decimal('payroll_iqd', 18, 2)->default(0);
            $table->decimal('employee_advances_iqd', 18, 2)->default(0);
            $table->decimal('insurance_iqd', 18, 2)->default(0);
            $table->decimal('penalties_iqd', 18, 2)->default(0);
            $table->decimal('other_expenses_iqd', 18, 2)->default(0);
            $table->decimal('approved_payments_iqd', 18, 2)->default(0);
            $table->decimal('available_money_for_payment_iqd', 18, 2)->default(0);
            $table->decimal('current_vault_iqd', 18, 2)->default(0);
            $table->decimal('pending_commitments_iqd', 18, 2)->default(0);
            $table->decimal('reserved_insurance_iqd', 18, 2)->default(0);
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['vault_id', 'year_month', 'project_scope_key'],
                'monthly_settlements_vault_month_scope_uq'
            );
            $table->index(['year_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_settlements');
    }
};
