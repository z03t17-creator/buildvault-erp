<?php

namespace App\Http\Controllers;

use App\Http\Requests\Floor\StoreFloorRequest;
use App\Http\Requests\Floor\UpdateFloorRequest;
use App\Models\Floor;
use App\Models\Tower;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FloorController extends Controller
{
    public function index(Tower $tower): Response
    {
        $tower->load('project');

        return Inertia::render('Floors/Index', [
            'tower' => $tower,
            'project' => $tower->project,
            'floors' => $tower->floors()->orderBy('name')->get(),
        ]);
    }

    public function create(Tower $tower): Response
    {
        $tower->load('project');

        return Inertia::render('Floors/Create', [
            'tower' => $tower,
            'project' => $tower->project,
        ]);
    }

    public function store(StoreFloorRequest $request, Tower $tower): RedirectResponse
    {
        $floor = $tower->floors()->create($request->validated());

        return redirect()
            ->route('floors.show', $floor)
            ->with('success', 'Floor created.');
    }

    public function show(Floor $floor): Response
    {
        $floor->load('tower.project');

        return Inertia::render('Floors/Show', [
            'floor' => $floor,
            'tower' => $floor->tower,
            'project' => $floor->tower->project,
        ]);
    }

    public function edit(Floor $floor): Response
    {
        $floor->load('tower.project');

        return Inertia::render('Floors/Edit', [
            'floor' => $floor,
            'tower' => $floor->tower,
            'project' => $floor->tower->project,
        ]);
    }

    public function update(UpdateFloorRequest $request, Floor $floor): RedirectResponse
    {
        $floor->update($request->validated());

        return redirect()
            ->route('floors.show', $floor)
            ->with('success', 'Floor updated.');
    }

    public function destroy(Floor $floor): RedirectResponse
    {
        $tower = $floor->tower;
        $floor->delete();

        return redirect()
            ->route('towers.floors.index', $tower)
            ->with('success', 'Floor deleted.');
    }
}
