<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\FloorController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\PenaltyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RetentionHoldController;
use App\Http\Controllers\TowerController;
use App\Http\Controllers\WorkerController;
use App\Services\RetentionHoldService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/dashboard', function (RetentionHoldService $holds) {
    return Inertia::render('Dashboard', [
        'maturedHolds' => $holds->maturedAwaitingRelease(),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('projects', ProjectController::class);
    Route::resource('projects.towers', TowerController::class)->shallow();
    Route::resource('towers.floors', FloorController::class)->shallow();
    Route::resource('workers', WorkerController::class);

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
    Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');
    Route::post('/attendance/mark-absences', [AttendanceController::class, 'markAbsences'])->name('attendance.mark-absences');

    Route::get('/payouts', [PayoutController::class, 'index'])->name('payouts.index');
    Route::get('/payouts/create', [PayoutController::class, 'create'])->name('payouts.create');
    Route::post('/payouts', [PayoutController::class, 'store'])->name('payouts.store');
    Route::get('/payouts/{payout}', [PayoutController::class, 'show'])->name('payouts.show');
    Route::post('/payouts/{payout}/approve', [PayoutController::class, 'approve'])->name('payouts.approve');
    Route::post('/payouts/{payout}/reject', [PayoutController::class, 'reject'])->name('payouts.reject');
    Route::post('/payouts/{payout}/reconcile', [PayoutController::class, 'reconcile'])->name('payouts.reconcile');

    Route::get('/penalties', [PenaltyController::class, 'index'])->name('penalties.index');
    Route::get('/penalties/create', [PenaltyController::class, 'create'])->name('penalties.create');
    Route::post('/penalties', [PenaltyController::class, 'store'])->name('penalties.store');
    Route::get('/penalties/{penalty}', [PenaltyController::class, 'show'])->name('penalties.show');
    Route::post('/penalties/{penalty}/waive', [PenaltyController::class, 'waive'])->name('penalties.waive');
    Route::post('/penalties/{penalty}/link', [PenaltyController::class, 'link'])->name('penalties.link');

    Route::get('/retention-holds', [RetentionHoldController::class, 'index'])->name('retention-holds.index');
    Route::post('/retention-holds/{retentionHold}/release', [RetentionHoldController::class, 'release'])
        ->name('retention-holds.release');
});

require __DIR__.'/auth.php';
