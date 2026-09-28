<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FloorController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\PayrollDashboardController;
use App\Http\Controllers\PenaltyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RetentionHoldController;
use App\Http\Controllers\TowerController;
use App\Http\Controllers\VaultDashboardController;
use App\Http\Controllers\WorkerController;
use App\Models\Attendance;
use App\Models\Document;
use App\Models\Payout;
use App\Models\Penalty;
use App\Models\Project;
use App\Models\Vault;
use App\Models\Worker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Vault / money dashboards
    Route::middleware('can:viewDashboard,'.Vault::class)->group(function () {
        Route::get('/dashboards/vault', [VaultDashboardController::class, 'show'])->name('dashboards.vault');
    });
    Route::post('/dashboards/vault/refresh-fx', [VaultDashboardController::class, 'refreshFx'])
        ->middleware('can:refreshFx,'.Vault::class)
        ->name('dashboards.vault.refresh-fx');
    Route::post('/dashboards/vault/override-fx', [VaultDashboardController::class, 'overrideFx'])
        ->middleware('can:overrideFx,'.Vault::class)
        ->name('dashboards.vault.override-fx');
    Route::get('/dashboards/payroll', [PayrollDashboardController::class, 'show'])
        ->middleware('can:viewPayroll,'.Vault::class)
        ->name('dashboards.payroll');

    Route::get('/audit', [AuditLogController::class, 'index'])
        ->middleware('can:viewAuditLog,'.Vault::class)
        ->name('audit.index');

    Route::middleware('can:viewAny,'.Document::class)->group(function () {
        Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::get('/documents/{document}/file', [DocumentController::class, 'file'])->name('documents.file');
    });
    Route::post('/documents', [DocumentController::class, 'store'])
        ->middleware('can:create,'.Document::class)
        ->name('documents.store');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])
        ->name('documents.destroy');

    Route::middleware('can:manageImports,'.Vault::class)->group(function () {
        Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
        Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
        Route::get('/imports/templates/{type}', [ImportController::class, 'downloadTemplate'])
            ->name('imports.templates.download');
        Route::get('/imports/{import}', [ImportController::class, 'show'])->name('imports.show');
        Route::post('/imports/{import}/rollback', [ImportController::class, 'rollback'])
            ->name('imports.rollback');
    });

    Route::middleware('can:manageExports,'.Vault::class)->group(function () {
        Route::get('/exports', [ExportController::class, 'index'])->name('exports.index');
        Route::get('/exports/projects/{project}/excel', [ExportController::class, 'projectExcel'])
            ->name('exports.project');
        Route::get('/exports/workers/{worker}/pdf', [ExportController::class, 'workerPdf'])
            ->name('exports.worker');
        Route::get('/exports/payouts/{payout}/voucher', [ExportController::class, 'payoutVoucher'])
            ->name('exports.voucher');
    });

    Route::middleware('can:manageBackups,'.Vault::class)->group(function () {
        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [BackupController::class, 'store'])->name('backups.store');
        Route::get('/backups/{backup}/download', [BackupController::class, 'download'])
            ->name('backups.download');
    });

    Route::middleware('can:viewAny,'.Project::class)->group(function () {
        Route::resource('projects', ProjectController::class);
        Route::resource('projects.towers', TowerController::class)->shallow();
        Route::resource('towers.floors', FloorController::class)->shallow();
    });

    Route::middleware('can:viewAny,'.Worker::class)->group(function () {
        Route::resource('workers', WorkerController::class);
    });

    Route::middleware('can:viewAny,'.Attendance::class)->group(function () {
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    });
    Route::middleware('can:manage,'.Attendance::class)->group(function () {
        Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
        Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');
        Route::post('/attendance/mark-absences', [AttendanceController::class, 'markAbsences'])->name('attendance.mark-absences');
    });

    Route::get('/payouts', [PayoutController::class, 'index'])
        ->middleware('can:viewAny,'.Payout::class)
        ->name('payouts.index');
    Route::middleware('can:create,'.Payout::class)->group(function () {
        Route::get('/payouts/create', [PayoutController::class, 'create'])->name('payouts.create');
        Route::post('/payouts', [PayoutController::class, 'store'])->name('payouts.store');
    });
    Route::get('/payouts/{payout}', [PayoutController::class, 'show'])
        ->middleware('can:viewAny,'.Payout::class)
        ->name('payouts.show');
    Route::post('/payouts/{payout}/approve', [PayoutController::class, 'approve'])->name('payouts.approve');
    Route::post('/payouts/{payout}/reject', [PayoutController::class, 'reject'])->name('payouts.reject');
    Route::post('/payouts/{payout}/reconcile', [PayoutController::class, 'reconcile'])->name('payouts.reconcile');

    Route::get('/penalties', [PenaltyController::class, 'index'])
        ->middleware('can:viewAny,'.Penalty::class)
        ->name('penalties.index');
    Route::middleware('can:create,'.Penalty::class)->group(function () {
        Route::get('/penalties/create', [PenaltyController::class, 'create'])->name('penalties.create');
        Route::post('/penalties', [PenaltyController::class, 'store'])->name('penalties.store');
    });
    Route::get('/penalties/{penalty}', [PenaltyController::class, 'show'])
        ->middleware('can:viewAny,'.Penalty::class)
        ->name('penalties.show');
    Route::post('/penalties/{penalty}/waive', [PenaltyController::class, 'waive'])->name('penalties.waive');
    Route::post('/penalties/{penalty}/link', [PenaltyController::class, 'link'])->name('penalties.link');

    Route::middleware('can:manageRetention,'.Vault::class)->group(function () {
        Route::get('/retention-holds', [RetentionHoldController::class, 'index'])->name('retention-holds.index');
        Route::put('/retention-holds/settings', [RetentionHoldController::class, 'updateSettings'])
            ->name('retention-holds.settings');
        Route::post('/retention-holds/{retentionHold}/release', [RetentionHoldController::class, 'release'])
            ->name('retention-holds.release');
    });
});

require __DIR__.'/auth.php';
