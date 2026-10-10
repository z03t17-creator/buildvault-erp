<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_movements', 'payment_source')) {
                $table->string('payment_source', 32)->nullable()->after('notes');
            }
            if (! Schema::hasColumn('stock_movements', 'vault_line_id')) {
                $table->foreignId('vault_line_id')->nullable()->after('payment_source')
                    ->constrained('vault_lines')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'vault_line_id')) {
                $table->dropConstrainedForeignId('vault_line_id');
            }
            if (Schema::hasColumn('stock_movements', 'payment_source')) {
                $table->dropColumn('payment_source');
            }
        });
    }
};
