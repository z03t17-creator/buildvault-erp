<?php

namespace App\Services;

use App\Models\Backup;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

class BackupService
{
    /**
     * Run a Spatie backup and log the result to the backups table.
     */
    public function run(string $type = Backup::TYPE_FULL, ?int $userId = null): Backup
    {
        if (! in_array($type, Backup::TYPES, true)) {
            throw new InvalidArgumentException('Backup type must be full, database, or files.');
        }

        Storage::disk(Backup::DISK)->makeDirectory('/');

        $backup = Backup::query()->create([
            'type' => $type,
            'disk' => Backup::DISK,
            'status' => Backup::STATUS_RUNNING,
            'created_by' => $userId,
            'started_at' => now(),
            'message' => 'Backup running…',
        ]);

        $before = $this->listZipPaths();

        try {
            $params = match ($type) {
                Backup::TYPE_DATABASE => ['--only-db' => true],
                Backup::TYPE_FILES => ['--only-files' => true],
                default => [],
            };

            $exit = Artisan::call('backup:run', $params);
            $output = trim(Artisan::output());

            if ($exit !== 0) {
                $backup->update([
                    'status' => Backup::STATUS_FAILED,
                    'message' => $output !== '' ? $output : "backup:run exited with code {$exit}",
                    'finished_at' => now(),
                ]);

                return $backup->fresh(['creator']);
            }

            $created = array_values(array_diff($this->listZipPaths(), $before));
            $path = $created[0] ?? $this->newestZipPath();

            if (! $path) {
                $backup->update([
                    'status' => Backup::STATUS_FAILED,
                    'message' => 'Backup command succeeded but no zip was found on the backups disk.',
                    'finished_at' => now(),
                ]);

                return $backup->fresh(['creator']);
            }

            $size = Storage::disk(Backup::DISK)->size($path);

            $backup->update([
                'status' => Backup::STATUS_COMPLETED,
                'filename' => basename($path),
                'location' => $path,
                'size_bytes' => $size,
                'message' => $output !== '' ? mb_substr($output, 0, 2000) : 'Backup completed.',
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $backup->update([
                'status' => Backup::STATUS_FAILED,
                'message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }

        return $backup->fresh(['creator']);
    }

    /**
     * @return list<string>
     */
    public function listZipPaths(): array
    {
        $disk = Storage::disk(Backup::DISK);
        if (! $disk->exists('/')) {
            return [];
        }

        return collect($disk->allFiles())
            ->filter(fn (string $path) => str_ends_with(strtolower($path), '.zip'))
            ->sort()
            ->values()
            ->all();
    }

    public function newestZipPath(): ?string
    {
        $files = $this->listZipPaths();
        if ($files === []) {
            return null;
        }

        $disk = Storage::disk(Backup::DISK);

        return collect($files)
            ->sortByDesc(fn (string $path) => $disk->lastModified($path))
            ->first();
    }
}
