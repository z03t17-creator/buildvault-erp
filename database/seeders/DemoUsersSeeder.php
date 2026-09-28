<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Worker;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Extra RBAC demo logins for local/agent testing only.
 * Passwords are intentionally weak — never use in production.
 */
class DemoUsersSeeder extends Seeder
{
    public const ACCOUNTANT_EMAIL = 'accountant@zhako.test';

    public const ACCOUNTANT_PASSWORD = 'password';

    public const BOSS_EMAIL = 'boss@zhako.test';

    public const BOSS_PASSWORD = 'password';

    public const STOCK_EMAIL = 'stock@zhako.test';

    public const STOCK_PASSWORD = 'password';

    /** @deprecated Phase 2 — migrated to BOSS_EMAIL */
    public const ENGINEER_EMAIL = 'engineer@zhako.test';

    /** @deprecated Phase 2 — replaced by STOCK_EMAIL */
    public const WORKER_EMAIL = 'worker@zhako.test';

    public function run(): void
    {
        $accountant = User::query()->firstOrCreate(
            ['email' => self::ACCOUNTANT_EMAIL],
            [
                'name' => 'Demo Accountant',
                'password' => Hash::make(self::ACCOUNTANT_PASSWORD),
                'email_verified_at' => now(),
                'locale' => 'en',
            ],
        );
        $accountant->syncRoles([Roles::ACCOUNTANT]);

        $boss = $this->ensureBossUser();
        $boss->syncRoles([Roles::BOSS_CONTRACTOR]);

        $stock = User::query()->firstOrCreate(
            ['email' => self::STOCK_EMAIL],
            [
                'name' => 'Demo Stock Manager',
                'password' => Hash::make(self::STOCK_PASSWORD),
                'email_verified_at' => now(),
                'locale' => 'en',
            ],
        );
        $stock->syncRoles([Roles::STOCK_MANAGER]);
        // Stock Manager is not a Worker login — never grant roster privileges via user_id link.
        Worker::query()->where('user_id', $stock->id)->update(['user_id' => null]);

        $this->retireLegacyDemoUsers();
    }

    /**
     * Prefer renaming engineer@ → boss@ so existing DBs keep the same user id.
     */
    private function ensureBossUser(): User
    {
        $boss = User::query()->where('email', self::BOSS_EMAIL)->first();
        if ($boss) {
            $boss->forceFill([
                'name' => 'Demo Boss / Contractor',
                'password' => Hash::make(self::BOSS_PASSWORD),
                'email_verified_at' => $boss->email_verified_at ?? now(),
            ])->save();

            return $boss;
        }

        $engineer = User::query()->where('email', self::ENGINEER_EMAIL)->first();
        if ($engineer) {
            $engineer->forceFill([
                'email' => self::BOSS_EMAIL,
                'name' => 'Demo Boss / Contractor',
                'password' => Hash::make(self::BOSS_PASSWORD),
                'email_verified_at' => $engineer->email_verified_at ?? now(),
            ])->save();

            return $engineer->refresh();
        }

        return User::query()->create([
            'email' => self::BOSS_EMAIL,
            'name' => 'Demo Boss / Contractor',
            'password' => Hash::make(self::BOSS_PASSWORD),
            'email_verified_at' => now(),
            'locale' => 'en',
        ]);
    }

    /**
     * Remove legacy seed logins; unlink any Worker row tied to worker@.
     */
    private function retireLegacyDemoUsers(): void
    {
        $legacyWorker = User::query()->where('email', self::WORKER_EMAIL)->first();
        if ($legacyWorker) {
            Worker::query()->where('user_id', $legacyWorker->id)->update(['user_id' => null]);
            $legacyWorker->syncRoles([]);
            $legacyWorker->delete();
        }

        // engineer@ already migrated in ensureBossUser; delete stray leftover if any
        User::query()->where('email', self::ENGINEER_EMAIL)->delete();
    }
}
