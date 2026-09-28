<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9 — Work / Production tracking (quantities, not money). Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('unit_type', 40);
            $table->string('unit_label', 120)->nullable();
            $table->decimal('assigned', 15, 2)->default(0);
            $table->decimal('completed', 15, 2)->default(0);
            $table->decimal('received', 15, 2)->default(0);
            $table->decimal('remaining', 15, 2)->default(0);
            $table->date('recorded_on');
            $table->text('notes')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'worker_id']);
            $table->index(['project_id', 'recorded_on']);
            $table->index(['worker_id', 'recorded_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_records');
    }
};
