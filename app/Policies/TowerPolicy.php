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
            Roles::SITE_ENGINEER,
        ]);
    }

    public function view(User $user, Tower $tower): bool
    {
        return $user->hasAnyRole([
            Roles::SUPER_ADMIN,
            Roles::ACCOUNTANT,
            Roles::SITE_ENGINEER,
        ]);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::SITE_ENGINEER]);
    }

    public function update(User $user, Tower $tower): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::SITE_ENGINEER]);
    }

    public function delete(User $user, Tower $tower): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::SITE_ENGINEER]);
    }
}
