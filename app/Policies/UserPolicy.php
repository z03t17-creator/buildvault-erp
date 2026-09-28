<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Roles;

/**
 * User Management is Super Admin only (Phase 4).
 * Gate::before also grants Super Admin; non-admins receive 403.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Roles::SUPER_ADMIN);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasRole(Roles::SUPER_ADMIN);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Roles::SUPER_ADMIN);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasRole(Roles::SUPER_ADMIN);
    }

    public function delete(User $user, User $model): bool
    {
        // Soft-disable preferred; delete ability maps to deactivate.
        return $user->hasRole(Roles::SUPER_ADMIN);
    }

    public function disable(User $user, User $model): bool
    {
        return $user->hasRole(Roles::SUPER_ADMIN);
    }

    public function enable(User $user, User $model): bool
    {
        return $user->hasRole(Roles::SUPER_ADMIN);
    }

    public function resetPassword(User $user, User $model): bool
    {
        return $user->hasRole(Roles::SUPER_ADMIN);
    }

    public function changeRole(User $user, User $model): bool
    {
        return $user->hasRole(Roles::SUPER_ADMIN);
    }
}
