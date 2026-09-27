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

    /** @var list<string> */
    public const ALL = [
        self::PAYOUT_APPROVED,
        self::PAYOUT_REJECTED,
        self::ALLOCATION_CHANGED,
        self::FX_RATE_OVERRIDDEN,
        self::VAULT_DEPOSIT,
    ];

    /** @var array<string, string> */
    public const LABELS = [
        self::PAYOUT_APPROVED => 'Payout approved',
        self::PAYOUT_REJECTED => 'Payout rejected',
        self::ALLOCATION_CHANGED => 'Allocation changed',
        self::FX_RATE_OVERRIDDEN => 'FX rate overridden',
        self::VAULT_DEPOSIT => 'Vault deposit',
    ];
}
