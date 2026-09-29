<?php

namespace Tests\Feature;

use App\Models\ApartmentUnit;
use App\Models\Expense;
use App\Models\Penalty;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Worker;
use App\Services\BusinessDataWipeService;
use App\Services\MayorcaWorkbookImportService;
use App\Support\SimpleXlsxWriter;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MayorcaImportPhase25Test extends TestCase
{
    use RefreshDatabase;

    private string $samplePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(DemoUsersSeeder::class);

        $this->samplePath = storage_path('app/imports/samples/test-mayorca.xlsx');
        if (! is_dir(dirname($this->samplePath))) {
            mkdir(dirname($this->samplePath), 0775, true);
        }

        Artisan::call('mayorca:import', ['--write-sample' => true]);
        // Copy default sample to test path if write-sample used default location
        $default = MayorcaWorkbookImportService::sampleAbsolutePath();
        if (is_file($default) && $default !== $this->samplePath) {
            copy($default, $this->samplePath);
        }
    }

    public function test_wipe_dry_run_does_not_delete_and_reports_counts(): void
    {
        $project = Project::query()->create(['name' => 'Demo Wipe']);
        Worker::query()->create(['name' => 'Temp Person']);
        Expense::query()->create([
            'project_id' => $project->id,
            'category' => Expense::CATEGORY_OTHER,
            'amount_iqd' => 1000,
            'amount_usd' => 0,
            'currency' => 'IQD',
            'expense_date' => now()->toDateString(),
            'approval_status' => Expense::STATUS_PENDING,
        ]);

        $wiper = app(BusinessDataWipeService::class);
        $result = $wiper->wipe(dryRun: true);

        $this->assertTrue($result['dry_run']);
        $this->assertGreaterThan(0, $result['wiped_tables']['projects'] ?? 0);
        $this->assertSame(1, Project::query()->count());
        $this->assertSame(1, Worker::query()->count());
        $this->assertContains(UserSeeder::ADMIN_EMAIL, $result['kept_users']);
    }

    public function test_wipe_commit_clears_business_data_keeps_core_users(): void
    {
        Project::query()->create(['name' => 'To Wipe']);
        Worker::query()->create(['name' => 'Wipe Me']);
        User::factory()->create(['email' => 'extra@example.test']);

        $wiper = app(BusinessDataWipeService::class);
        $result = $wiper->wipe(dryRun: false);

        $this->assertFalse($result['dry_run']);
        $this->assertSame(0, Project::query()->count());
        $this->assertSame(0, Worker::query()->count());
        $this->assertSame(0, User::query()->where('email', 'extra@example.test')->count());

        foreach (BusinessDataWipeService::CORE_EMAILS as $email) {
            $this->assertDatabaseHas('users', ['email' => $email]);
        }
    }

    public function test_import_dry_run_does_not_persist_and_keeps_people_unclassified(): void
    {
        $path = MayorcaWorkbookImportService::sampleAbsolutePath();
        $this->assertFileExists($path);

        $importer = app(MayorcaWorkbookImportService::class);
        $result = $importer->import($path, dryRun: true);

        $this->assertTrue($result['dry_run']);
        $this->assertGreaterThan(0, $result['counts']['vault_rows']);
        $this->assertGreaterThan(0, $result['counts']['expenses']);
        $this->assertGreaterThan(0, $result['people']);
        $this->assertSame(0, Worker::query()->count());
        $this->assertSame(0, Transaction::query()->count());
        $this->assertSame(0, Expense::query()->count());
    }

    public function test_import_commit_creates_unclassified_people_and_sheet_targets(): void
    {
        $path = MayorcaWorkbookImportService::sampleAbsolutePath();
        $importer = app(MayorcaWorkbookImportService::class);
        $result = $importer->import($path, dryRun: false);

        $this->assertFalse($result['dry_run']);
        $this->assertGreaterThan(0, Worker::query()->count());
        $this->assertSame(
            Worker::query()->count(),
            Worker::query()->where('labor_kind', Worker::LABOR_KIND_UNCLASSIFIED)->count()
        );

        // Never guessed staff/worker
        $this->assertSame(0, Worker::query()->where('labor_kind', Worker::LABOR_KIND_STAFF)->count());
        $this->assertSame(0, Worker::query()->where('labor_kind', Worker::LABOR_KIND_WORKER)->count());

        $this->assertTrue(Project::query()->where('name', 'Mayorca Zhako')->exists());
        $this->assertTrue(Project::query()->where('name', 'Mirani City')->exists());
        $this->assertTrue(Project::query()->where('name', 'Darwaza Cornish')->exists());

        $this->assertGreaterThan(0, Transaction::query()->count());
        $this->assertGreaterThan(0, Expense::query()->count());
        $this->assertGreaterThan(0, Penalty::query()->count());
        $this->assertGreaterThan(0, ApartmentUnit::query()->count());
        $this->assertGreaterThan(0, ApartmentUnit::query()->where('is_company_crew', true)->count());

        // HISABAT legacy only
        $this->assertGreaterThan(0, $result['counts']['legacy_hisabat_skipped_rows']);
    }

    public function test_artisan_refuses_wipe_without_commit(): void
    {
        $exit = Artisan::call('mayorca:import', [
            '--wipe' => true,
            '--path' => MayorcaWorkbookImportService::sampleAbsolutePath(),
        ]);
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Refusing wipe', Artisan::output());
    }

    public function test_artisan_dry_run_wipe_and_import(): void
    {
        Project::query()->create(['name' => 'Seed Project']);
        $exit = Artisan::call('mayorca:import', [
            '--wipe' => true,
            '--dry-run' => true,
            '--path' => MayorcaWorkbookImportService::sampleAbsolutePath(),
        ]);
        $this->assertSame(0, $exit);
        $this->assertSame(1, Project::query()->count());
    }
}
