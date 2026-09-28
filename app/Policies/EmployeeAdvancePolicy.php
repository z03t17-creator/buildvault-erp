<?php

namespace App\Policies;

use App\Models\EmployeeAdvance;
use App\Models\User;
use App\Support\Roles;

/**
 * Advances (سلفە): Accountant + Super Admin manage; Boss view; Stock Manager none.
 */
class EmployeeAdvancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            Roles::SUPER_ADMIN,
            Roles::ACCOUNTANT,
            Roles::BOSS_CONTRACTOR,
        ]);
    }

    public function view(User $user, EmployeeAdvance $advance): bool
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

    public function update(User $user, EmployeeAdvance $advance): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }

    public function repay(User $user, EmployeeAdvance $advance): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }

    public function cancel(User $user, EmployeeAdvance $advance): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }
}
