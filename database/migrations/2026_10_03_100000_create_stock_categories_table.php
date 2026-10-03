<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('name');
            $table->index('name');
        });

        Schema::table('stock_items', function (Blueprint $table) {
            $table->foreignId('stock_category_id')
                ->nullable()
                ->after('category')
                ->constrained('stock_categories')
                ->nullOnDelete();
        });

        // Backfill categories from legacy free-text `category` column.
        $names = DB::table('stock_items')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $now = now();
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $id = DB::table('stock_categories')->insertGetId([
                'name' => mb_substr($name, 0, 64),
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('stock_items')
                ->where('category', $name)
                ->update(['stock_category_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_category_id');
        });

        Schema::dropIfExists('stock_categories');
    }
};
