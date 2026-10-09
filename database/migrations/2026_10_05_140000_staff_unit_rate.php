<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unit-rate staff (m² / piece / villa…) — rate × quantity payout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (! Schema::hasColumn('staff', 'unit_rate')) {
                $table->decimal('unit_rate', 18, 4)->nullable()->after('currency');
            }
            if (! Schema::hasColumn('staff', 'rate_unit')) {
                $table->string('rate_unit', 32)->nullable()->after('unit_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'rate_unit')) {
                $table->dropColumn('rate_unit');
            }
            if (Schema::hasColumn('staff', 'unit_rate')) {
                $table->dropColumn('unit_rate');
            }
        });
    }
};
