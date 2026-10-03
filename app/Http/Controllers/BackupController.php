<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Models\Vault;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BackupController extends Controller
{
    public function __construct(
        private readonly BackupService $backups,
    ) {}

    public function index(): Response
    {
        $this->authorize('manageBackups', Vault::class);

        $backups = Backup::query()
            ->with('creator:id,name')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return Inertia::render('Backups/Index', [
            'types' => Backup::TYPES,
            'schedule' => [
                'command' => 'php artisan backup:run-logged',
                'cron' => '* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1',
                'direct' => '0 2 * * * cd '.base_path().' && php artisan backup:run-logged >> /dev/null 2>&1',
                'note' => 'Daily at 02:00 via schedule:run (or call backup:run-logged directly).',
            ],
            'overview' => [
                'count' => $backups->count(),
                'completed' => $backups->where('status', Backup::STATUS_COMPLETED)->count(),
                'failed' => $backups->where('status', Backup::STATUS_FAILED)->count(),
                'running' => $backups->whereIn('status', [
                    Backup::STATUS_PENDING,
                    Backup::STATUS_RUNNING,
                ])->count(),
            ],
            'backups' => $backups->map(fn (Backup $b) => [
                'id' => $b->id,
                'type' => $b->type,
                'filename' => $b->filename,
                'location' => $b->location,
                'size_bytes' => $b->size_bytes,
                'status' => $b->status,
                'message' => $b->message,
                'created_by' => $b->creator?->name,
                'started_at' => $b->started_at?->toIso8601String(),
                'finished_at' => $b->finished_at?->toIso8601String(),
                'created_at' => $b->created_at?->toIso8601String(),
                'downloadable' => $b->isDownloadable(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manageBackups', Vault::class);

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(Backup::TYPES)],
        ]);

        try {
            $backup = $this->backups->run($data['type'], $request->user()?->id);
        } catch (Throwable $e) {
            return redirect()
                ->route('backups.index')
                ->with('error', $e->getMessage());
        }

        $flash = $backup->status === Backup::STATUS_COMPLETED
            ? "Backup #{$backup->id} completed ({$backup->filename})."
            : "Backup #{$backup->id} failed: ".($backup->message ?: 'unknown error');

        return redirect()
            ->route('backups.index')
            ->with($backup->status === Backup::STATUS_COMPLETED ? 'success' : 'error', $flash);
    }

    public function download(Backup $backup): StreamedResponse
    {
        $this->authorize('manageBackups', Vault::class);

        abort_unless($backup->isDownloadable(), 404);

        return Storage::disk($backup->disk)->download(
            $backup->location,
            $backup->filename ?: basename($backup->location),
        );
    }
}
