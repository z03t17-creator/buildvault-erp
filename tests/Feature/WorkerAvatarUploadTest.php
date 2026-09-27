<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkerAvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Minimal 1×1 PNG (no GD extension required in CI).
     */
    protected function fakePng(string $name = 'crew.png'): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        );

        $path = sys_get_temp_dir().'/'.$name;
        file_put_contents($path, $png);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    public function test_worker_store_persists_avatar_on_public_disk(): void
    {
        Storage::fake('public');

        $user = $this->userWithRole();
        $file = $this->fakePng('crew.png');

        $response = $this->actingAs($user)->post(route('workers.store'), [
            'name' => 'Photo Worker',
            'role' => Worker::ROLE_LABORER,
            'avatar' => $file,
        ]);

        $worker = Worker::query()->where('name', 'Photo Worker')->first();
        $this->assertNotNull($worker);
        $this->assertNotNull($worker->avatar_path);
        $this->assertStringStartsWith('uploads/workers/', $worker->avatar_path);
        Storage::disk('public')->assertExists($worker->avatar_path);

        $response->assertRedirect(route('workers.show', $worker));

        $this->actingAs($user)
            ->get(route('workers.show', $worker))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workers/Show')
                ->where('worker.avatar_path', $worker->avatar_path)
                ->where('worker.avatar_url', '/storage/'.$worker->avatar_path)
            );
    }

    public function test_worker_update_replaces_avatar_and_deletes_old_file(): void
    {
        Storage::fake('public');

        $user = $this->userWithRole();
        $oldPath = $this->fakePng('old.png')->store('uploads/workers', 'public');

        $worker = Worker::query()->create([
            'name' => 'Replace Me',
            'role' => Worker::ROLE_ENGINEER,
            'avatar_path' => $oldPath,
        ]);

        $this->actingAs($user)
            ->post(route('workers.update', $worker), [
                '_method' => 'put',
                'name' => 'Replace Me',
                'role' => Worker::ROLE_ENGINEER,
                'avatar' => $this->fakePng('new.png'),
            ])
            ->assertRedirect(route('workers.show', $worker));

        $worker->refresh();
        $this->assertNotSame($oldPath, $worker->avatar_path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($worker->avatar_path);
    }

    public function test_avatar_rejects_non_image(): void
    {
        Storage::fake('public');

        $user = $this->userWithRole();

        $this->actingAs($user)
            ->post(route('workers.store'), [
                'name' => 'Bad File',
                'avatar' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('avatar');

        $this->assertDatabaseMissing('workers', ['name' => 'Bad File']);
    }
}
