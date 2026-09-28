<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Document;
use App\Models\EmployeeAdvance;
use App\Models\Expense;
use App\Models\Floor;
use App\Models\Payout;
use App\Models\Penalty;
use App\Models\ProductionRecord;
use App\Models\Project;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Tower;
use App\Models\User;
use App\Models\Vault;
use App\Models\Worker;
use Illuminate\Support\Facades\Gate;

/**
 * Shared Inertia auth.can / auth.nav maps — hide unauthorized UI; server still 403s.
 */
final class UserAbilities
{
    /**
     * @return array<string, bool>
     */
    public static function canMap(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $gate = Gate::forUser($user);

        return [
            'vault.view' => $gate->allows('viewDashboard', Vault::class),
            'vault.ledger' => $gate->allows('viewLedger', Vault::class),
            'vault.settlement' => $gate->allows('viewSettlement', Vault::class),
            'vault.settlementManage' => $gate->allows('manageSettlement', Vault::class),
            'vault.refreshFx' => $gate->allows('refreshFx', Vault::class),
            'vault.overrideFx' => $gate->allows('overrideFx', Vault::class),
            'vault.audit' => $gate->allows('viewAuditLog', Vault::class),
            'vault.backups' => $gate->allows('manageBackups', Vault::class),
            'vault.imports' => $gate->allows('manageImports', Vault::class),
            'vault.exports' => $gate->allows('manageExports', Vault::class),
            'vault.payroll' => $gate->allows('viewPayroll', Vault::class),
            'vault.retention' => $gate->allows('viewRetention', Vault::class),
            'vault.retentionManage' => $gate->allows('manageRetention', Vault::class),
            'projects.viewAny' => $gate->allows('viewAny', Project::class),
            'projects.create' => $gate->allows('create', Project::class),
            'projects.update' => $gate->allows('update', new Project),
            'projects.delete' => $gate->allows('delete', new Project),
            'projects.viewFinancials' => $gate->allows('viewFinancials', new Project),
            'projects.recordReceipt' => $gate->allows('recordReceipt', new Project),
            'towers.viewAny' => $gate->allows('viewAny', Tower::class),
            'towers.create' => $gate->allows('create', Tower::class),
            'towers.update' => $gate->allows('update', new Tower),
            'towers.delete' => $gate->allows('delete', new Tower),
            'floors.viewAny' => $gate->allows('viewAny', Floor::class),
            'floors.create' => $gate->allows('create', Floor::class),
            'floors.update' => $gate->allows('update', new Floor),
            'floors.delete' => $gate->allows('delete', new Floor),
            'workers.viewAny' => $gate->allows('viewAny', Worker::class),
            'workers.create' => $gate->allows('create', Worker::class),
            'workers.update' => $gate->allows('update', new Worker),
            'workers.delete' => $gate->allows('delete', new Worker),
            'attendance.viewAny' => $gate->allows('viewAny', Attendance::class),
            'attendance.manage' => $gate->allows('manage', Attendance::class),
            'payouts.viewAny' => $gate->allows('viewAny', Payout::class),
            'payouts.create' => $gate->allows('create', Payout::class),
            'payouts.approve' => $gate->allows('approve', new Payout),
            'payouts.reject' => $gate->allows('reject', new Payout),
            'payouts.reconcile' => $gate->allows('reconcile', new Payout),
            'penalties.viewAny' => $gate->allows('viewAny', Penalty::class),
            'penalties.create' => $gate->allows('create', Penalty::class),
            'penalties.waive' => $gate->allows('waive', new Penalty),
            'penalties.apply' => $gate->allows('apply', new Penalty),
            'penalties.link' => $gate->allows('link', new Penalty),
            'advances.viewAny' => $gate->allows('viewAny', EmployeeAdvance::class),
            'advances.create' => $gate->allows('create', EmployeeAdvance::class),
            'advances.update' => $gate->allows('update', new EmployeeAdvance),
            'advances.repay' => $gate->allows('repay', new EmployeeAdvance),
            'advances.cancel' => $gate->allows('cancel', new EmployeeAdvance),
            'productions.viewAny' => $gate->allows('viewAny', ProductionRecord::class),
            'productions.create' => $gate->allows('create', ProductionRecord::class),
            'productions.update' => $gate->allows('update', new ProductionRecord),
            'documents.viewAny' => $gate->allows('viewAny', Document::class),
            'documents.create' => $gate->allows('create', Document::class),
            'documents.delete' => $gate->allows('delete', new Document),
            'expenses.viewAny' => $gate->allows('viewAny', Expense::class),
            'expenses.create' => $gate->allows('create', Expense::class),
            'expenses.update' => $gate->allows('update', new Expense),
            'expenses.approve' => $gate->allows('approve', new Expense),
            'expenses.reject' => $gate->allows('reject', new Expense),
            'users.viewAny' => $gate->allows('viewAny', User::class),
            'users.create' => $gate->allows('create', User::class),
            'users.update' => $gate->allows('update', new User),
            'users.disable' => $gate->allows('disable', new User),
            'users.resetPassword' => $gate->allows('resetPassword', new User),
            'users.changeRole' => $gate->allows('changeRole', new User),
            'stock.viewAny' => $gate->allows('viewAny', StockItem::class),
            'stock.manageItems' => $gate->allows('create', StockItem::class),
            'stock.stockIn' => $gate->allows('stockIn', StockMovement::class),
            'stock.stockOut' => $gate->allows('stockOut', StockMovement::class),
            'stock.manageSuppliers' => $gate->allows('create', Supplier::class),
        ];
    }

    /**
     * Nav keys the current user may see (hide, don't tease).
     *
     * @param  array<string, bool>  $can
     * @return list<string>
     */
    public static function navKeys(array $can): array
    {
        $map = [
            'dashboard' => true,
            'vault' => $can['vault.view'] ?? false,
            'payroll' => $can['vault.payroll'] ?? false,
            'projects' => $can['projects.viewAny'] ?? false,
            'workers' => $can['workers.viewAny'] ?? false,
            'stock' => $can['stock.viewAny'] ?? false,
            'settlements' => $can['vault.settlement'] ?? false,
            'payouts' => $can['payouts.viewAny'] ?? false,
            'expenses' => $can['expenses.viewAny'] ?? false,
            'penalties' => $can['penalties.viewAny'] ?? false,
            'advances' => $can['advances.viewAny'] ?? false,
            'productions' => $can['productions.viewAny'] ?? false,
            'docs' => $can['documents.viewAny'] ?? false,
            'imports' => $can['vault.imports'] ?? false,
            'exports' => $can['vault.exports'] ?? false,
            'backups' => $can['vault.backups'] ?? false,
            'audit' => $can['vault.audit'] ?? false,
            'insurance' => ($can['vault.retention'] ?? false) || ($can['vault.retentionManage'] ?? false),
            'users' => $can['users.viewAny'] ?? false,
        ];

        return array_values(array_keys(array_filter($map)));
    }
}
