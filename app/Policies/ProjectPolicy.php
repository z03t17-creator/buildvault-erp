<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Support\Roles;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            Roles::SUPER_ADMIN,
            Roles::ACCOUNTANT,
            Roles::BOSS_CONTRACTOR,
        ]);
    }

    public function view(User $user, Project $project): bool
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

    public function update(User $user, Project $project): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::BOSS_CONTRACTOR]);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasRole(Roles::SUPER_ADMIN);
    }
}
