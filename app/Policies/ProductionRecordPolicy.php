<?php

namespace App\Policies;

use App\Models\ProductionRecord;
use App\Models\User;
use App\Support\Roles;

/**
 * Production (work tracking): Accountant + Super Admin manage; Boss view; Stock Manager none.
 */
class ProductionRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            Roles::SUPER_ADMIN,
            Roles::ACCOUNTANT,
            Roles::BOSS_CONTRACTOR,
        ]);
    }

    public function view(User $user, ProductionRecord $production): bool
    {
        return $user->hasAnyRole([
            Roles::SUPER_ADMIN,
            Roles::ACCOUNTANT,
            Roles::BOSS_CONTRACTOR,
        ]);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }

    public function update(User $user, ProductionRecord $production): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }

    public function delete(User $user, ProductionRecord $production): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }
}
