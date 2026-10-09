<?php

namespace App\Http\Controllers;

use App\Models\ApartmentUnit;
use App\Models\BuildingBlock;
use App\Models\Project;
use App\Models\Worker;
use App\Services\SpatialGridService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class SpatialGridController extends Controller
{
    public function __construct(
        private readonly SpatialGridService $grid,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ApartmentUnit::class);

        $projectId = $request->integer('project_id') ?: null;
        $category = (string) $request->input('category', ApartmentUnit::CATEGORY_MDF);
        if (! in_array($category, ApartmentUnit::CATEGORIES, true)) {
            $category = ApartmentUnit::CATEGORY_MDF;
        }
        $blockId = $request->integer('block_id') ?: null;

        $projects = Project::query()
            ->where(function ($q) {
                $q->whereHas('buildingBlocks')
                    ->orWhereHas('apartmentUnits');
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        // Fallback: all projects if none have spatial data yet
        if ($projects->isEmpty()) {
            $projects = Project::query()->orderBy('name')->get(['id', 'name']);
        }

        if (! $projectId && $projects->isNotEmpty()) {
            $projectId = (int) $projects->first()->id;
        }

        $project = $projectId
            ? Project::query()->find($projectId)
            : null;

        $blocks = $project
            ? BuildingBlock::query()
                ->where('project_id', $project->id)
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'sort_order'])
            : collect();

        if ($blockId && $blocks->where('id', $blockId)->isEmpty()) {
            $blockId = null;
        }
        if (! $blockId && $blocks->isNotEmpty()) {
            $blockId = (int) $blocks->first()->id;
        }

        $matrix = null;
        if ($project) {
            $matrix = $this->grid->matrix($project, $category, $blockId);
        }

        // Categories that have at least one cell for this project/block
        $availableCategories = ApartmentUnit::CATEGORIES;
        if ($project) {
            $q = ApartmentUnit::query()
                ->where('project_id', $project->id)
                ->select('category')
                ->distinct();
            if ($blockId) {
                $q->where('building_block_id', $blockId);
            }
            $present = $q->pluck('category')->all();
            if ($present !== []) {
                $availableCategories = array_values(array_intersect(ApartmentUnit::CATEGORIES, $present));
                // Always keep primary three even if empty after seed wipe
                foreach ([ApartmentUnit::CATEGORY_MDF, ApartmentUnit::CATEGORY_LAMINATE, ApartmentUnit::CATEGORY_METXAL] as $must) {
                    if (! in_array($must, $availableCategories, true)) {
                        $availableCategories[] = $must;
                    }
                }
                $availableCategories = array_values(array_unique($availableCategories));
            } else {
                $availableCategories = [
                    ApartmentUnit::CATEGORY_MDF,
                    ApartmentUnit::CATEGORY_LAMINATE,
                    ApartmentUnit::CATEGORY_METXAL,
                ];
            }
        }

        return Inertia::render('Spatial/Index', [
            'projects' => $projects,
            'blocks' => $blocks,
            'people' => Worker::query()->orderBy('name')->get(['id', 'name', 'labor_kind', 'project_id']),
            'filters' => [
                'project_id' => $projectId,
                'category' => $category,
                'block_id' => $blockId,
            ],
            'categories' => $availableCategories,
            'allCategories' => ApartmentUnit::CATEGORIES,
            'statuses' => ApartmentUnit::STATUSES,
            'matrix' => $matrix,
            'project' => $project ? ['id' => $project->id, 'name' => $project->name] : null,
        ]);
    }

    public function update(Request $request, ApartmentUnit $apartmentUnit): RedirectResponse
    {
        $this->authorize('update', $apartmentUnit);

        $data = $request->validate([
            'status' => ['nullable', Rule::in(ApartmentUnit::STATUSES)],
            'assigned_worker_id' => ['nullable', 'integer', 'exists:workers,id'],
            'is_company_crew' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->grid->updateCell($apartmentUnit, $data, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', __('Spatial cell updated.'));
    }

    public function bulkAssign(Request $request): RedirectResponse
    {
        $this->authorize('bulkAssign', ApartmentUnit::class);

        $data = $request->validate([
            'unit_ids' => ['required', 'array', 'min:1'],
            'unit_ids.*' => ['integer', 'exists:apartment_units,id'],
            'status' => ['nullable', Rule::in(ApartmentUnit::STATUSES)],
            'assigned_worker_id' => ['nullable', 'integer', 'exists:workers,id'],
            'is_company_crew' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $payload = collect($data)->except('unit_ids')->all();

        try {
            $count = $this->grid->bulkAssign($data['unit_ids'], $payload, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['unit_ids' => $e->getMessage()]);
        }

        return back()->with('success', __('Assigned :count units.', ['count' => $count]));
    }
}
