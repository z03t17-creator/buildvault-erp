<?php

namespace App\Http\Controllers;

use App\Http\Requests\Worker\StoreWorkerRequest;
use App\Http\Requests\Worker\UpdateWorkerRequest;
use App\Models\Project;
use App\Models\Worker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class WorkerController extends Controller
{
    public const AVATAR_DIR = 'uploads/workers';

    public function index(): Response
    {
        $this->authorize('viewAny', Worker::class);

        return Inertia::render('Workers/Index', [
            'workers' => Worker::query()
                ->with('project:id,name')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Worker::class);

        return Inertia::render('Workers/Create', [
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'roles' => Worker::ROLES,
        ]);
    }

    public function store(StoreWorkerRequest $request): RedirectResponse
    {
        $this->authorize('create', Worker::class);

        $data = $request->safe()->except(['avatar']);

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $this->storeAvatar($request->file('avatar'));
        }

        $worker = Worker::query()->create($data);

        return redirect()
            ->route('workers.show', $worker)
            ->with('success', 'Worker created.');
    }

    public function show(Worker $worker): Response
    {
        $this->authorize('view', $worker);

        $worker->load('project');

        return Inertia::render('Workers/Show', [
            'worker' => $worker,
        ]);
    }

    public function edit(Worker $worker): Response
    {
        $this->authorize('update', $worker);

        return Inertia::render('Workers/Edit', [
            'worker' => $worker,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'roles' => Worker::ROLES,
        ]);
    }

    public function update(UpdateWorkerRequest $request, Worker $worker): RedirectResponse
    {
        $this->authorize('update', $worker);

        $data = $request->safe()->except(['avatar']);

        if ($request->hasFile('avatar')) {
            $this->deleteAvatar($worker->avatar_path);
            $data['avatar_path'] = $this->storeAvatar($request->file('avatar'));
        }

        $worker->update($data);

        return redirect()
            ->route('workers.show', $worker)
            ->with('success', 'Worker updated.');
    }

    public function destroy(Worker $worker): RedirectResponse
    {
        $this->authorize('delete', $worker);

        $this->deleteAvatar($worker->avatar_path);
        $worker->delete();

        return redirect()
            ->route('workers.index')
            ->with('success', 'Worker deleted.');
    }

    protected function storeAvatar(UploadedFile $file): string
    {
        return $file->store(self::AVATAR_DIR, 'public');
    }

    protected function deleteAvatar(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
