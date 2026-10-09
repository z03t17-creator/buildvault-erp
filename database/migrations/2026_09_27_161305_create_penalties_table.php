<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('floor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason');
            $table->decimal('amount_usd', 18, 2);
            $table->boolean('deducted_from_payout')->default(false);
            $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('pending'); // pending|applied|waived
            $table->timestamps();

            $table->index('status');
            $table->index(['worker_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalties');
    }
};
