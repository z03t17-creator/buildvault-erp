<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent repair for Render/production when simple-vault schema is missing or stuck
 * on worker_id columns (code expects staff + vault_lines).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff')) {
            Schema::create('staff', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('phone')->nullable();
                $table->string('kind', 16);
                $table->string('trade')->nullable();
                $table->decimal('monthly_salary', 18, 2)->nullable();
                $table->string('currency', 3)->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index('kind');
                $table->index('name');
            });
        }

        if (! Schema::hasTable('vault_lines')) {
            Schema::create('vault_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vault_id')->constrained()->cascadeOnDelete();
                $table->string('kind', 16);
                $table->date('occurred_on');
                $table->decimal('amount', 18, 2);
                $table->string('currency', 3);
                $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
                $table->string('note')->nullable();
                $table->string('expense_type')->nullable();
                $table->string('purpose')->nullable();
                $table->decimal('hold_amount', 18, 2)->default(0);
                $table->string('hold_pool', 32)->nullable();
                $table->date('unlock_date')->nullable();
                $table->timestamp('hold_released_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['kind', 'occurred_on']);
                $table->index(['currency', 'kind']);
                $table->index(['unlock_date', 'hold_pool']);
                $table->index(['staff_id', 'kind']);
            });
        }

        if (
            Schema::hasTable('attendances')
            && Schema::hasColumn('attendances', 'worker_id')
            && ! Schema::hasColumn('attendances', 'staff_id')
        ) {
            DB::table('attendances')->delete();
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropUnique(['worker_id', 'date']);
                $table->dropConstrainedForeignId('worker_id');
                $table->foreignId('staff_id')->after('id')->constrained('staff')->cascadeOnDelete();
                $table->unique(['staff_id', 'date']);
            });
        }

        if (
            Schema::hasTable('penalties')
            && Schema::hasColumn('penalties', 'worker_id')
            && ! Schema::hasColumn('penalties', 'staff_id')
        ) {
            DB::table('penalties')->delete();
            Schema::table('penalties', function (Blueprint $table) {
                $table->dropIndex(['worker_id', 'status']);
                $table->dropConstrainedForeignId('worker_id');
                $table->foreignId('staff_id')->after('id')->constrained('staff')->cascadeOnDelete();
                $table->index(['staff_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        // Repair migration — no down (forward-only on production).
    }
};
