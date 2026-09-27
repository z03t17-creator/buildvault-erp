<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        File::ensureDirectoryExists(storage_path('app/backups'));
        File::ensureDirectoryExists(storage_path('app/uploads'));
        File::put(storage_path('app/uploads/.keep'), '');
    }

    protected function tearDown(): void
    {
        foreach (Storage::disk('backups')->allFiles() as $file) {
            if (str_ends_with(strtolower($file), '.zip')) {
                Storage::disk('backups')->delete($file);
            }
        }
        parent::tearDown();
    }

    public function test_backups_index_renders(): void
    {
        $user = User::factory()->create();

        Backup::query()->create([
            'type' => Backup::TYPE_FULL,
            'filename' => 'demo.zip',
            'disk' => Backup::DISK,
            'location' => 'BuildVault/demo.zip',
            'size_bytes' => 1234,
            'status' => Backup::STATUS_COMPLETED,
            'created_by' => $user->id,
            'finished_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('backups.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Backups/Index')
                ->has('backups', 1)
                ->has('schedule')
                ->where('backups.0.filename', 'demo.zip'));
    }

    public function test_trigger_files_backup_logs_completed_run(): void
    {
        $user = User::factory()->create();

        // Files-only avoids sqlite3 dump while PHPUnit holds the DB lock.
        $response = $this->actingAs($user)->post(route('backups.store'), [
            'type' => Backup::TYPE_FILES,
        ]);

        $backup = Backup::query()->latest('id')->first();
        $this->assertNotNull($backup);
        $response->assertRedirect(route('backups.index'));

        $this->assertSame(Backup::STATUS_COMPLETED, $backup->status, (string) $backup->message);
        $this->assertSame(Backup::TYPE_FILES, $backup->type);
        $this->assertSame($user->id, $backup->created_by);
        $this->assertNotNull($backup->location);
        $this->assertTrue(Storage::disk('backups')->exists($backup->location));
        $this->assertGreaterThan(0, $backup->size_bytes);
    }

    public function test_download_completed_backup(): void
    {
        $user = User::factory()->create();
        Storage::disk('backups')->put('BuildVault/ok.zip', 'PK'.str_repeat('y', 400));

        $backup = Backup::query()->create([
            'type' => Backup::TYPE_FULL,
            'filename' => 'ok.zip',
            'disk' => Backup::DISK,
            'location' => 'BuildVault/ok.zip',
            'size_bytes' => 402,
            'status' => Backup::STATUS_COMPLETED,
            'created_by' => $user->id,
            'finished_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('backups.download', $backup))
            ->assertOk()
            ->assertDownload('ok.zip');
    }

    public function test_backup_run_logged_command_files(): void
    {
        $this->artisan('backup:run-logged', ['--type' => 'files'])
            ->assertSuccessful();

        $backup = Backup::query()->latest('id')->first();
        $this->assertNotNull($backup);
        $this->assertSame(Backup::STATUS_COMPLETED, $backup->status, (string) $backup->message);
    }

    public function test_failed_backup_is_logged(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('backup:run', ['--only-db' => true])
            ->andReturn(1);
        Artisan::shouldReceive('output')->andReturn('Simulated dump failure');

        $backup = app(BackupService::class)->run(Backup::TYPE_DATABASE);

        $this->assertSame(Backup::STATUS_FAILED, $backup->status);
        $this->assertStringContainsString('Simulated', (string) $backup->message);
    }

    public function test_service_rejects_invalid_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(BackupService::class)->run('nope');
    }
}
