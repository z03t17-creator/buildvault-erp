<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vault;
use App\Support\AuditActions;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\InsuranceSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\VaultSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Phase 5 — wipe business data while preserving roles + core @zhako.test users.
 */
class BusinessDataWipeService
{
    /** @var list<string> */
    public const CORE_EMAILS = [
        UserSeeder::ADMIN_EMAIL,
        DemoUsersSeeder::BOSS_EMAIL,
        DemoUsersSeeder::ACCOUNTANT_EMAIL,
        DemoUsersSeeder::STOCK_EMAIL,
    ];

    /** Business tables wiped in FK-safe order (children first). */
    /** @var list<string> */
    public const BUSINESS_TABLES = [
        'apartment_units',
        'building_blocks',
        'staff_statements',
        'client_retention_holds',
        'client_advances',
        'attendances',
        'import_details',
        'imports',
        'production_records',
        'stock_movements',
        'stock_items',
        'stock_categories',
        'suppliers',
        'employee_advances',
        'penalties',
        'retention_holds',
        'payouts',
        'expenses',
        'project_receipts',
        'project_allocations',
        'monthly_settlements',
        'documents',
        'transactions',
        'floors',
        'towers',
        'workers',
        'projects',
        'backups',
        'activity_log',
    ];

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{wiped_tables: array<string, int>, kept_users: list<string>, vault_reset: bool}
     */
    public function wipe(bool $dryRun = false, ?User $actor = null): array
    {
        $counts = [];
        foreach (self::BUSINESS_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $counts[$table] = (int) DB::table($table)->count();
        }

        $kept = User::query()
            ->whereIn('email', self::CORE_EMAILS)
            ->pluck('email')
            ->all();

        if ($dryRun) {
            return [
                'wiped_tables' => $counts,
                'kept_users' => $kept,
                'vault_reset' => Schema::hasTable('vaults'),
                'dry_run' => true,
            ];
        }

        return DB::transaction(function () use ($counts, $actor) {
            $this->disableFkChecks();

            try {
                foreach (self::BUSINESS_TABLES as $table) {
                    if (! Schema::hasTable($table)) {
                        continue;
                    }
                    DB::table($table)->delete();
                }

                // Reset vault balances; keep vault shell row.
                if (Schema::hasTable('vaults')) {
                    Vault::query()->update([
                        'balance_usd' => 0,
                        'balance_iqd' => 0,
                    ]);
                }

                // Remove non-core users (demo extras / imports).
                if (Schema::hasTable('users')) {
                    User::query()
                        ->whereNotIn('email', self::CORE_EMAILS)
                        ->each(function (User $user) {
                            $user->syncRoles([]);
                            $user->delete();
                        });
                }
            } finally {
                $this->enableFkChecks();
            }

            // Re-seed core roles/users/vault/settings if anything missing.
            (new RoleSeeder)->run();
            (new UserSeeder)->run();
            (new DemoUsersSeeder)->run();
            (new VaultSeeder)->run();
            (new InsuranceSettingsSeeder)->run();

            $kept = User::query()
                ->whereIn('email', self::CORE_EMAILS)
                ->pluck('email')
                ->all();

            if (count($kept) < count(self::CORE_EMAILS)) {
                throw new InvalidArgumentException(
                    'Wipe aborted safety check: missing core users after re-seed.'
                );
            }

            $this->audit->log(
                AuditActions::VAULT_SOFT_DELETE_REBUILD,
                'Business data wiped; core users/roles retained',
                null,
                [
                    'wiped_tables' => $counts,
                    'kept_users' => $kept,
                    'actor_id' => $actor?->id,
                ],
            );

            return [
                'wiped_tables' => $counts,
                'kept_users' => $kept,
                'vault_reset' => true,
                'dry_run' => false,
            ];
        });
    }

    protected function disableFkChecks(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }
    }

    protected function enableFkChecks(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
