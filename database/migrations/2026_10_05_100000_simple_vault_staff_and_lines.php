<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Simple vault schema — Staff replaces Workers for attendance / penalties.
 * Books are empty: drop worker_id and attach staff_id with no data backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('kind', 16); // salary | time
            $table->string('trade')->nullable(); // MDF, laminate, or other
            $table->decimal('monthly_salary', 18, 2)->nullable();
            $table->string('currency', 3)->nullable(); // USD | IQD — only for salary kind
            $table->timestamps();
            $table->softDeletes();

            $table->index('kind');
            $table->index('name');
        });

        // Staff rewire does not backfill worker rows — clear legacy attendance/penalty
        // rows so NOT NULL staff_id can be added without wiping projects or vault books.
        if (Schema::hasTable('attendances') && Schema::hasColumn('attendances', 'worker_id')) {
            DB::table('attendances')->delete();
        }
        if (Schema::hasTable('penalties') && Schema::hasColumn('penalties', 'worker_id')) {
            DB::table('penalties')->delete();
        }

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique(['worker_id', 'date']);
            $table->dropConstrainedForeignId('worker_id');
            $table->foreignId('staff_id')->after('id')->constrained('staff')->cascadeOnDelete();
            $table->unique(['staff_id', 'date']);
        });

        Schema::table('penalties', function (Blueprint $table) {
            $table->dropIndex(['worker_id', 'status']);
            $table->dropConstrainedForeignId('worker_id');
            $table->foreignId('staff_id')->after('id')->constrained('staff')->cascadeOnDelete();
            $table->index(['staff_id', 'status']);
        });

        Schema::create('vault_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16); // advance | salary | expense | job_pay
            $table->date('occurred_on');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3); // USD | IQD — never blended
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('note')->nullable();
            $table->string('expense_type')->nullable();
            $table->string('purpose')->nullable();
            $table->decimal('hold_amount', 18, 2)->default(0);
            /** company_insurance (advance) | staff_owed (job_pay) | null */
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

    public function down(): void
    {
        Schema::dropIfExists('vault_lines');

        Schema::table('penalties', function (Blueprint $table) {
            $table->dropIndex(['staff_id', 'status']);
            $table->dropConstrainedForeignId('staff_id');
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->index(['worker_id', 'status']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique(['staff_id', 'date']);
            $table->dropConstrainedForeignId('staff_id');
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->unique(['worker_id', 'date']);
        });

        Schema::dropIfExists('staff');
    }
};
