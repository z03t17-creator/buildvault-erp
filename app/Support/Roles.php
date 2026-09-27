<?php

namespace App\Support;

final class Roles
{
    public const SUPER_ADMIN = 'Super Admin';

    public const ACCOUNTANT = 'Accountant';

    public const SITE_ENGINEER = 'Site Engineer';

    public const WORKER = 'Worker';

    /** @var list<string> */
    public const ALL = [
        self::SUPER_ADMIN,
        self::ACCOUNTANT,
        self::SITE_ENGINEER,
        self::WORKER,
    ];
}
