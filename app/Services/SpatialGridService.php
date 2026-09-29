<?php

namespace App\Services;

use App\Models\ApartmentUnit;
use App\Models\BuildingBlock;
use App\Models\Project;
use App\Models\User;
use App\Models\Worker;
use App\Support\AuditActions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SpatialGridService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Build Floor (Y) × apartment (X) matrix for one project / category / optional block.
     *
     * @return array{
     *   floors: list<int>,
     *   units: list<string>,
     *   cells: array<string, array<string, mixed>>,
     *   summary: array<string, int>
     * }
     */
    public function matrix(Project $project, string $category, ?int $blockId = null): array
    {
        if (! in_array($category, ApartmentUnit::CATEGORIES, true)) {
            throw new InvalidArgumentException('Unknown spatial category: '.$category);
        }

        $query = ApartmentUnit::query()
            ->with(['assignedWorker:id,name,labor_kind', 'buildingBlock:id,code,name'])
            ->where('project_id', $project->id)
            ->where('category', $category);

        if ($blockId) {
            $query->where('building_block_id', $blockId);
        }

        /** @var Collection<int, ApartmentUnit> $rows */
        $rows = $query->orderBy('floor_number')->orderBy('unit_label')->get();

        $floors = $rows->pluck('floor_number')->filter(fn ($n) => $n !== null)->unique()->sort()->values()->all();
        $units = $rows->pluck('unit_label')->unique()->sort()->values()->all();

        $cells = [];
        foreach ($rows as $row) {
            $key = $this->cellKey((int) $row->floor_number, (string) $row->unit_label);
            $cells[$key] = $this->serializeCell($row);
        }

        $summary = [
            'total' => $rows->count(),
            'pending' => $rows->where('status', ApartmentUnit::STATUS_PENDING)->count(),
            'in_progress' => $rows->where('status', ApartmentUnit::STATUS_IN_PROGRESS)->count(),
            'completed' => $rows->where('status', ApartmentUnit::STATUS_COMPLETED)->count(),
            'inspected' => $rows->where('status', ApartmentUnit::STATUS_INSPECTED)->count(),
            'company_crew' => $rows->where('is_company_crew', true)->count(),
            'assigned' => $rows->whereNotNull('assigned_worker_id')->count(),
        ];

        return [
            'floors' => array_map('intval', $floors),
            'units' => $units,
            'cells' => $cells,
            'summary' => $summary,
        ];
    }

    /**
     * Update a single cell (status / person / xoman / notes).
     *
     * @param  array{
     *   status?: string,
     *   assigned_worker_id?: int|null,
     *   is_company_crew?: bool,
     *   notes?: string|null
     * }  $data
     */
    public function updateCell(ApartmentUnit $unit, array $data, ?User $actor = null): ApartmentUnit
    {
        return DB::transaction(function () use ($unit, $data, $actor) {
            $this->applyAssignment($unit, $data);
            $unit->save();

            $this->audit->log(
                AuditActions::SPATIAL_CELL_UPDATED,
                'Spatial cell updated',
                $unit,
                [
                    'category' => $unit->category,
                    'floor_number' => $unit->floor_number,
                    'unit_label' => $unit->unit_label,
                    'status' => $unit->status,
                    'is_company_crew' => $unit->is_company_crew,
                    'assigned_worker_id' => $unit->assigned_worker_id,
                ],
                $actor,
            );

            return $unit->fresh(['assignedWorker:id,name,labor_kind', 'buildingBlock:id,code,name']);
        });
    }

    /**
     * Bulk-assign status and/or person / company crew across many unit ids.
     *
     * @param  list<int>  $unitIds
     * @param  array{
     *   status?: string,
     *   assigned_worker_id?: int|null,
     *   is_company_crew?: bool,
     *   notes?: string|null
     * }  $data
     * @return int Number of units updated
     */
    public function bulkAssign(array $unitIds, array $data, ?User $actor = null): int
    {
        $ids = array_values(array_unique(array_map('intval', $unitIds)));
        if ($ids === []) {
            return 0;
        }

        return (int) DB::transaction(function () use ($ids, $data, $actor) {
            $units = ApartmentUnit::query()->whereIn('id', $ids)->get();
            $count = 0;

            foreach ($units as $unit) {
                $this->applyAssignment($unit, $data);
                $unit->save();
                $count++;
            }

            $this->audit->log(
                AuditActions::SPATIAL_BULK_ASSIGNED,
                'Spatial bulk assignment',
                null,
                [
                    'unit_ids' => $ids,
                    'count' => $count,
                    'payload' => [
                        'status' => $data['status'] ?? null,
                        'assigned_worker_id' => $data['assigned_worker_id'] ?? null,
                        'is_company_crew' => $data['is_company_crew'] ?? null,
                    ],
                ],
                $actor,
            );

            return $count;
        });
    }

    /**
     * Ensure a rectangular grid exists (demo / ensure helpers).
     *
     * @param  list<int>  $floors
     * @param  list<string>  $unitLabels
     * @param  list<string>  $categories
     */
    public function ensureGrid(
        Project $project,
        BuildingBlock $block,
        array $floors,
        array $unitLabels,
        array $categories,
    ): int {
        $created = 0;

        foreach ($categories as $category) {
            if (! in_array($category, ApartmentUnit::CATEGORIES, true)) {
                continue;
            }

            foreach ($floors as $floor) {
                foreach ($unitLabels as $label) {
                    $unit = ApartmentUnit::query()->firstOrCreate(
                        [
                            'project_id' => $project->id,
                            'building_block_id' => $block->id,
                            'floor_number' => (int) $floor,
                            'unit_label' => (string) $label,
                            'category' => $category,
                        ],
                        [
                            'status' => ApartmentUnit::STATUS_PENDING,
                            'is_company_crew' => false,
                        ],
                    );

                    if ($unit->wasRecentlyCreated) {
                        $created++;
                    }
                }
            }
        }

        return $created;
    }

    public function cellKey(int $floorNumber, string $unitLabel): string
    {
        return $floorNumber.'|'.$unitLabel;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeCell(ApartmentUnit $unit): array
    {
        return [
            'id' => $unit->id,
            'floor_number' => $unit->floor_number,
            'unit_label' => $unit->unit_label,
            'category' => $unit->category,
            'status' => $unit->status,
            'notes' => $unit->notes,
            'is_company_crew' => (bool) $unit->is_company_crew,
            'assigned_worker_id' => $unit->assigned_worker_id,
            'assigned_worker' => $unit->assignedWorker
                ? [
                    'id' => $unit->assignedWorker->id,
                    'name' => $unit->assignedWorker->name,
                    'labor_kind' => $unit->assignedWorker->labor_kind,
                ]
                : null,
            'building_block' => $unit->buildingBlock
                ? [
                    'id' => $unit->buildingBlock->id,
                    'code' => $unit->buildingBlock->code,
                    'name' => $unit->buildingBlock->name,
                ]
                : null,
            'badge_label' => $unit->is_company_crew
                ? 'xoman'
                : ($unit->assignedWorker?->name ?? null),
        ];
    }

    /**
     * @param  array{
     *   status?: string,
     *   assigned_worker_id?: int|null,
     *   is_company_crew?: bool,
     *   notes?: string|null
     * }  $data
     */
    private function applyAssignment(ApartmentUnit $unit, array $data): void
    {
        if (array_key_exists('status', $data) && $data['status'] !== null && $data['status'] !== '') {
            $status = (string) $data['status'];
            // Accept legacy "done" from older clients
            if ($status === 'done') {
                $status = ApartmentUnit::STATUS_COMPLETED;
            }
            if ($status === 'company') {
                throw new InvalidArgumentException('Use is_company_crew for xoman; status must be pending/in_progress/completed/inspected.');
            }
            if (! in_array($status, ApartmentUnit::STATUSES, true)) {
                throw new InvalidArgumentException('Invalid spatial status: '.$status);
            }
            $unit->status = $status;
        }

        if (array_key_exists('notes', $data)) {
            $unit->notes = $data['notes'];
        }

        $wantsCrew = array_key_exists('is_company_crew', $data)
            ? (bool) $data['is_company_crew']
            : null;

        if ($wantsCrew === true) {
            $unit->is_company_crew = true;
            $unit->assigned_worker_id = null;
        } elseif (array_key_exists('assigned_worker_id', $data)) {
            $workerId = $data['assigned_worker_id'];
            if ($workerId === null || $workerId === '' || (int) $workerId === 0) {
                $unit->assigned_worker_id = null;
                if ($wantsCrew === false) {
                    $unit->is_company_crew = false;
                }
            } else {
                $workerId = (int) $workerId;
                if (! Worker::query()->whereKey($workerId)->exists()) {
                    throw new InvalidArgumentException('Person not found.');
                }
                $unit->assigned_worker_id = $workerId;
                $unit->is_company_crew = false;
            }
        } elseif ($wantsCrew === false) {
            $unit->is_company_crew = false;
        }
    }
}
