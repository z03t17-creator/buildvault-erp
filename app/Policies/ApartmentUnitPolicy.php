<?php

namespace App\Policies;

use App\Models\ApartmentUnit;
use App\Models\User;
use App\Support\Roles;

/**
 * Spatial progress grid: Accountant + Super Admin manage; Boss view; Stock Manager none.
 */
class ApartmentUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            Roles::SUPER_ADMIN,
            Roles::ACCOUNTANT,
            Roles::BOSS_CONTRACTOR,
        ]);
    }

    public function view(User $user, ApartmentUnit $unit): bool
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

    public function update(User $user, ApartmentUnit $unit): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }

    public function delete(User $user, ApartmentUnit $unit): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }

    public function bulkAssign(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }
}
