<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — Accountant ability-to-pay hold + dual currency markers on money rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'currency')) {
                $table->string('currency', 3)->default('IQD')->after('exchange_rate');
            }
            if (! Schema::hasColumn('expenses', 'pay_ability_notes')) {
                $table->text('pay_ability_notes')->nullable()->after('approval_status');
            }
            if (! Schema::hasColumn('expenses', 'held_at')) {
                $table->timestamp('held_at')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('expenses', 'held_by')) {
                $table->foreignId('held_by')->nullable()->after('held_at')
                    ->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('payouts', function (Blueprint $table) {
            if (! Schema::hasColumn('payouts', 'currency')) {
                $table->string('currency', 3)->default('USD')->after('exchange_rate');
            }
            if (! Schema::hasColumn('payouts', 'pay_ability_notes')) {
                $table->text('pay_ability_notes')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('payouts', 'held_at')) {
                $table->timestamp('held_at')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('payouts', 'held_by')) {
                $table->foreignId('held_by')->nullable()->after('held_at')
                    ->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'direction')) {
                $table->string('direction', 16)->nullable()->after('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'held_by')) {
                $table->dropConstrainedForeignId('held_by');
            }
            foreach (['currency', 'pay_ability_notes', 'held_at'] as $col) {
                if (Schema::hasColumn('expenses', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('payouts', function (Blueprint $table) {
            if (Schema::hasColumn('payouts', 'held_by')) {
                $table->dropConstrainedForeignId('held_by');
            }
            foreach (['currency', 'pay_ability_notes', 'held_at'] as $col) {
                if (Schema::hasColumn('payouts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'direction')) {
                $table->dropColumn('direction');
            }
        });
    }
};
