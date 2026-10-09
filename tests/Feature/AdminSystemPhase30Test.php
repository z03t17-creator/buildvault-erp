<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\Import;
use App\Models\User;
use App\Support\AuditActions;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminSystemPhase30Test extends TestCase
{
    use RefreshDatabase;

    public function test_audit_index_exposes_overview_and_filters(): void
    {
        $admin = $this->actingAsRole(Roles::SUPER_ADMIN);

        Activity::query()->create([
            'log_name' => AuditActions::LOG_NAME,
            'description' => 'Vault money in',
            'event' => AuditActions::VAULT_MONEY_IN,
            'causer_type' => $admin->getMorphClass(),
            'causer_id' => $admin->id,
        ]);

        $this->get(route('audit.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Audit/Log')
                ->has('entries')
                ->has('overview')
                ->where('overview.count', fn ($v) => (int) $v >= 1)
                ->where('overview.limit', 200)
                ->has('actions')
                ->has('users')
                ->has('filters')
            );

        $this->get(route('audit.index', ['action' => AuditActions::VAULT_MONEY_IN]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.action', AuditActions::VAULT_MONEY_IN)
                ->where('overview.count', fn ($v) => (int) $v >= 1)
            );
    }

    public function test_backups_index_exposes_overview_and_schedule(): void
    {
        $this->actingAsRole(Roles::SUPER_ADMIN);

        Backup::query()->create([
            'type' => 'full',
            'status' => Backup::STATUS_COMPLETED,
            'filename' => 'demo.zip',
            'location' => 'backups/demo.zip',
            'size_bytes' => 1024,
            'finished_at' => now(),
        ]);

        Backup::query()->create([
            'type' => 'database',
            'status' => Backup::STATUS_FAILED,
            'message' => 'disk full',
        ]);

        $this->get(route('backups.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Backups/Index')
                ->has('backups', 2)
                ->has('overview')
                ->where('overview.count', 2)
                ->where('overview.completed', 1)
                ->where('overview.failed', 1)
                ->has('schedule.cron')
                ->has('types')
            );
    }

    public function test_imports_index_exposes_mayorca_flag_and_overview(): void
    {
        $this->actingAsRole(Roles::SUPER_ADMIN);

        Import::query()->create([
            'type' => 'workers',
            'mode' => Import::MODE_PARTIAL ?? 'partial',
            'status' => Import::STATUS_COMPLETED,
            'original_filename' => 'people.csv',
            'total_rows' => 3,
            'success_rows' => 3,
            'failed_rows' => 0,
        ]);

        $this->get(route('imports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Imports/Index')
                ->where('canImportMayorca', true)
                ->has('workbookBundled')
                ->has('overview')
                ->where('overview.count', fn ($v) => (int) $v >= 1)
                ->where('overview.completed', fn ($v) => (int) $v >= 1)
                ->has('types')
                ->has('recent')
            );

        $boss = $this->userWithRole(Roles::BOSS_CONTRACTOR);
        $this->actingAs($boss)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canImportMayorca', false)
            );
    }
}
