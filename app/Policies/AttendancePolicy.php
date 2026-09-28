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
            Roles::BOSS_CONTRACTOR,
        ]);
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $user->hasAnyRole([
            Roles::SUPER_ADMIN,
            Roles::ACCOUNTANT,
            Roles::BOSS_CONTRACTOR,
        ]);
    }

    public function manage(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::BOSS_CONTRACTOR]);
    }
}
