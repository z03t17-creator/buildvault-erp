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
            Roles::SITE_ENGINEER,
            Roles::WORKER,
        ]);
    }

    public function view(User $user, Project $project): bool
    {
        if ($user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT, Roles::SITE_ENGINEER])) {
            return true;
        }

        if ($user->hasRole(Roles::WORKER)) {
            return (int) $user->worker?->project_id === (int) $project->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::SITE_ENGINEER]);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::SITE_ENGINEER]);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasRole(Roles::SUPER_ADMIN);
    }
}
