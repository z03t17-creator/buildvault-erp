<?php

use App\Support\Roles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2: rename Spatie roles in place (keep IDs / model_has_roles).
 * Idempotent — safe on fresh DBs that already have the new names.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $this->renameRole(Roles::LEGACY_SITE_ENGINEER, Roles::BOSS_CONTRACTOR);
        $this->renameRole(Roles::LEGACY_WORKER, Roles::STOCK_MANAGER);
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $this->renameRole(Roles::BOSS_CONTRACTOR, Roles::LEGACY_SITE_ENGINEER);
        $this->renameRole(Roles::STOCK_MANAGER, Roles::LEGACY_WORKER);
    }

    private function renameRole(string $from, string $to): void
    {
        $legacy = DB::table('roles')->where('name', $from)->where('guard_name', 'web')->first();
        if (! $legacy) {
            return;
        }

        $existing = DB::table('roles')->where('name', $to)->where('guard_name', 'web')->first();
        if ($existing && (int) $existing->id !== (int) $legacy->id) {
            DB::table('model_has_roles')
                ->where('role_id', $legacy->id)
                ->update(['role_id' => $existing->id]);
            DB::table('role_has_permissions')->where('role_id', $legacy->id)->delete();
            DB::table('roles')->where('id', $legacy->id)->delete();

            return;
        }

        DB::table('roles')->where('id', $legacy->id)->update([
            'name' => $to,
            'updated_at' => now(),
        ]);
    }
};
