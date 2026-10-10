<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BusinessWipeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FloorController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MayorcaImportController;
use App\Http\Controllers\PenaltyController;
use App\Http\Controllers\ProductionRecordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RetentionHoldController;
use App\Http\Controllers\StockCategoryController;
use App\Http\Controllers\StockDashboardController;
use App\Http\Controllers\StockItemController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TowerController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SimpleVaultLineController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\VaultDashboardController;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Attendance;
use App\Models\Penalty;
use App\Models\ProductionRecord;
use App\Models\Project;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vault;
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

Route::post('/admin/mayorca-import', [MayorcaImportController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('admin.mayorca-import');

Route::post('/admin/business-wipe', [BusinessWipeController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('admin.business-wipe');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Vault / money dashboards + Phase 2 ledger CRUD
    Route::middleware('can:viewDashboard,'.Vault::class)->group(function () {
        Route::get('/dashboards/vault', [VaultDashboardController::class, 'show'])->name('dashboards.vault');
    });
    Route::middleware('can:viewLedger,'.Vault::class)->group(function () {
        // Legacy dual-currency ledger UI removed — simple vault home is the money surface.
        Route::redirect('/vault', '/dashboards/vault')->name('vault.index');
        Route::redirect('/vault/transactions', '/dashboards/vault')->name('vault.transactions');
        Route::get('/vault/job-pay', [SimpleVaultLineController::class, 'indexJobPay'])
            ->name('vault.job-pay.index');
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/{staff}', [StaffController::class, 'show'])
            ->whereNumber('staff')
            ->name('staff.show');
    });
    Route::middleware('can:manageLedger,'.Vault::class)->group(function () {
        Route::post('/vault/job-pay/{line}/confirm-hold', [SimpleVaultLineController::class, 'confirmJobPayHold'])
            ->whereNumber('line')
            ->name('vault.job-pay.confirm-hold');
        Route::redirect('/vault/transactions/create', '/dashboards/vault')
            ->name('vault.transactions.create');
        Route::post('/vault/transactions', fn () => redirect()->route('dashboards.vault'))
            ->name('vault.transactions.store');
        Route::get('/vault/transactions/{transaction}/edit', fn () => redirect()->route('dashboards.vault'))
            ->name('vault.transactions.edit');
        Route::put('/vault/transactions/{transaction}', fn () => redirect()->route('dashboards.vault'))
            ->name('vault.transactions.update');
        Route::delete('/vault/transactions/{transaction}', fn () => redirect()->route('dashboards.vault'))
            ->name('vault.transactions.destroy');

        // Simple vault money forms (vault_lines only)
        Route::get('/vault/lines/advance', [SimpleVaultLineController::class, 'createAdvance'])
            ->name('vault.lines.advance.create');
        Route::post('/vault/lines/advance', [SimpleVaultLineController::class, 'storeAdvance'])
            ->name('vault.lines.advance.store');
        // Project expenses are the only expense UI — vault form redirects there.
        Route::get('/vault/lines/expense', fn () => redirect()->route('expenses.create'))
            ->name('vault.lines.expense.create');
        Route::post('/vault/lines/expense', fn () => redirect()->route('expenses.create'))
            ->name('vault.lines.expense.store');
        Route::get('/vault/lines/staff-pay', [SimpleVaultLineController::class, 'createStaffPay'])
            ->name('vault.lines.staff-pay.create');
        Route::post('/vault/lines/staff-pay', [SimpleVaultLineController::class, 'storeStaffPay'])
            ->name('vault.lines.staff-pay.store');
        Route::get('/vault/lines/job-pay', [SimpleVaultLineController::class, 'createJobPay'])
            ->name('vault.lines.job-pay.create');
        Route::post('/vault/lines/job-pay', [SimpleVaultLineController::class, 'storeJobPay'])
            ->name('vault.lines.job-pay.store');
        Route::get('/vault/lines/unit-pay', [SimpleVaultLineController::class, 'createUnitPay'])
            ->name('vault.lines.unit-pay.create');
        Route::post('/vault/lines/unit-pay', [SimpleVaultLineController::class, 'storeUnitPay'])
            ->name('vault.lines.unit-pay.store');
        Route::get('/vault/lines/salary', [SimpleVaultLineController::class, 'createSalary'])
            ->name('vault.lines.salary.create');
        Route::post('/vault/lines/salary', [SimpleVaultLineController::class, 'storeSalary'])
            ->name('vault.lines.salary.store');

        Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    });
    Route::middleware('can:manageStaffPay,'.Vault::class)->group(function () {
        Route::get('/vault/lines/staff-pay/{line}/edit', [SimpleVaultLineController::class, 'editStaffPay'])
            ->whereNumber('line')
            ->name('vault.lines.staff-pay.edit');
        Route::put('/vault/lines/staff-pay/{line}', [SimpleVaultLineController::class, 'updateStaffPay'])
            ->whereNumber('line')
            ->name('vault.lines.staff-pay.update');
        Route::delete('/vault/lines/staff-pay/{line}', [SimpleVaultLineController::class, 'destroyStaffPay'])
            ->whereNumber('line')
            ->name('vault.lines.staff-pay.destroy');
        Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])
            ->whereNumber('staff')
            ->name('staff.edit');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])
            ->whereNumber('staff')
            ->name('staff.update');
        Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])
            ->whereNumber('staff')
            ->name('staff.destroy');
    });

    Route::post('/dashboards/vault/refresh-fx', [VaultDashboardController::class, 'refreshFx'])
        ->middleware('can:refreshFx,'.Vault::class)
        ->name('dashboards.vault.refresh-fx');
    Route::post('/dashboards/vault/override-fx', [VaultDashboardController::class, 'overrideFx'])
        ->middleware('can:overrideFx,'.Vault::class)
        ->name('dashboards.vault.override-fx');


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
        ->middleware('can:delete,document')
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

    // Phase 15 — professional reports suite (Boss/Accountant/Admin financial; Stock Manager stock-only)
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{type}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/{type}/export/{format}', [ReportController::class, 'export'])->name('reports.export');
    Route::redirect('/exports', '/reports')->name('exports.index');

    Route::middleware('can:manageExports,'.Vault::class)->group(function () {
        Route::get('/exports/projects/{project}/excel', [ExportController::class, 'projectExcel'])
            ->name('exports.project');
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


    // Old client-advances UI removed — one money-in form is vault advance (سلفە).
    Route::redirect('/client-advances', '/vault/lines/advance')->name('client-advances.index');
    Route::redirect('/client-advances/create', '/vault/lines/advance')->name('client-advances.create');
    Route::get('/client-advances/{clientAdvance}', fn () => redirect()->route('vault.lines.advance.create'))
        ->name('client-advances.show');
    Route::post('/client-advances', fn () => redirect()->route('vault.lines.advance.create'))
        ->name('client-advances.store');
    Route::delete('/client-advances/{clientAdvance}', fn () => redirect()->route('vault.lines.advance.create'))
        ->name('client-advances.destroy');

    // Phase 10 — Stock / inventory (attendance UI/data removed from product surface)
    Route::middleware('can:viewAny,'.StockItem::class)->group(function () {
        Route::get('/stock', StockDashboardController::class)->name('stock.dashboard');
        Route::get('/stock/items', [StockItemController::class, 'index'])->name('stock.items.index');
        Route::get('/stock/movements', [StockMovementController::class, 'index'])->name('stock.movements.index');
        Route::get('/stock/consumption', [StockMovementController::class, 'consumption'])
            ->name('stock.consumption');
        Route::get('/stock/suppliers', [SupplierController::class, 'index'])->name('stock.suppliers.index');
        Route::get('/stock/categories', [StockCategoryController::class, 'index'])->name('stock.categories.index');
    });
    Route::middleware('can:create,'.StockItem::class)->group(function () {
        Route::get('/stock/items/create', [StockItemController::class, 'create'])->name('stock.items.create');
        Route::post('/stock/items', [StockItemController::class, 'store'])->name('stock.items.store');
        Route::get('/stock/categories/create', [StockCategoryController::class, 'create'])->name('stock.categories.create');
        Route::post('/stock/categories', [StockCategoryController::class, 'store'])->name('stock.categories.store');
        Route::get('/stock/categories/{stockCategory}/edit', [StockCategoryController::class, 'edit'])->name('stock.categories.edit');
        Route::put('/stock/categories/{stockCategory}', [StockCategoryController::class, 'update'])->name('stock.categories.update');
        Route::delete('/stock/categories/{stockCategory}', [StockCategoryController::class, 'destroy'])->name('stock.categories.destroy');
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


    Route::get('/expenses', [ExpenseController::class, 'index'])
        ->middleware('can:viewAny,'.Expense::class)
        ->name('expenses.index');
    Route::middleware('can:create,'.Expense::class)->group(function () {
        Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    });
    Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])
        ->middleware('can:view,expense')
        ->name('expenses.show');
    Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])
        ->middleware('can:update,expense')
        ->name('expenses.edit');
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])
        ->middleware('can:update,expense')
        ->name('expenses.update');
    Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])
        ->middleware('can:approve,expense')
        ->name('expenses.approve');
    Route::post('/expenses/{expense}/hold', [ExpenseController::class, 'hold'])
        ->middleware('can:hold,expense')
        ->name('expenses.hold');
    Route::post('/expenses/{expense}/reject', [ExpenseController::class, 'reject'])
        ->middleware('can:reject,expense')
        ->name('expenses.reject');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])
        ->middleware('can:delete,expense')
        ->name('expenses.destroy');

    Route::get('/penalties', [PenaltyController::class, 'index'])
        ->middleware('can:viewAny,'.Penalty::class)
        ->name('penalties.index');
    Route::middleware('can:create,'.Penalty::class)->group(function () {
        Route::get('/penalties/create', [PenaltyController::class, 'create'])->name('penalties.create');
        Route::post('/penalties', [PenaltyController::class, 'store'])->name('penalties.store');
    });
    Route::get('/penalties/{penalty}', [PenaltyController::class, 'show'])
        ->middleware('can:view,penalty')
        ->name('penalties.show');
    Route::post('/penalties/{penalty}/waive', [PenaltyController::class, 'waive'])
        ->middleware('can:waive,penalty')
        ->name('penalties.waive');
    Route::post('/penalties/{penalty}/apply', [PenaltyController::class, 'apply'])
        ->middleware('can:apply,penalty')
        ->name('penalties.apply');

    // Old staff/employee advances UI removed — company→staff pay is vault job-pay (پارەی ستاف).
    Route::redirect('/advances', '/vault/lines/job-pay')->name('advances.index');
    Route::redirect('/advances/create', '/vault/lines/job-pay')->name('advances.create');
    Route::post('/advances', fn () => redirect()->route('vault.lines.job-pay.create'))
        ->name('advances.store');
    Route::get('/advances/{advance}', fn () => redirect()->route('vault.lines.job-pay.create'))
        ->name('advances.show');
    Route::post('/advances/{advance}/repay', fn () => redirect()->route('vault.lines.job-pay.create'))
        ->name('advances.repay');
    Route::post('/advances/{advance}/cancel', fn () => redirect()->route('vault.lines.job-pay.create'))
        ->name('advances.cancel');

    Route::get('/productions', [ProductionRecordController::class, 'index'])
        ->middleware('can:viewAny,'.ProductionRecord::class)
        ->name('productions.index');
    Route::middleware('can:create,'.ProductionRecord::class)->group(function () {
        Route::get('/productions/create', [ProductionRecordController::class, 'create'])->name('productions.create');
        Route::post('/productions', [ProductionRecordController::class, 'store'])->name('productions.store');
    });
    Route::get('/productions/{production}', [ProductionRecordController::class, 'show'])
        ->middleware('can:view,production')
        ->name('productions.show');
    Route::get('/productions/{production}/edit', [ProductionRecordController::class, 'edit'])
        ->middleware('can:update,production')
        ->name('productions.edit');
    Route::put('/productions/{production}', [ProductionRecordController::class, 'update'])
        ->middleware('can:update,production')
        ->name('productions.update');


    // Phase 4 — Worker attendance (Stock Manager writes; Accountant/Boss view)
    Route::get('/attendance', [AttendanceController::class, 'index'])
        ->middleware('can:viewAny,'.Attendance::class)
        ->name('attendance.index');
    Route::middleware('can:manage,'.Attendance::class)->group(function () {
        Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])
            ->name('attendance.check-in');
        Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut'])
            ->name('attendance.check-out');
        Route::post('/attendance/status', [AttendanceController::class, 'updateStatus'])
            ->name('attendance.status');
        Route::post('/attendance/mark-absences', [AttendanceController::class, 'markAbsences'])
            ->name('attendance.mark-absences');
    });

    Route::middleware('can:viewAny,'.User::class)->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])
            ->middleware('can:create,'.User::class)
            ->name('users.create');
        Route::post('/users', [UserController::class, 'store'])
            ->middleware('can:create,'.User::class)
            ->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])
            ->middleware('can:view,user')
            ->name('users.show');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])
            ->middleware('can:update,user')
            ->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])
            ->middleware('can:update,user')
            ->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->middleware('can:delete,user')
            ->name('users.destroy');
        Route::post('/users/{user}/disable', [UserController::class, 'disable'])
            ->middleware('can:disable,user')
            ->name('users.disable');
        Route::post('/users/{user}/enable', [UserController::class, 'enable'])
            ->middleware('can:enable,user')
            ->name('users.enable');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])
            ->middleware('can:resetPassword,user')
            ->name('users.reset-password');
        Route::post('/users/{user}/change-role', [UserController::class, 'changeRole'])
            ->middleware('can:changeRole,user')
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
        Route::post('/retention-holds/client/{clientRetentionHold}/release', [RetentionHoldController::class, 'releaseClient'])
            ->name('retention-holds.client-release');
    });
});

require __DIR__.'/auth.php';
