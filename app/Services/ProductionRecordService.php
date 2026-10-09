<?php

namespace App\Services;

use App\Models\ProductionRecord;
use App\Models\User;
use InvalidArgumentException;

/**
 * Work / production tracking — quantities only (no IQD/USD).
 *
 * remaining = assigned − completed
 * progress_% = completed / assigned * 100 (when assigned > 0)
 * received is tracked separately and does not affect remaining.
 */
class ProductionRecordService
{
    /**
     * @return array{remaining: float, progress_pct: float}
     */
    public function calculate(float|int|string $assigned, float|int|string $completed): array
    {
        $assigned = round((float) $assigned, 2);
        $completed = round((float) $completed, 2);

        if ($assigned < 0) {
            throw new InvalidArgumentException('Assigned quantity cannot be negative.');
        }

        if ($completed < 0) {
            throw new InvalidArgumentException('Completed quantity cannot be negative.');
        }

        if ($completed > $assigned && $assigned > 0) {
            throw new InvalidArgumentException('Completed cannot exceed assigned.');
        }

        $remaining = round($assigned - $completed, 2);
        $progress = $assigned > 0
            ? round(($completed / $assigned) * 100, 2)
            : 0.0;

        return [
            'remaining' => $remaining,
            'progress_pct' => $progress,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): ProductionRecord
    {
        $payload = $this->normalize($data);
        $math = $this->calculate($payload['assigned'], $payload['completed']);

        return ProductionRecord::query()->create([
            ...$payload,
            'remaining' => $math['remaining'],
            'entered_by' => $actor?->id ?? ($data['entered_by'] ?? null),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ProductionRecord $record, array $data): ProductionRecord
    {
        $payload = $this->normalize(array_merge($record->only([
            'worker_id',
            'project_id',
            'unit_type',
            'unit_label',
            'assigned',
            'completed',
            'received',
            'recorded_on',
            'notes',
        ]), $data));

        $math = $this->calculate($payload['assigned'], $payload['completed']);

        $record->fill([
            ...$payload,
            'remaining' => $math['remaining'],
        ]);
        $record->save();

        return $record->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $unitType = (string) ($data['unit_type'] ?? ProductionRecord::UNIT_APARTMENT);
        if (! in_array($unitType, ProductionRecord::UNIT_TYPES, true)) {
            throw new InvalidArgumentException('Invalid unit type.');
        }

        $unitLabel = isset($data['unit_label']) ? trim((string) $data['unit_label']) : null;
        if ($unitLabel === '') {
            $unitLabel = null;
        }

        if ($unitType === ProductionRecord::UNIT_OTHER && ($unitLabel === null || $unitLabel === '')) {
            throw new InvalidArgumentException('Custom unit label is required when unit type is other.');
        }

        $assigned = round((float) ($data['assigned'] ?? 0), 2);
        $completed = round((float) ($data['completed'] ?? 0), 2);
        $received = round((float) ($data['received'] ?? 0), 2);

        if ($received < 0) {
            throw new InvalidArgumentException('Received quantity cannot be negative.');
        }

        $recordedOn = $data['recorded_on'] ?? now()->toDateString();
        if ($recordedOn instanceof \DateTimeInterface) {
            $recordedOn = $recordedOn->format('Y-m-d');
        }

        return [
            'worker_id' => (int) $data['worker_id'],
            'project_id' => (int) $data['project_id'],
            'unit_type' => $unitType,
            'unit_label' => $unitLabel,
            'assigned' => $assigned,
            'completed' => $completed,
            'received' => $received,
            'recorded_on' => $recordedOn,
            'notes' => isset($data['notes']) && $data['notes'] !== ''
                ? (string) $data['notes']
                : null,
        ];
    }
}
