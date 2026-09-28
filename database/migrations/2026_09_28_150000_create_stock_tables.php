<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku', 64)->nullable();
            $table->string('category', 64)->nullable();
            $table->string('unit', 32)->default('pcs');
            $table->decimal('quantity', 18, 3)->default(0);
            $table->decimal('min_quantity', 18, 3)->default(0);
            $table->decimal('purchase_price_iqd', 18, 2)->default(0);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('sku');
            $table->index('category');
            $table->index('name');
            $table->index('quantity');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('type', 8); // in | out
            $table->foreignId('stock_item_id')->constrained('stock_items')->cascadeOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->date('moved_on');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->decimal('purchase_price_iqd', 18, 2)->nullable();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('tower_id')->nullable()->constrained('towers')->nullOnDelete();
            $table->foreignId('floor_id')->nullable()->constrained('floors')->nullOnDelete();
            $table->string('invoice_ref')->nullable();
            $table->string('receiver')->nullable();
            $table->string('issuer')->nullable();
            $table->string('purpose')->nullable();
            $table->string('reference')->nullable();
            $table->decimal('previous_qty', 18, 3);
            $table->decimal('new_qty', 18, 3);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['type', 'moved_on']);
            $table->index('moved_on');
            $table->index(['project_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_items');
        Schema::dropIfExists('suppliers');
    }
};
