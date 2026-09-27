<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attendance\BulkCheckInRequest;
use App\Http\Requests\Attendance\BulkCheckOutRequest;
use App\Models\Attendance;
use App\Models\Floor;
use App\Models\Project;
use App\Models\Worker;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    /**
     * Grid / matrix data for a date (and optional project filter).
     */
    public function index(Request $request): Response
    {
        $date = $request->string('date')->toString() ?: now()->toDateString();
        $projectId = $request->integer('project_id') ?: null;

        $workersQuery = Worker::query()->with('project:id,name')->orderBy('name');
        if ($projectId) {
            $workersQuery->where('project_id', $projectId);
        }
        $workers = $workersQuery->get();

        $attendances = Attendance::query()
            ->whereDate('date', $date)
            ->whereIn('worker_id', $workers->pluck('id'))
            ->get()
            ->keyBy('worker_id');

        $grid = $workers->map(function (Worker $worker) use ($attendances) {
            $row = $attendances->get($worker->id);

            return [
                'worker' => $worker,
                'attendance' => $row,
            ];
        });

        return Inertia::render('Attendance/Matrix', [
            'date' => $date,
            'projectId' => $projectId,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'floors' => Floor::query()->with('tower:id,name,project_id')->orderBy('name')->get(),
            'grid' => $grid,
            'statuses' => Attendance::STATUSES,
        ]);
    }

    public function checkIn(BulkCheckInRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Worker::query()
            ->whereIn('id', $data['worker_ids'])
            ->get()
            ->each(function (Worker $worker) use ($data) {
                $this->attendanceService->record($worker, $data['date'], [
                    'floor_id' => $data['floor_id'] ?? null,
                    'check_in' => $data['check_in'],
                ]);
            });

        return back()->with('success', 'Check-in recorded.');
    }

    public function checkOut(BulkCheckOutRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Worker::query()
            ->whereIn('id', $data['worker_ids'])
            ->get()
            ->each(function (Worker $worker) use ($data) {
                $this->attendanceService->record($worker, $data['date'], [
                    'check_out' => $data['check_out'],
                ]);
            });

        return back()->with('success', 'Check-out recorded.');
    }

    /**
     * Optional: mark absences for a date after cutoff (manual invoke).
     */
    public function markAbsences(Request $request): RedirectResponse
    {
        $request->validate([
            'date' => ['required', 'date'],
        ]);

        $marked = $this->attendanceService->markAbsentIfNoCheckIn(
            $request->string('date')->toString(),
            null,
            Carbon::now(),
        );

        return back()->with('success', $marked->count().' worker(s) marked absent.');
    }
}
