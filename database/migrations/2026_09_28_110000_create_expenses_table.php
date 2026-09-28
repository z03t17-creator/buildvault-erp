<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vault_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 32);
            $table->decimal('amount_iqd', 18, 2);
            $table->decimal('amount_usd', 18, 2)->default(0);
            $table->decimal('exchange_rate', 18, 4)->default(0);
            $table->date('expense_date');
            $table->string('supplier')->nullable();
            $table->string('payment_method', 32)->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('approval_status', 32)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamps();

            $table->index('approval_status');
            $table->index('category');
            $table->index(['project_id', 'approval_status']);
            $table->index('expense_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
