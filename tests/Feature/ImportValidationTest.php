<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\ImportDetail;
use App\Models\Project;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
    }

    public function test_partial_import_happy_path_creates_workers(): void
    {
        $user = $this->userWithRole();
        $project = Project::query()->create(['name' => 'Import Site']);

        $csv = "name,project_name,role,daily_rate_usd,overtime_rate_usd,spending_limit_usd,phone,national_id_number\n"
            ."Sara Ali,Import Site,laborer,40,60,0,+9647501111111,NID-100\n"
            ."Omar K,Import Site,engineer,80,120,0,+9647502222222,NID-101\n";

        $file = UploadedFile::fake()->createWithContent('workers.csv', $csv);

        $response = $this->actingAs($user)->post(route('imports.store'), [
            'type' => Import::TYPE_WORKERS,
            'mode' => Import::MODE_PARTIAL,
            'file' => $file,
        ]);

        $import = Import::query()->first();
        $this->assertNotNull($import);
        $response->assertRedirect(route('imports.show', $import));

        $this->assertSame(Import::STATUS_COMPLETED, $import->status);
        $this->assertSame(2, $import->success_rows);
        $this->assertSame(0, $import->failed_rows);
        $this->assertSame(2, Worker::query()->count());
        $this->assertDatabaseHas('workers', ['name' => 'Sara Ali', 'project_id' => $project->id]);
    }

    public function test_partial_import_skips_invalid_rows(): void
    {
        $user = $this->userWithRole();
        Project::query()->create(['name' => 'Import Site']);

        $csv = "name,project_name,role,daily_rate_usd,overtime_rate_usd,spending_limit_usd,phone,national_id_number\n"
            ."Good Worker,Import Site,laborer,40,60,0,,\n"
            .",Missing Name,laborer,40,60,0,,\n"
            ."Bad Project,No Such Project,laborer,40,60,0,,\n";

        $file = UploadedFile::fake()->createWithContent('workers.csv', $csv);

        $this->actingAs($user)->post(route('imports.store'), [
            'type' => Import::TYPE_WORKERS,
            'mode' => Import::MODE_PARTIAL,
            'file' => $file,
        ]);

        $import = Import::query()->first();
        $this->assertSame(Import::STATUS_COMPLETED, $import->status);
        $this->assertSame(1, $import->success_rows);
        $this->assertSame(2, $import->failed_rows);
        $this->assertSame(1, Worker::query()->count());

        $invalid = ImportDetail::query()->where('status', ImportDetail::STATUS_INVALID)->count();
        $this->assertSame(2, $invalid);
    }

    public function test_atomic_import_imports_nothing_when_any_row_invalid(): void
    {
        $user = $this->userWithRole();
        Project::query()->create(['name' => 'Import Site']);

        $csv = "name,project_name,role,daily_rate_usd,overtime_rate_usd,spending_limit_usd,phone,national_id_number\n"
            ."Good Worker,Import Site,laborer,40,60,0,,\n"
            .",Bad Row,laborer,40,60,0,,\n";

        $file = UploadedFile::fake()->createWithContent('workers.csv', $csv);

        $this->actingAs($user)->post(route('imports.store'), [
            'type' => Import::TYPE_WORKERS,
            'mode' => Import::MODE_ATOMIC,
            'file' => $file,
        ]);

        $import = Import::query()->first();
        $this->assertSame(Import::STATUS_FAILED, $import->status);
        $this->assertSame(0, $import->success_rows);
        $this->assertSame(0, Worker::query()->count());
        $this->assertTrue(
            ImportDetail::query()->where('status', ImportDetail::STATUS_SKIPPED)->exists()
        );
    }

    public function test_projects_import_and_rollback(): void
    {
        $user = $this->userWithRole();

        $csv = "name,location,status,total_budget_usd,start_date,end_date,description\n"
            ."Rollback Site,Erbil,planning,100000,2026-10-01,2027-10-01,Test\n";

        $file = UploadedFile::fake()->createWithContent('projects.csv', $csv);

        $this->actingAs($user)->post(route('imports.store'), [
            'type' => Import::TYPE_PROJECTS,
            'mode' => Import::MODE_PARTIAL,
            'file' => $file,
        ]);

        $import = Import::query()->first();
        $this->assertSame(1, Project::query()->where('name', 'Rollback Site')->count());

        $this->actingAs($user)
            ->post(route('imports.rollback', $import))
            ->assertRedirect(route('imports.show', $import));

        $import->refresh();
        $this->assertSame(Import::STATUS_ROLLED_BACK, $import->status);
        $this->assertSame(0, Project::query()->where('name', 'Rollback Site')->count());
    }

    public function test_import_show_renders_error_report(): void
    {
        $user = $this->userWithRole();
        Project::query()->create(['name' => 'Import Site']);

        $csv = "name,project_name,role,daily_rate_usd,overtime_rate_usd,spending_limit_usd,phone,national_id_number\n"
            .",Import Site,laborer,40,60,0,,\n";

        $file = UploadedFile::fake()->createWithContent('workers.csv', $csv);

        $this->actingAs($user)->post(route('imports.store'), [
            'type' => Import::TYPE_WORKERS,
            'mode' => Import::MODE_PARTIAL,
            'file' => $file,
        ]);

        $import = Import::query()->first();

        $this->actingAs($user)
            ->get(route('imports.show', $import))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Imports/Show')
                ->where('import.id', $import->id)
                ->where('import.failed_rows', 1)
                ->has('import.details', 1));
    }
}
