<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_gallery_renders_and_groups_documents(): void
    {
        Storage::fake(Document::DISK);

        $user = $this->userWithRole();
        $project = Project::query()->create(['name' => 'Doc Site']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Doc Worker',
        ]);

        Document::query()->create([
            'project_id' => $project->id,
            'worker_id' => $worker->id,
            'type' => Document::TYPE_SITE_PHOTO,
            'title' => 'North elevation',
            'original_name' => 'site.jpg',
            'path' => $project->id.'/site_photo/site.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1200,
            'uploaded_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('documents.index', [
            'project_id' => $project->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Documents/Gallery')
            ->has('documents', 1)
            ->has('grouped', 1)
            ->where('grouped.0.type', Document::TYPE_SITE_PHOTO)
            ->where('filters.project_id', $project->id));
    }

    public function test_store_uploads_to_project_type_directory(): void
    {
        Storage::fake(Document::DISK);

        $user = $this->userWithRole();
        $project = Project::query()->create(['name' => 'Upload Site']);
        $worker = Worker::query()->create([
            'project_id' => $project->id,
            'name' => 'Upload Worker',
        ]);

        // Avoid GD dependency in CI/cloud agents.
        $file = UploadedFile::fake()->create('receipt.png', 120, 'image/png');

        $response = $this->actingAs($user)->post(route('documents.store'), [
            'project_id' => $project->id,
            'worker_id' => $worker->id,
            'type' => Document::TYPE_RECEIPT,
            'title' => 'Fuel receipt',
            'file' => $file,
        ]);

        $response->assertRedirect();

        $doc = Document::query()->first();
        $this->assertNotNull($doc);
        $this->assertSame(Document::TYPE_RECEIPT, $doc->type);
        $this->assertSame($project->id, $doc->project_id);
        $this->assertSame($worker->id, $doc->worker_id);
        $this->assertStringStartsWith($project->id.'/receipt/', $doc->path);
        Storage::disk(Document::DISK)->assertExists($doc->path);
    }

    public function test_file_endpoint_streams_stored_document(): void
    {
        Storage::fake(Document::DISK);

        $user = $this->userWithRole();
        $project = Project::query()->create(['name' => 'Stream Site']);
        $path = $project->id.'/contract/demo.pdf';
        Storage::disk(Document::DISK)->put($path, '%PDF-1.4 demo');

        $doc = Document::query()->create([
            'project_id' => $project->id,
            'type' => Document::TYPE_CONTRACT,
            'original_name' => 'demo.pdf',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => 12,
            'uploaded_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('documents.file', $doc));

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    public function test_rejects_oversized_or_wrong_mime(): void
    {
        Storage::fake(Document::DISK);

        $user = $this->userWithRole();
        $project = Project::query()->create(['name' => 'Reject Site']);

        $response = $this->actingAs($user)->post(route('documents.store'), [
            'project_id' => $project->id,
            'type' => Document::TYPE_PROFILE_PHOTO,
            'file' => UploadedFile::fake()->create('notes.csv', 100, 'text/csv'),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, Document::query()->count());
    }
}
