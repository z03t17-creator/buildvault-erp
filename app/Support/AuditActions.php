<?php

namespace App\Support;

/**
 * Stable event keys stored on activity_log.event / description for the audit UI.
 */
final class AuditActions
{
    public const LOG_NAME = 'audit';

    public const PAYOUT_APPROVED = 'payout.approved';

    public const PAYOUT_REJECTED = 'payout.rejected';

    public const ALLOCATION_CHANGED = 'allocation.changed';

    public const FX_RATE_OVERRIDDEN = 'fx.rate_overridden';

    public const VAULT_DEPOSIT = 'vault.deposit';

    public const USER_CREATED = 'user.created';

    public const USER_UPDATED = 'user.updated';

    public const USER_ROLE_CHANGED = 'user.role_changed';

    public const USER_DISABLED = 'user.disabled';

    public const USER_ENABLED = 'user.enabled';

    public const USER_PASSWORD_RESET = 'user.password_reset';

    public const PROJECT_RECEIPT = 'project.receipt';

    public const EXPENSE_APPROVED = 'expense.approved';

    public const EXPENSE_REJECTED = 'expense.rejected';

    public const EXPENSE_HELD = 'expense.held';

    public const PAYOUT_HELD = 'payout.held';

    /** Explicit USD↔IQD conversion (never silent blend). */
    public const FX_EXPLICIT_CONVERSION = 'fx.explicit_conversion';

    public const PERSON_CLASSIFIED = 'person.classified';

    public const VAULT_SOFT_DELETE_REBUILD = 'vault.soft_delete_rebuild';

    public const VAULT_MONEY_IN = 'vault.money_in';

    public const VAULT_MONEY_OUT = 'vault.money_out';

    public const VAULT_LEDGER_UPDATED = 'vault.ledger_updated';

    public const CLIENT_ADVANCE_RECORDED = 'client_advance.recorded';

    public const STAFF_STATEMENT_SAVED = 'staff_statement.saved';

    public const SPATIAL_CELL_UPDATED = 'spatial.cell_updated';

    public const SPATIAL_BULK_ASSIGNED = 'spatial.bulk_assigned';

    /** @var list<string> */
    public const ALL = [
        self::PAYOUT_APPROVED,
        self::PAYOUT_REJECTED,
        self::PAYOUT_HELD,
        self::ALLOCATION_CHANGED,
        self::FX_RATE_OVERRIDDEN,
        self::FX_EXPLICIT_CONVERSION,
        self::VAULT_DEPOSIT,
        self::VAULT_MONEY_IN,
        self::VAULT_MONEY_OUT,
        self::VAULT_LEDGER_UPDATED,
        self::USER_CREATED,
        self::USER_UPDATED,
        self::USER_ROLE_CHANGED,
        self::USER_DISABLED,
        self::USER_ENABLED,
        self::USER_PASSWORD_RESET,
        self::PROJECT_RECEIPT,
        self::EXPENSE_APPROVED,
        self::EXPENSE_REJECTED,
        self::EXPENSE_HELD,
        self::PERSON_CLASSIFIED,
        self::VAULT_SOFT_DELETE_REBUILD,
        self::CLIENT_ADVANCE_RECORDED,
        self::STAFF_STATEMENT_SAVED,
        self::SPATIAL_CELL_UPDATED,
        self::SPATIAL_BULK_ASSIGNED,
    ];

    /** @var array<string, string> */
    public const LABELS = [
        self::PAYOUT_APPROVED => 'Payout approved',
        self::PAYOUT_REJECTED => 'Payout rejected',
        self::PAYOUT_HELD => 'Payout held (ability to pay)',
        self::ALLOCATION_CHANGED => 'Allocation changed',
        self::FX_RATE_OVERRIDDEN => 'FX rate overridden',
        self::FX_EXPLICIT_CONVERSION => 'Explicit FX conversion',
        self::VAULT_DEPOSIT => 'Vault deposit',
        self::VAULT_MONEY_IN => 'Money In',
        self::VAULT_MONEY_OUT => 'Money Out',
        self::VAULT_LEDGER_UPDATED => 'Vault ledger updated',
        self::USER_CREATED => 'User created',
        self::USER_UPDATED => 'User updated',
        self::USER_ROLE_CHANGED => 'User role changed',
        self::USER_DISABLED => 'User disabled',
        self::USER_ENABLED => 'User enabled',
        self::USER_PASSWORD_RESET => 'User password reset',
        self::PROJECT_RECEIPT => 'Project money received',
        self::EXPENSE_APPROVED => 'Expense approved',
        self::EXPENSE_REJECTED => 'Expense rejected',
        self::EXPENSE_HELD => 'Expense held (ability to pay)',
        self::PERSON_CLASSIFIED => 'Person classified',
        self::VAULT_SOFT_DELETE_REBUILD => 'Vault balance rebuilt after soft delete',
        self::CLIENT_ADVANCE_RECORDED => 'Client advance recorded',
        self::STAFF_STATEMENT_SAVED => 'Staff statement saved',
        self::SPATIAL_CELL_UPDATED => 'Spatial cell updated',
        self::SPATIAL_BULK_ASSIGNED => 'Spatial bulk assignment',
    ];
}
