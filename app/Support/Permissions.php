<?php

namespace App\Support;

/**
 * Spatie permission names aligned with Vault/Project/… policy abilities.
 */
final class Permissions
{
    public const VAULT_VIEW = 'vault.view';

    public const VAULT_REFRESH_FX = 'vault.refresh-fx';

    public const VAULT_OVERRIDE_FX = 'vault.override-fx';

    public const VAULT_AUDIT = 'vault.audit';

    public const VAULT_BACKUPS = 'vault.backups';

    public const VAULT_IMPORTS = 'vault.imports';

    public const VAULT_EXPORTS = 'vault.exports';

    public const VAULT_PAYROLL = 'vault.payroll';

    public const VAULT_RETENTION = 'vault.retention';

    public const PROJECTS_VIEW_ANY = 'projects.viewAny';

    public const PROJECTS_CREATE = 'projects.create';

    public const PROJECTS_UPDATE = 'projects.update';

    public const PROJECTS_DELETE = 'projects.delete';

    public const TOWERS_VIEW_ANY = 'towers.viewAny';

    public const TOWERS_CREATE = 'towers.create';

    public const TOWERS_UPDATE = 'towers.update';

    public const TOWERS_DELETE = 'towers.delete';

    public const FLOORS_VIEW_ANY = 'floors.viewAny';

    public const FLOORS_CREATE = 'floors.create';

    public const FLOORS_UPDATE = 'floors.update';

    public const FLOORS_DELETE = 'floors.delete';

    public const WORKERS_VIEW_ANY = 'workers.viewAny';

    public const WORKERS_CREATE = 'workers.create';

    public const WORKERS_UPDATE = 'workers.update';

    public const WORKERS_DELETE = 'workers.delete';

    public const ATTENDANCE_VIEW_ANY = 'attendance.viewAny';

    public const ATTENDANCE_MANAGE = 'attendance.manage';

    public const PAYOUTS_VIEW_ANY = 'payouts.viewAny';

    public const PAYOUTS_CREATE = 'payouts.create';

    public const PAYOUTS_APPROVE = 'payouts.approve';

    public const PAYOUTS_REJECT = 'payouts.reject';

    public const PAYOUTS_RECONCILE = 'payouts.reconcile';

    public const PENALTIES_VIEW_ANY = 'penalties.viewAny';

    public const PENALTIES_CREATE = 'penalties.create';

    public const PENALTIES_WAIVE = 'penalties.waive';

    public const PENALTIES_LINK = 'penalties.link';

    public const DOCUMENTS_VIEW_ANY = 'documents.viewAny';

    public const DOCUMENTS_CREATE = 'documents.create';

    public const DOCUMENTS_DELETE = 'documents.delete';

    /** @var list<string> */
    public const ALL = [
        self::VAULT_VIEW,
        self::VAULT_REFRESH_FX,
        self::VAULT_OVERRIDE_FX,
        self::VAULT_AUDIT,
        self::VAULT_BACKUPS,
        self::VAULT_IMPORTS,
        self::VAULT_EXPORTS,
        self::VAULT_PAYROLL,
        self::VAULT_RETENTION,
        self::PROJECTS_VIEW_ANY,
        self::PROJECTS_CREATE,
        self::PROJECTS_UPDATE,
        self::PROJECTS_DELETE,
        self::TOWERS_VIEW_ANY,
        self::TOWERS_CREATE,
        self::TOWERS_UPDATE,
        self::TOWERS_DELETE,
        self::FLOORS_VIEW_ANY,
        self::FLOORS_CREATE,
        self::FLOORS_UPDATE,
        self::FLOORS_DELETE,
        self::WORKERS_VIEW_ANY,
        self::WORKERS_CREATE,
        self::WORKERS_UPDATE,
        self::WORKERS_DELETE,
        self::ATTENDANCE_VIEW_ANY,
        self::ATTENDANCE_MANAGE,
        self::PAYOUTS_VIEW_ANY,
        self::PAYOUTS_CREATE,
        self::PAYOUTS_APPROVE,
        self::PAYOUTS_REJECT,
        self::PAYOUTS_RECONCILE,
        self::PENALTIES_VIEW_ANY,
        self::PENALTIES_CREATE,
        self::PENALTIES_WAIVE,
        self::PENALTIES_LINK,
        self::DOCUMENTS_VIEW_ANY,
        self::DOCUMENTS_CREATE,
        self::DOCUMENTS_DELETE,
    ];

    /**
     * Role → permission matrix (mirrors existing policies).
     * Super Admin receives every permission via seeder.
     *
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        return [
            Roles::ACCOUNTANT => [
                self::VAULT_VIEW,
                self::VAULT_REFRESH_FX,
                self::VAULT_OVERRIDE_FX,
                self::VAULT_AUDIT,
                self::VAULT_BACKUPS,
                self::VAULT_IMPORTS,
                self::VAULT_EXPORTS,
                self::VAULT_PAYROLL,
                self::VAULT_RETENTION,
                self::PROJECTS_VIEW_ANY,
                self::TOWERS_VIEW_ANY,
                self::FLOORS_VIEW_ANY,
                self::WORKERS_VIEW_ANY,
                self::ATTENDANCE_VIEW_ANY,
                self::PAYOUTS_VIEW_ANY,
                self::PAYOUTS_CREATE,
                self::PAYOUTS_APPROVE,
                self::PAYOUTS_REJECT,
                self::PAYOUTS_RECONCILE,
                self::PENALTIES_VIEW_ANY,
                self::PENALTIES_CREATE,
                self::PENALTIES_WAIVE,
                self::PENALTIES_LINK,
                self::DOCUMENTS_VIEW_ANY,
                self::DOCUMENTS_CREATE,
                self::DOCUMENTS_DELETE,
            ],
            Roles::SITE_ENGINEER => [
                self::VAULT_IMPORTS,
                self::VAULT_EXPORTS,
                self::VAULT_PAYROLL,
                self::PROJECTS_VIEW_ANY,
                self::PROJECTS_CREATE,
                self::PROJECTS_UPDATE,
                self::TOWERS_VIEW_ANY,
                self::TOWERS_CREATE,
                self::TOWERS_UPDATE,
                self::TOWERS_DELETE,
                self::FLOORS_VIEW_ANY,
                self::FLOORS_CREATE,
                self::FLOORS_UPDATE,
                self::FLOORS_DELETE,
                self::WORKERS_VIEW_ANY,
                self::WORKERS_CREATE,
                self::WORKERS_UPDATE,
                self::WORKERS_DELETE,
                self::ATTENDANCE_VIEW_ANY,
                self::ATTENDANCE_MANAGE,
                self::PAYOUTS_VIEW_ANY,
                self::PENALTIES_VIEW_ANY,
                self::PENALTIES_CREATE,
                self::DOCUMENTS_VIEW_ANY,
                self::DOCUMENTS_CREATE,
                self::DOCUMENTS_DELETE,
            ],
            Roles::WORKER => [
                self::PROJECTS_VIEW_ANY,
                self::ATTENDANCE_VIEW_ANY,
                self::PAYOUTS_VIEW_ANY,
                self::DOCUMENTS_VIEW_ANY,
            ],
        ];
    }
}
