<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_index_lists_template_types(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('imports.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Imports/Index')
            ->has('types', 4)
            ->where('types.0.type', Import::TYPE_WORKERS)
            ->has('recent'));
    }

    public function test_download_csv_and_xlsx_templates(): void
    {
        $user = User::factory()->create();

        foreach (Import::TYPES as $type) {
            $csv = $this->actingAs($user)->get(route('imports.templates.download', [
                'type' => $type,
                'format' => 'csv',
            ]));
            $csv->assertOk();
            $csv->assertDownload("{$type}_import_template.csv");

            $xlsx = $this->actingAs($user)->get(route('imports.templates.download', [
                'type' => $type,
                'format' => 'xlsx',
            ]));
            $xlsx->assertOk();
            $xlsx->assertDownload("{$type}_import_template.xlsx");
        }
    }

    public function test_create_pending_import_skeleton(): void
    {
        $user = User::factory()->create();
        $service = app(ImportService::class);

        $import = $service->createPending([
            'type' => Import::TYPE_WORKERS,
            'mode' => Import::MODE_ATOMIC,
            'original_filename' => 'workers.csv',
            'created_by' => $user->id,
        ]);

        $this->assertDatabaseHas('imports', [
            'id' => $import->id,
            'type' => Import::TYPE_WORKERS,
            'mode' => Import::MODE_ATOMIC,
            'status' => Import::STATUS_PENDING,
            'original_filename' => 'workers.csv',
        ]);
    }

    public function test_generate_templates_writes_committed_files(): void
    {
        $service = app(ImportService::class);
        $paths = $service->generateTemplates();

        $this->assertNotEmpty($paths);
        foreach (Import::TYPES as $type) {
            $this->assertFileExists($service->csvPath($type));
            $this->assertFileExists($service->xlsxPath($type));
        }
        $this->assertFileExists($service->rulesMarkdownPath());
    }
}
