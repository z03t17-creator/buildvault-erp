<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Warehouse desk: barcode/SKU helpers, shelf on receive, villa/building dispatch place, staff receiver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_items', 'barcode')) {
                $table->string('barcode', 64)->nullable()->after('sku');
                $table->unique('barcode');
            }
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_movements', 'shelf_zone')) {
                $table->string('shelf_zone', 120)->nullable()->after('invoice_ref');
            }
            if (! Schema::hasColumn('stock_movements', 'staff_id')) {
                $table->foreignId('staff_id')->nullable()->after('receiver')->constrained('staff')->nullOnDelete();
            }
            if (! Schema::hasColumn('stock_movements', 'site_kind')) {
                $table->string('site_kind', 16)->nullable()->after('floor_id');
            }
            if (! Schema::hasColumn('stock_movements', 'block')) {
                $table->string('block', 64)->nullable()->after('site_kind');
            }
            if (! Schema::hasColumn('stock_movements', 'zone')) {
                $table->string('zone', 64)->nullable()->after('block');
            }
            if (! Schema::hasColumn('stock_movements', 'floor_label')) {
                $table->string('floor_label', 64)->nullable()->after('zone');
            }
            if (! Schema::hasColumn('stock_movements', 'apartment_number')) {
                $table->string('apartment_number', 64)->nullable()->after('floor_label');
            }
            if (! Schema::hasColumn('stock_movements', 'villa_number')) {
                $table->string('villa_number', 64)->nullable()->after('apartment_number');
            }
            if (! Schema::hasColumn('stock_movements', 'total_cost_iqd')) {
                $table->decimal('total_cost_iqd', 18, 2)->nullable()->after('purchase_price_iqd');
            }
        });

        // Copy SKU into barcode when barcode is empty so search works immediately.
        if (Schema::hasColumn('stock_items', 'barcode')) {
            DB::table('stock_items')
                ->whereNull('barcode')
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->orderBy('id')
                ->chunkById(100, function ($rows): void {
                    foreach ($rows as $row) {
                        $taken = DB::table('stock_items')->where('barcode', $row->sku)->exists();
                        if ($taken) {
                            continue;
                        }
                        DB::table('stock_items')->where('id', $row->id)->update(['barcode' => $row->sku]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'staff_id')) {
                $table->dropConstrainedForeignId('staff_id');
            }
            foreach ([
                'shelf_zone',
                'site_kind',
                'block',
                'zone',
                'floor_label',
                'apartment_number',
                'villa_number',
                'total_cost_iqd',
            ] as $column) {
                if (Schema::hasColumn('stock_movements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('stock_items', function (Blueprint $table) {
            if (Schema::hasColumn('stock_items', 'barcode')) {
                $table->dropUnique(['barcode']);
                $table->dropColumn('barcode');
            }
        });
    }
};
