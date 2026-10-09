<?php

namespace App\Policies;

use App\Models\Tower;
use App\Models\User;
use App\Support\Roles;

class TowerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            Roles::SUPER_ADMIN,
            Roles::ACCOUNTANT,
            Roles::BOSS_CONTRACTOR,
        ]);
    }

    public function view(User $user, Tower $tower): bool
    {
        return $user->hasAnyRole([
            Roles::SUPER_ADMIN,
            Roles::ACCOUNTANT,
            Roles::BOSS_CONTRACTOR,
        ]);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::BOSS_CONTRACTOR]);
    }

    public function update(User $user, Tower $tower): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::BOSS_CONTRACTOR]);
    }

    public function delete(User $user, Tower $tower): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::BOSS_CONTRACTOR]);
    }
}
