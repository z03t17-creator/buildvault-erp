<?php

namespace App\Support;

final class Roles
{
    public const SUPER_ADMIN = 'Super Admin';

    public const BOSS_CONTRACTOR = 'Boss / Contractor';

    public const ACCOUNTANT = 'Accountant';

    public const STOCK_MANAGER = 'Stock Manager';

    /** @var list<string> */
    public const ALL = [
        self::SUPER_ADMIN,
        self::BOSS_CONTRACTOR,
        self::ACCOUNTANT,
        self::STOCK_MANAGER,
    ];

    /** Legacy Spatie role names before Phase 2 rename (migration only). */
    public const LEGACY_SITE_ENGINEER = 'Site Engineer';

    public const LEGACY_WORKER = 'Worker';
}
