<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->string('currency', 3)->default('IQD')->after('min_quantity');
            $table->decimal('purchase_price_usd', 18, 2)->default(0)->after('currency');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('currency', 3)->default('IQD')->after('supplier_id');
            $table->decimal('purchase_price_usd', 18, 2)->nullable()->after('purchase_price_iqd');
            $table->decimal('total_cost_usd', 18, 2)->nullable()->after('total_cost_iqd');
        });

        // Existing rows are IQD-priced.
        DB::table('stock_items')->update([
            'currency' => 'IQD',
            'purchase_price_usd' => 0,
        ]);
        DB::table('stock_movements')->update([
            'currency' => 'IQD',
            'purchase_price_usd' => 0,
            'total_cost_usd' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['currency', 'purchase_price_usd', 'total_cost_usd']);
        });

        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropColumn(['currency', 'purchase_price_usd']);
        });
    }
};
