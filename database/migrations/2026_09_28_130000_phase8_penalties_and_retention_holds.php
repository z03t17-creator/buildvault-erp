<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8: complete penalty + retention hold columns for payroll wiring.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penalties', function (Blueprint $table) {
            if (! Schema::hasColumn('penalties', 'type')) {
                $table->string('type', 64)->default('other')->after('floor_id');
            }
            if (! Schema::hasColumn('penalties', 'amount_iqd')) {
                $table->decimal('amount_iqd', 18, 2)->nullable()->after('amount_usd');
            }
            if (! Schema::hasColumn('penalties', 'occurred_on')) {
                $table->date('occurred_on')->nullable()->after('amount_iqd');
            }
            if (! Schema::hasColumn('penalties', 'notes')) {
                $table->text('notes')->nullable()->after('reason');
            }
            if (! Schema::hasColumn('penalties', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('retention_holds', function (Blueprint $table) {
            if (! Schema::hasColumn('retention_holds', 'pay_period')) {
                $table->string('pay_period', 7)->nullable()->after('payout_id'); // Y-m
            }
            if (! Schema::hasColumn('retention_holds', 'hold_pct')) {
                $table->decimal('hold_pct', 5, 2)->nullable()->after('pay_period');
            }
            if (! Schema::hasColumn('retention_holds', 'released_amount_usd')) {
                $table->decimal('released_amount_usd', 18, 2)->nullable()->after('released_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('penalties', function (Blueprint $table) {
            if (Schema::hasColumn('penalties', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
            foreach (['type', 'amount_iqd', 'occurred_on', 'notes'] as $col) {
                if (Schema::hasColumn('penalties', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('retention_holds', function (Blueprint $table) {
            foreach (['pay_period', 'hold_pct', 'released_amount_usd'] as $col) {
                if (Schema::hasColumn('retention_holds', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
