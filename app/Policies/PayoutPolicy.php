<?php

namespace App\Policies;

use App\Models\Payout;
use App\Models\User;
use App\Support\Roles;

class PayoutPolicy
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

    public function view(User $user, Payout $payout): bool
    {
        if ($user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT, Roles::SITE_ENGINEER])) {
            return true;
        }

        if ($user->hasRole(Roles::WORKER)) {
            return $user->worker && (int) $payout->worker_id === (int) $user->worker->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }

    public function approve(User $user, Payout $payout): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }

    public function reject(User $user, Payout $payout): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }

    public function reconcile(User $user, Payout $payout): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }
}
