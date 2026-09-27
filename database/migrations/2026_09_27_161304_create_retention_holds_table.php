<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shared 10% insurance reserve holds — matured and returned to staff after 6 months.
     */
    public function up(): void
    {
        Schema::create('retention_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount_usd', 18, 2);
            $table->date('hold_start');
            $table->date('maturity_date'); // hold_start + 6 months
            $table->string('status', 32)->default('holding'); // holding|matured|released
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('maturity_date');
            $table->index(['worker_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_holds');
    }
};
