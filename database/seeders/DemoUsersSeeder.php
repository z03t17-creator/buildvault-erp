<?php

namespace Database\Seeders;

use App\Models\Project;
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

    public const ENGINEER_EMAIL = 'engineer@zhako.test';

    public const ENGINEER_PASSWORD = 'password';

    public const WORKER_EMAIL = 'worker@zhako.test';

    public const WORKER_PASSWORD = 'password';

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
        if (! $accountant->hasRole(Roles::ACCOUNTANT)) {
            $accountant->assignRole(Roles::ACCOUNTANT);
        }

        $engineer = User::query()->firstOrCreate(
            ['email' => self::ENGINEER_EMAIL],
            [
                'name' => 'Demo Site Engineer',
                'password' => Hash::make(self::ENGINEER_PASSWORD),
                'email_verified_at' => now(),
                'locale' => 'en',
            ],
        );
        if (! $engineer->hasRole(Roles::SITE_ENGINEER)) {
            $engineer->assignRole(Roles::SITE_ENGINEER);
        }

        $workerUser = User::query()->firstOrCreate(
            ['email' => self::WORKER_EMAIL],
            [
                'name' => 'Demo Worker',
                'password' => Hash::make(self::WORKER_PASSWORD),
                'email_verified_at' => now(),
                'locale' => 'en',
            ],
        );
        if (! $workerUser->hasRole(Roles::WORKER)) {
            $workerUser->assignRole(Roles::WORKER);
        }

        $project = Project::query()->orderBy('id')->first();
        if ($project) {
            $worker = Worker::query()->firstOrCreate(
                ['user_id' => $workerUser->id],
                [
                    'project_id' => $project->id,
                    'name' => 'Demo Worker',
                    'role' => Worker::ROLE_LABORER,
                    'daily_rate_usd' => 40,
                    'overtime_rate_usd' => 8,
                ],
            );
            if (! $worker->project_id) {
                $worker->update(['project_id' => $project->id]);
            }
        }
    }
}
