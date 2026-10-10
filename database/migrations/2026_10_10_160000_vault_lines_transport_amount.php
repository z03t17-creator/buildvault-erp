<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily job pay: optional transport add-on recorded on the vault line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vault_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('vault_lines', 'transport_amount')) {
                $table->decimal('transport_amount', 18, 2)->nullable()->after('day_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vault_lines', function (Blueprint $table) {
            if (Schema::hasColumn('vault_lines', 'transport_amount')) {
                $table->dropColumn('transport_amount');
            }
        });
    }
};
