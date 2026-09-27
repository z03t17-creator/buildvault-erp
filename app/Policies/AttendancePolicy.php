<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;
use App\Support\Roles;

class AttendancePolicy
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

    public function view(User $user, Attendance $attendance): bool
    {
        if ($user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT, Roles::SITE_ENGINEER])) {
            return true;
        }

        if ($user->hasRole(Roles::WORKER)) {
            return $user->worker && (int) $attendance->worker_id === (int) $user->worker->id;
        }

        return false;
    }

    public function manage(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::SITE_ENGINEER]);
    }
}
