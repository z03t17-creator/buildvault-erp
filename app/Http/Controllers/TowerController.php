<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tower\StoreTowerRequest;
use App\Http\Requests\Tower\UpdateTowerRequest;
use App\Models\Project;
use App\Models\Tower;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TowerController extends Controller
{
    public function index(Project $project): Response
    {
        return Inertia::render('Towers/Index', [
            'project' => $project,
            'towers' => $project->towers()->withCount('floors')->orderBy('name')->get(),
        ]);
    }

    public function create(Project $project): Response
    {
        return Inertia::render('Towers/Create', [
            'project' => $project,
        ]);
    }

    public function store(StoreTowerRequest $request, Project $project): RedirectResponse
    {
        $tower = $project->towers()->create($request->validated());

        return redirect()
            ->route('towers.show', $tower)
            ->with('success', 'Tower created.');
    }

    public function show(Tower $tower): Response
    {
        $tower->load(['project', 'floors']);

        return Inertia::render('Towers/Show', [
            'tower' => $tower,
            'project' => $tower->project,
        ]);
    }

    public function edit(Tower $tower): Response
    {
        $tower->load('project');

        return Inertia::render('Towers/Edit', [
            'tower' => $tower,
            'project' => $tower->project,
        ]);
    }

    public function update(UpdateTowerRequest $request, Tower $tower): RedirectResponse
    {
        $tower->update($request->validated());

        return redirect()
            ->route('towers.show', $tower)
            ->with('success', 'Tower updated.');
    }

    public function destroy(Tower $tower): RedirectResponse
    {
        $project = $tower->project;
        $tower->delete();

        return redirect()
            ->route('projects.towers.index', $project)
            ->with('success', 'Tower deleted.');
    }
}
