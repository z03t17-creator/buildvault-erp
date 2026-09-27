<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Worker;
use App\Support\Roles;

class WorkerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            Roles::SUPER_ADMIN,
            Roles::ACCOUNTANT,
            Roles::SITE_ENGINEER,
        ]);
    }

    public function view(User $user, Worker $worker): bool
    {
        if ($user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT, Roles::SITE_ENGINEER])) {
            return true;
        }

        if ($user->hasRole(Roles::WORKER)) {
            return (int) $user->worker?->id === (int) $worker->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::SITE_ENGINEER]);
    }

    public function update(User $user, Worker $worker): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::SITE_ENGINEER]);
    }

    public function delete(User $user, Worker $worker): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::SITE_ENGINEER]);
    }
}
