<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;
use App\Support\Roles;

/**
 * Attendance: Stock Manager (+ Super Admin) write; Accountant/Boss view/list/filter.
 */
class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            Roles::SUPER_ADMIN,
            Roles::ACCOUNTANT,
            Roles::BOSS_CONTRACTOR,
            Roles::STOCK_MANAGER,
        ]);
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $this->viewAny($user);
    }

    public function manage(User $user): bool
    {
        return $user->hasAnyRole([
            Roles::SUPER_ADMIN,
            Roles::STOCK_MANAGER,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return $this->manage($user);
    }
}
