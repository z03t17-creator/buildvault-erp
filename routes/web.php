<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\FloorController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\PenaltyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RetentionHoldController;
use App\Http\Controllers\TowerController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PayrollDashboardController;
use App\Http\Controllers\VaultDashboardController;
use App\Http\Controllers\WorkerController;
use App\Services\ExchangeRateService;
use App\Services\RetentionHoldService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/dashboard', function (RetentionHoldService $holds, ExchangeRateService $fx) {
    $rate = $fx->getUsdToIqd();

    $maturedHolds = $holds->maturedAwaitingRelease()->map(function ($hold) use ($rate) {
        $hold->setAttribute('amount_iqd', round((float) $hold->amount_usd * $rate, 0));

        return $hold;
    });

    return Inertia::render('Dashboard', [
        'maturedHolds' => $maturedHolds,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboards/vault', [VaultDashboardController::class, 'show'])->name('dashboards.vault');
    Route::post('/dashboards/vault/refresh-fx', [VaultDashboardController::class, 'refreshFx'])
        ->name('dashboards.vault.refresh-fx');
    Route::post('/dashboards/vault/override-fx', [VaultDashboardController::class, 'overrideFx'])
        ->name('dashboards.vault.override-fx');
    Route::get('/dashboards/payroll', [PayrollDashboardController::class, 'show'])->name('dashboards.payroll');

    Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}/file', [DocumentController::class, 'file'])->name('documents.file');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
    Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
    Route::get('/imports/templates/{type}', [ImportController::class, 'downloadTemplate'])
        ->name('imports.templates.download');
    Route::get('/imports/{import}', [ImportController::class, 'show'])->name('imports.show');
    Route::post('/imports/{import}/rollback', [ImportController::class, 'rollback'])
        ->name('imports.rollback');

    Route::get('/exports', [ExportController::class, 'index'])->name('exports.index');
    Route::get('/exports/projects/{project}/excel', [ExportController::class, 'projectExcel'])
        ->name('exports.project');
    Route::get('/exports/workers/{worker}/pdf', [ExportController::class, 'workerPdf'])
        ->name('exports.worker');
    Route::get('/exports/payouts/{payout}/voucher', [ExportController::class, 'payoutVoucher'])
        ->name('exports.voucher');

    Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('/backups', [BackupController::class, 'store'])->name('backups.store');
    Route::get('/backups/{backup}/download', [BackupController::class, 'download'])
        ->name('backups.download');

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
