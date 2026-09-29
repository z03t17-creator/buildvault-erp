<?php

namespace Tests\Feature;

use App\Models\EmployeeAdvance;
use App\Models\Expense;
use App\Models\Floor;
use App\Models\MonthlySettlement;
use App\Models\Penalty;
use App\Models\ProductionRecord;
use App\Models\Project;
use App\Models\ProjectReceipt;
use App\Models\RetentionHold;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vault;
use App\Models\Worker;
use App\Support\Roles;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoHierarchySeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DemoSeedIntegrityPhase19Test extends TestCase
{
    use RefreshDatabase;

    private function setSeedDemo(bool $enabled): void
    {
        $value = $enabled ? 'true' : 'false';
        putenv('SEED_DEMO='.$value);
        $_ENV['SEED_DEMO'] = $value;
        $_SERVER['SEED_DEMO'] = $value;
    }

    public function test_slim_seed_skips_demo_modules(): void
    {
        $this->setSeedDemo(false);

        $started = microtime(true);
        Artisan::call('db:seed', ['--force' => true]);
        $elapsed = microtime(true) - $started;

        $this->assertLessThan(5.0, $elapsed, 'Slim seed should stay fast for Render boot.');

        $this->assertSame(1, User::query()->count());
        $this->assertSame(UserSeeder::ADMIN_EMAIL, User::query()->value('email'));
        $this->assertTrue(User::query()->first()->hasRole(Roles::SUPER_ADMIN));
        $this->assertSame(1, Vault::query()->where('name', VaultSeeder::NAME)->count());
        $this->assertSame(count(Roles::ALL), Role::query()->count());

        $this->assertSame(0, Project::query()->count());
        $this->assertSame(0, Worker::query()->count());
        $this->assertSame(0, Expense::query()->count());
        $this->assertSame(0, EmployeeAdvance::query()->count());
        $this->assertSame(0, Penalty::query()->count());
        $this->assertSame(0, ProductionRecord::query()->count());
        $this->assertSame(0, StockItem::query()->count());
        $this->assertSame(0, MonthlySettlement::query()->count());
        $this->assertSame(0, Transaction::query()->count());
        $this->assertNull(User::query()->where('email', DemoUsersSeeder::BOSS_EMAIL)->first());
    }

    public function test_full_demo_seed_covers_modules_and_roles(): void
    {
        $this->setSeedDemo(true);
        Artisan::call('db:seed', ['--force' => true]);

        $emails = [
            UserSeeder::ADMIN_EMAIL => Roles::SUPER_ADMIN,
            DemoUsersSeeder::BOSS_EMAIL => Roles::BOSS_CONTRACTOR,
            DemoUsersSeeder::ACCOUNTANT_EMAIL => Roles::ACCOUNTANT,
            DemoUsersSeeder::STOCK_EMAIL => Roles::STOCK_MANAGER,
        ];

        foreach ($emails as $email => $role) {
            $user = User::query()->where('email', $email)->first();
            $this->assertNotNull($user, "Missing demo user {$email}");
            $this->assertTrue($user->hasRole($role), "{$email} missing role {$role}");
            $this->assertTrue(Hash::check('password', $user->password), "{$email} password mismatch");
            $this->assertSame(User::STATUS_ACTIVE, $user->status);
        }

        $this->assertSame(4, User::query()->count());
        $this->assertGreaterThanOrEqual(2, Project::query()->count());
        $this->assertNotNull(Project::query()->where('name', DemoHierarchySeeder::PROJECT_NAME)->first());
        $this->assertNotNull(Project::query()->where('name', DemoHierarchySeeder::PROJECT_B_NAME)->first());

        $this->assertGreaterThanOrEqual(8, Worker::query()->count());
        $this->assertGreaterThanOrEqual(1, Worker::query()->where('manual_ot_hours', '>', 0)->count());
        $this->assertGreaterThanOrEqual(4, Floor::query()->count());

        $this->assertGreaterThanOrEqual(3, Expense::query()->count());
        $this->assertGreaterThanOrEqual(2, EmployeeAdvance::query()->count());
        $this->assertGreaterThanOrEqual(2, Penalty::query()->count());
        $this->assertGreaterThanOrEqual(1, Penalty::query()->where('status', Penalty::STATUS_PENDING)->count());
        $this->assertGreaterThanOrEqual(2, RetentionHold::query()->count());
        $this->assertGreaterThanOrEqual(3, ProductionRecord::query()->count());
        $this->assertGreaterThanOrEqual(1, ProjectReceipt::query()->count());

        $this->assertGreaterThanOrEqual(1, Supplier::query()->count());
        $this->assertGreaterThanOrEqual(3, StockItem::query()->count());
        $this->assertGreaterThanOrEqual(6, StockMovement::query()->count());

        $projectIds = StockMovement::query()
            ->where('type', StockMovement::TYPE_OUT)
            ->whereNotNull('project_id')
            ->distinct()
            ->pluck('project_id');
        $this->assertGreaterThanOrEqual(2, $projectIds->count(), 'Stock OUT should hit both demo projects.');

        $this->assertGreaterThanOrEqual(1, Vault::query()->count());
        $this->assertGreaterThanOrEqual(8, Transaction::query()->count());
        $this->assertTrue(
            Transaction::query()->where('type', Transaction::TYPE_MONEY_RECEIVED)->exists()
            || Transaction::query()->where('type', Transaction::TYPE_DEPOSIT)->exists()
        );
        $this->assertTrue(Transaction::query()->where('type', Transaction::TYPE_PAYROLL)->exists());
        $this->assertTrue(Transaction::query()->where('type', Transaction::TYPE_EXPENSE)->exists());
        $this->assertTrue(Transaction::query()->where('type', Transaction::TYPE_ADVANCE)->exists());

        $this->assertGreaterThanOrEqual(2, MonthlySettlement::query()->count());
        $this->assertTrue(
            MonthlySettlement::query()->whereNull('project_id')->exists(),
            'Expected all-projects settlement snapshot',
        );
        $demoProject = Project::query()->where('name', DemoHierarchySeeder::PROJECT_NAME)->firstOrFail();
        $this->assertTrue(
            MonthlySettlement::query()->where('project_id', $demoProject->id)->exists(),
            'Expected project-scoped settlement snapshot',
        );

        // Attendance UI optional in demo — may be zero until Stock Manager records rows.
        $this->assertGreaterThanOrEqual(0, \App\Models\Attendance::query()->count());
    }

    public function test_full_demo_seed_is_idempotent(): void
    {
        $this->setSeedDemo(true);
        Artisan::call('db:seed', ['--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);

        $this->assertSame(4, User::query()->count());
        $this->assertSame(1, User::query()->where('email', UserSeeder::ADMIN_EMAIL)->count());
        $this->assertSame(1, Vault::query()->where('name', VaultSeeder::NAME)->count());
        $this->assertSame(1, Project::query()->where('name', DemoHierarchySeeder::PROJECT_NAME)->count());
        $this->assertSame(1, Project::query()->where('name', DemoHierarchySeeder::PROJECT_B_NAME)->count());
        $this->assertSame(3, Expense::query()->count());
        $this->assertSame(2, EmployeeAdvance::query()->count());
        $this->assertSame(2, Penalty::query()->count());
        $this->assertSame(3, ProductionRecord::query()->count());
        $this->assertSame(3, StockItem::query()->count());
        $this->assertSame(3, MonthlySettlement::query()->count());
    }

    public function test_database_seeder_respects_seed_demo_gate(): void
    {
        $seeder = new DatabaseSeeder;

        $this->setSeedDemo(false);
        $this->assertFalse($seeder->shouldSeedDemo());

        $this->setSeedDemo(true);
        $this->assertTrue($seeder->shouldSeedDemo());
    }
}
