<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: refine apartment_units statuses to PENDING / IN_PROGRESS / COMPLETED / INSPECTED.
 * Company crew (xoman) stays on is_company_crew — not a status value.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('apartment_units')) {
            return;
        }

        // Legacy Phase 1 values → Phase 3 vocabulary
        DB::table('apartment_units')->where('status', 'done')->update(['status' => 'completed']);

        DB::table('apartment_units')
            ->where('status', 'company')
            ->update([
                'status' => 'pending',
                'is_company_crew' => true,
                'assigned_worker_id' => null,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('apartment_units')) {
            return;
        }

        DB::table('apartment_units')->where('status', 'completed')->update(['status' => 'done']);
        DB::table('apartment_units')->where('status', 'inspected')->update(['status' => 'done']);
    }
};
