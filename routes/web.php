<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EmployeeAdvanceController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FloorController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MonthlySettlementController;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\PayrollDashboardController;
use App\Http\Controllers\PenaltyController;
use App\Http\Controllers\ProductionRecordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RetentionHoldController;
use App\Http\Controllers\StockDashboardController;
use App\Http\Controllers\StockItemController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TowerController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VaultDashboardController;
use App\Http\Controllers\VaultTransactionController;
use App\Http\Controllers\WorkerController;
use App\Models\Document;
use App\Models\EmployeeAdvance;
use App\Models\Expense;
use App\Models\Payout;
use App\Models\Penalty;
use App\Models\ProductionRecord;
use App\Models\Project;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
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
    Route::middleware('can:viewLedger,'.Vault::class)->group(function () {
        Route::get('/vault/transactions', [VaultTransactionController::class, 'index'])
            ->name('vault.transactions');
    });
    Route::middleware('can:viewSettlement,'.Vault::class)->group(function () {
        Route::get('/settlements', [MonthlySettlementController::class, 'index'])
            ->name('settlements.index');
        Route::get('/monthly-settlement', fn () => redirect()->route('settlements.index', request()->query()))
            ->name('settlements.alias');
    });
    Route::post('/settlements', [MonthlySettlementController::class, 'store'])
        ->middleware('can:manageSettlement,'.Vault::class)
        ->name('settlements.store');
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
        Route::post('/projects/{project}/receipts', [ProjectController::class, 'storeReceipt'])
            ->name('projects.receipts.store');
        Route::resource('projects.towers', TowerController::class)->shallow();
        Route::resource('towers.floors', FloorController::class)->shallow();
    });

    Route::middleware('can:viewAny,'.Worker::class)->group(function () {
        Route::resource('workers', WorkerController::class);
    });

    // Phase 10 — Stock / inventory (Attendance UI removed; payroll still uses attendance data)
    Route::middleware('can:viewAny,'.StockItem::class)->group(function () {
        Route::get('/stock', StockDashboardController::class)->name('stock.dashboard');
        Route::get('/stock/items', [StockItemController::class, 'index'])->name('stock.items.index');
        Route::get('/stock/movements', [StockMovementController::class, 'index'])->name('stock.movements.index');
        Route::get('/stock/suppliers', [SupplierController::class, 'index'])->name('stock.suppliers.index');
    });
    Route::middleware('can:create,'.StockItem::class)->group(function () {
        Route::get('/stock/items/create', [StockItemController::class, 'create'])->name('stock.items.create');
        Route::post('/stock/items', [StockItemController::class, 'store'])->name('stock.items.store');
    });
    Route::middleware('can:viewAny,'.StockItem::class)->group(function () {
        Route::get('/stock/items/{item}', [StockItemController::class, 'show'])->name('stock.items.show');
    });
    Route::middleware('can:create,'.StockItem::class)->group(function () {
        Route::get('/stock/items/{item}/edit', [StockItemController::class, 'edit'])->name('stock.items.edit');
        Route::put('/stock/items/{item}', [StockItemController::class, 'update'])->name('stock.items.update');
        Route::delete('/stock/items/{item}', [StockItemController::class, 'destroy'])->name('stock.items.destroy');
    });
    Route::middleware('can:stockIn,'.StockMovement::class)->group(function () {
        Route::get('/stock/in/create', [StockMovementController::class, 'createIn'])->name('stock.in.create');
        Route::post('/stock/in', [StockMovementController::class, 'storeIn'])->name('stock.in.store');
    });
    Route::middleware('can:stockOut,'.StockMovement::class)->group(function () {
        Route::get('/stock/out/create', [StockMovementController::class, 'createOut'])->name('stock.out.create');
        Route::post('/stock/out', [StockMovementController::class, 'storeOut'])->name('stock.out.store');
    });
    Route::middleware('can:create,'.Supplier::class)->group(function () {
        Route::get('/stock/suppliers/create', [SupplierController::class, 'create'])->name('stock.suppliers.create');
        Route::post('/stock/suppliers', [SupplierController::class, 'store'])->name('stock.suppliers.store');
        Route::get('/stock/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('stock.suppliers.edit');
        Route::put('/stock/suppliers/{supplier}', [SupplierController::class, 'update'])->name('stock.suppliers.update');
        Route::delete('/stock/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('stock.suppliers.destroy');
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

    Route::get('/expenses', [ExpenseController::class, 'index'])
        ->middleware('can:viewAny,'.Expense::class)
        ->name('expenses.index');
    Route::middleware('can:create,'.Expense::class)->group(function () {
        Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    });
    Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])
        ->middleware('can:viewAny,'.Expense::class)
        ->name('expenses.show');
    Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
    Route::post('/expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject');

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
    Route::post('/penalties/{penalty}/apply', [PenaltyController::class, 'apply'])->name('penalties.apply');
    Route::post('/penalties/{penalty}/link', [PenaltyController::class, 'link'])->name('penalties.link');

    Route::get('/advances', [EmployeeAdvanceController::class, 'index'])
        ->middleware('can:viewAny,'.EmployeeAdvance::class)
        ->name('advances.index');
    Route::middleware('can:create,'.EmployeeAdvance::class)->group(function () {
        Route::get('/advances/create', [EmployeeAdvanceController::class, 'create'])->name('advances.create');
        Route::post('/advances', [EmployeeAdvanceController::class, 'store'])->name('advances.store');
    });
    Route::get('/advances/{advance}', [EmployeeAdvanceController::class, 'show'])
        ->middleware('can:viewAny,'.EmployeeAdvance::class)
        ->name('advances.show');
    Route::post('/advances/{advance}/repay', [EmployeeAdvanceController::class, 'repay'])
        ->name('advances.repay');
    Route::post('/advances/{advance}/cancel', [EmployeeAdvanceController::class, 'cancel'])
        ->name('advances.cancel');

    Route::get('/productions', [ProductionRecordController::class, 'index'])
        ->middleware('can:viewAny,'.ProductionRecord::class)
        ->name('productions.index');
    Route::middleware('can:create,'.ProductionRecord::class)->group(function () {
        Route::get('/productions/create', [ProductionRecordController::class, 'create'])->name('productions.create');
        Route::post('/productions', [ProductionRecordController::class, 'store'])->name('productions.store');
    });
    Route::get('/productions/{production}', [ProductionRecordController::class, 'show'])
        ->middleware('can:viewAny,'.ProductionRecord::class)
        ->name('productions.show');
    Route::get('/productions/{production}/edit', [ProductionRecordController::class, 'edit'])->name('productions.edit');
    Route::put('/productions/{production}', [ProductionRecordController::class, 'update'])->name('productions.update');

    Route::middleware('can:viewAny,'.User::class)->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])
            ->middleware('can:create,'.User::class)
            ->name('users.create');
        Route::post('/users', [UserController::class, 'store'])
            ->middleware('can:create,'.User::class)
            ->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/disable', [UserController::class, 'disable'])->name('users.disable');
        Route::post('/users/{user}/enable', [UserController::class, 'enable'])->name('users.enable');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])
            ->name('users.reset-password');
        Route::post('/users/{user}/change-role', [UserController::class, 'changeRole'])
            ->name('users.change-role');
    });

    Route::get('/retention-holds', [RetentionHoldController::class, 'index'])
        ->middleware('can:viewRetention,'.Vault::class)
        ->name('retention-holds.index');
    Route::middleware('can:manageRetention,'.Vault::class)->group(function () {
        Route::put('/retention-holds/settings', [RetentionHoldController::class, 'updateSettings'])
            ->name('retention-holds.settings');
        Route::post('/retention-holds/{retentionHold}/release', [RetentionHoldController::class, 'release'])
            ->name('retention-holds.release');
    });
});

require __DIR__.'/auth.php';
