<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Payout;
use App\Models\Project;
use App\Models\RetentionHold;
use App\Models\User;
use App\Models\Vault;
use App\Models\Worker;
use Carbon\Carbon;
use Database\Seeders\VaultSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_exports_index_lists_sources(): void
    {
        $user = $this->userWithRole();
        Project::query()->create(['name' => 'Export Site']);
        Worker::query()->create(['name' => 'Export Worker']);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/Index')
                ->has('catalog')
                ->has('legacy.projects', 1)
                ->has('legacy.workers', 1)
                ->has('legacy.payouts')
                ->has('legacy.default_month'));

        $this->actingAs($user)
            ->get(route('exports.index'))
            ->assertRedirect(route('reports.index'));
    }

    public function test_project_excel_download(): void
    {
        $user = $this->userWithRole();
        $project = Project::query()->create(['name' => 'Excel Site']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Excel Worker',
        ]);

        Document::query()->create([
            'project_id' => $project->id,
            'worker_id' => $worker->id,
            'type' => Document::TYPE_SITE_PHOTO,
            'title' => 'Site',
            'original_name' => 'site.jpg',
            'path' => $project->id.'/site_photo/site.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
        ]);

        $response = $this->actingAs($user)->get(route('exports.project', $project));

        $response->assertOk();
        $response->assertDownload();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type'),
        );
        $file = $response->baseResponse->getFile();
        $this->assertNotNull($file);
        $this->assertGreaterThan(500, $file->getSize());
    }

    public function test_worker_pdf_download(): void
    {
        $user = $this->userWithRole();
        $project = Project::query()->create(['name' => 'PDF Site']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'PDF Worker',
            'daily_rate_usd' => 50,
        ]);

        $response = $this->actingAs($user)->get(route('exports.worker', [
            'worker' => $worker,
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('pdf', strtolower((string) $response->headers->get('content-type')));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_payout_voucher_pdf_download(): void
    {
        $user = $this->userWithRole();
        $project = Project::query()->create(['name' => 'Voucher Site']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Voucher Worker',
        ]);
        $vault = Vault::query()->create(['name' => VaultSeeder::NAME]);

        $payout = Payout::query()->create([
            'vault_id' => $vault->id,
            'project_id' => $project->id,
            'worker_id' => $worker->id,
            'category' => Payout::CATEGORY_PAYROLL,
            'amount_usd' => 100,
            'amount_iqd' => 131000,
            'exchange_rate' => 1310,
            'retention_holdback' => 10,
            'status' => Payout::STATUS_PENDING,
            'created_by' => $user->id,
            'notes' => 'Test voucher',
        ]);

        RetentionHold::query()->create([
            'vault_id' => $vault->id,
            'project_id' => $project->id,
            'worker_id' => $worker->id,
            'payout_id' => $payout->id,
            'amount_usd' => 10,
            'hold_start' => now()->toDateString(),
            'maturity_date' => now()->addMonths(6)->toDateString(),
            'status' => RetentionHold::STATUS_HOLDING,
        ]);

        $response = $this->actingAs($user)->get(route('exports.voucher', $payout));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
    }
}
