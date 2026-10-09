<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff pay redesign: pay_model, role, day_rate, staff_rates,
 * vault_line location + vault_line_items for unit pay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (! Schema::hasColumn('staff', 'pay_model')) {
                $table->string('pay_model', 16)->nullable()->after('kind');
            }
            if (! Schema::hasColumn('staff', 'role')) {
                $table->string('role', 120)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('staff', 'day_rate')) {
                $table->decimal('day_rate', 18, 2)->nullable()->after('monthly_salary');
            }
        });

        if (Schema::hasColumn('staff', 'pay_model')) {
            DB::table('staff')->where('kind', 'salary')->whereNull('pay_model')->update(['pay_model' => 'monthly']);
            DB::table('staff')->where('kind', 'time')->whereNull('pay_model')->update(['pay_model' => 'daily']);
            DB::table('staff')->where('kind', 'unit')->whereNull('pay_model')->update(['pay_model' => 'unit']);
            DB::table('staff')->whereNull('pay_model')->update(['pay_model' => 'daily']);
        }

        if (Schema::hasColumn('staff', 'role') && Schema::hasColumn('staff', 'trade')) {
            DB::table('staff')->whereNull('role')->whereNotNull('trade')->update([
                'role' => DB::raw('trade'),
            ]);
        }

        // Seed staff_rates from legacy single unit_rate rows.
        if (! Schema::hasTable('staff_rates')) {
            Schema::create('staff_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
                $table->string('item_name', 160);
                $table->string('unit', 32);
                $table->decimal('rate', 18, 4);
                $table->string('currency', 3);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['staff_id', 'sort_order']);
            });
        }

        if (Schema::hasTable('staff_rates') && Schema::hasColumn('staff', 'unit_rate')) {
            $unitStaff = DB::table('staff')
                ->where(function ($q) {
                    $q->where('pay_model', 'unit')->orWhere('kind', 'unit');
                })
                ->whereNotNull('unit_rate')
                ->where('unit_rate', '>', 0)
                ->get(['id', 'unit_rate', 'rate_unit', 'currency']);

            foreach ($unitStaff as $row) {
                $exists = DB::table('staff_rates')->where('staff_id', $row->id)->exists();
                if ($exists) {
                    continue;
                }
                DB::table('staff_rates')->insert([
                    'staff_id' => $row->id,
                    'item_name' => 'کار',
                    'unit' => $row->rate_unit ?: 'دانە',
                    'rate' => $row->unit_rate,
                    'currency' => $row->currency ?: 'IQD',
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('vault_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('vault_lines', 'site_kind')) {
                $table->string('site_kind', 16)->nullable()->after('purpose');
            }
            if (! Schema::hasColumn('vault_lines', 'block')) {
                $table->string('block', 64)->nullable()->after('site_kind');
            }
            if (! Schema::hasColumn('vault_lines', 'zone')) {
                $table->string('zone', 64)->nullable()->after('block');
            }
            if (! Schema::hasColumn('vault_lines', 'floor')) {
                $table->string('floor', 64)->nullable()->after('zone');
            }
            if (! Schema::hasColumn('vault_lines', 'apartment_number')) {
                $table->string('apartment_number', 64)->nullable()->after('floor');
            }
            if (! Schema::hasColumn('vault_lines', 'apartment_model')) {
                $table->string('apartment_model', 64)->nullable()->after('apartment_number');
            }
            if (! Schema::hasColumn('vault_lines', 'villa_number')) {
                $table->string('villa_number', 64)->nullable()->after('apartment_model');
            }
            if (! Schema::hasColumn('vault_lines', 'area')) {
                $table->string('area', 64)->nullable()->after('villa_number');
            }
            if (! Schema::hasColumn('vault_lines', 'days_count')) {
                $table->decimal('days_count', 10, 2)->nullable()->after('area');
            }
            if (! Schema::hasColumn('vault_lines', 'day_rate')) {
                $table->decimal('day_rate', 18, 2)->nullable()->after('days_count');
            }
        });

        if (! Schema::hasTable('vault_line_items')) {
            Schema::create('vault_line_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vault_line_id')->constrained('vault_lines')->cascadeOnDelete();
                $table->foreignId('staff_rate_id')->nullable()->constrained('staff_rates')->nullOnDelete();
                $table->string('item_name', 160);
                $table->string('unit', 32);
                $table->decimal('quantity', 18, 4);
                $table->decimal('unit_rate', 18, 4);
                $table->decimal('subtotal', 18, 2);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['vault_line_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_line_items');

        Schema::table('vault_lines', function (Blueprint $table) {
            foreach ([
                'site_kind', 'block', 'zone', 'floor', 'apartment_number',
                'apartment_model', 'villa_number', 'area', 'days_count', 'day_rate',
            ] as $col) {
                if (Schema::hasColumn('vault_lines', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('staff_rates');

        Schema::table('staff', function (Blueprint $table) {
            foreach (['pay_model', 'role', 'day_rate'] as $col) {
                if (Schema::hasColumn('staff', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
