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

    /** @var list<string> */
    public const ALL = [
        self::PAYOUT_APPROVED,
        self::PAYOUT_REJECTED,
        self::ALLOCATION_CHANGED,
        self::FX_RATE_OVERRIDDEN,
        self::VAULT_DEPOSIT,
        self::USER_CREATED,
        self::USER_UPDATED,
        self::USER_ROLE_CHANGED,
        self::USER_DISABLED,
        self::USER_ENABLED,
        self::USER_PASSWORD_RESET,
        self::PROJECT_RECEIPT,
        self::EXPENSE_APPROVED,
        self::EXPENSE_REJECTED,
    ];

    /** @var array<string, string> */
    public const LABELS = [
        self::PAYOUT_APPROVED => 'Payout approved',
        self::PAYOUT_REJECTED => 'Payout rejected',
        self::ALLOCATION_CHANGED => 'Allocation changed',
        self::FX_RATE_OVERRIDDEN => 'FX rate overridden',
        self::VAULT_DEPOSIT => 'Vault deposit',
        self::USER_CREATED => 'User created',
        self::USER_UPDATED => 'User updated',
        self::USER_ROLE_CHANGED => 'User role changed',
        self::USER_DISABLED => 'User disabled',
        self::USER_ENABLED => 'User enabled',
        self::USER_PASSWORD_RESET => 'User password reset',
        self::PROJECT_RECEIPT => 'Project money received',
        self::EXPENSE_APPROVED => 'Expense approved',
        self::EXPENSE_REJECTED => 'Expense rejected',
    ];
}
