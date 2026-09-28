<?php

namespace App\Policies;

use App\Models\Penalty;
use App\Models\User;
use App\Support\Roles;

class PenaltyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            Roles::SUPER_ADMIN,
            Roles::ACCOUNTANT,
            Roles::BOSS_CONTRACTOR,
        ]);
    }

    public function view(User $user, Penalty $penalty): bool
    {
        return $user->hasAnyRole([
            Roles::SUPER_ADMIN,
            Roles::ACCOUNTANT,
            Roles::BOSS_CONTRACTOR,
        ]);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT, Roles::BOSS_CONTRACTOR]);
    }

    public function update(User $user, Penalty $penalty): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }

    public function waive(User $user, Penalty $penalty): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }

    public function link(User $user, Penalty $penalty): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }
}
