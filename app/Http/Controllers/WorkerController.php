<?php

namespace App\Http\Controllers;

use App\Http\Requests\Worker\StoreWorkerRequest;
use App\Http\Requests\Worker\UpdateWorkerRequest;
use App\Models\Project;
use App\Models\Worker;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WorkerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Workers/Index', [
            'workers' => Worker::query()
                ->with('project:id,name')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Workers/Create', [
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'roles' => Worker::ROLES,
        ]);
    }

    public function store(StoreWorkerRequest $request): RedirectResponse
    {
        $worker = Worker::query()->create($request->validated());

        return redirect()
            ->route('workers.show', $worker)
            ->with('success', 'Worker created.');
    }

    public function show(Worker $worker): Response
    {
        $worker->load('project');

        return Inertia::render('Workers/Show', [
            'worker' => $worker,
        ]);
    }

    public function edit(Worker $worker): Response
    {
        return Inertia::render('Workers/Edit', [
            'worker' => $worker,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'roles' => Worker::ROLES,
        ]);
    }

    public function update(UpdateWorkerRequest $request, Worker $worker): RedirectResponse
    {
        $worker->update($request->validated());

        return redirect()
            ->route('workers.show', $worker)
            ->with('success', 'Worker updated.');
    }

    public function destroy(Worker $worker): RedirectResponse
    {
        $worker->delete();

        return redirect()
            ->route('workers.index')
            ->with('success', 'Worker deleted.');
    }
}
