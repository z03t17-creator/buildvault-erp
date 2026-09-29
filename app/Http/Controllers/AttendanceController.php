<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Floor;
use App\Models\Project;
use App\Models\Worker;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Attendance::class);

        $date = $request->string('date')->toString() ?: now()->toDateString();
        $projectId = $request->integer('project_id') ?: null;

        $workersQuery = Worker::query()
            ->where('labor_kind', Worker::LABOR_KIND_WORKER)
            ->with('project:id,name')
            ->orderBy('name');

        if ($projectId) {
            $workersQuery->where('project_id', $projectId);
        }

        $workers = $workersQuery->get();

        $attendances = Attendance::query()
            ->with(['penalty:id,type,amount_usd,amount_iqd,status', 'enteredBy:id,name'])
            ->whereDate('date', $date)
            ->whereIn('worker_id', $workers->pluck('id'))
            ->get()
            ->keyBy('worker_id');

        $grid = $workers->map(function (Worker $worker) use ($attendances) {
            return [
                'worker' => $worker,
                'attendance' => $attendances->get($worker->id),
            ];
        });

        return Inertia::render('Attendance/Matrix', [
            'date' => $date,
            'projectId' => $projectId,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'floors' => Floor::query()->with('tower:id,name,project_id')->orderBy('name')->get(),
            'grid' => $grid,
            'statuses' => Attendance::STATUSES,
            'shiftStart' => (string) config('attendance.shift_start', '08:00'),
            'lateForfeitMinutes' => Attendance::LATE_FORFEIT_MINUTES,
            'canManage' => $request->user()?->can('manage', Attendance::class) ?? false,
        ]);
    }

    public function checkIn(Request $request): RedirectResponse
    {
        $this->authorize('manage', Attendance::class);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'check_in' => ['required', 'date_format:H:i'],
            'shift_start' => ['nullable', 'date_format:H:i'],
            'floor_id' => ['nullable', 'integer', 'exists:floors,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'worker_ids' => ['required', 'array', 'min:1'],
            'worker_ids.*' => ['integer', 'exists:workers,id'],
        ]);

        $errors = [];
        Worker::query()
            ->whereIn('id', $data['worker_ids'])
            ->get()
            ->each(function (Worker $worker) use ($data, $request, &$errors) {
                try {
                    $this->attendance->record($worker, $data['date'], [
                        'floor_id' => $data['floor_id'] ?? null,
                        'project_id' => $data['project_id'] ?? $worker->project_id,
                        'check_in' => $data['check_in'],
                        'shift_start' => $data['shift_start'] ?? null,
                        'entered_by' => $request->user()?->id,
                    ], $request->user());
                } catch (InvalidArgumentException $e) {
                    $errors[] = $worker->name.': '.$e->getMessage();
                }
            });

        if ($errors !== []) {
            return back()->withErrors(['check_in' => implode(' ', $errors)]);
        }

        return back()->with('success', __('Check-in recorded.'));
    }

    public function checkOut(Request $request): RedirectResponse
    {
        $this->authorize('manage', Attendance::class);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'check_out' => ['required', 'date_format:H:i'],
            'worker_ids' => ['required', 'array', 'min:1'],
            'worker_ids.*' => ['integer', 'exists:workers,id'],
        ]);

        Worker::query()
            ->whereIn('id', $data['worker_ids'])
            ->get()
            ->each(function (Worker $worker) use ($data, $request) {
                $this->attendance->record($worker, $data['date'], [
                    'check_out' => $data['check_out'],
                    'entered_by' => $request->user()?->id,
                ], $request->user());
            });

        return back()->with('success', __('Check-out recorded.'));
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $this->authorize('manage', Attendance::class);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'worker_id' => ['required', 'integer', 'exists:workers,id'],
            'status' => ['required', Rule::in(Attendance::STATUSES)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ]);

        $worker = Worker::query()->findOrFail($data['worker_id']);

        try {
            $attrs = [
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'project_id' => $data['project_id'] ?? $worker->project_id,
                'entered_by' => $request->user()?->id,
            ];
            if (in_array($data['status'], [
                Attendance::STATUS_LEAVE_PAID,
                Attendance::STATUS_LEAVE_SICK,
                Attendance::STATUS_ABSENT_UNEXCUSED,
            ], true)) {
                $attrs['check_in'] = null;
                $attrs['check_out'] = null;
            }

            $this->attendance->record($worker, $data['date'], $attrs, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', __('Attendance updated.'));
    }

    public function markAbsences(Request $request): RedirectResponse
    {
        $this->authorize('manage', Attendance::class);

        $request->validate(['date' => ['required', 'date']]);

        $marked = $this->attendance->markAbsentIfNoCheckIn(
            $request->string('date')->toString(),
            null,
            Carbon::now(),
            $request->user(),
        );

        return back()->with('success', __(':count worker(s) marked absent.', ['count' => $marked->count()]));
    }
}
