<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vault;
use App\Support\Roles;

class VaultPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT, Roles::BOSS_CONTRACTOR]);
    }

    public function view(User $user, Vault $vault): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT, Roles::BOSS_CONTRACTOR]);
    }

    public function viewDashboard(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT, Roles::BOSS_CONTRACTOR]);
    }

    /**
     * Phase 12 — business transaction ledger (Boss / Accountant / Super Admin).
     * Stock Manager is intentionally excluded.
     */
    public function viewLedger(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT, Roles::BOSS_CONTRACTOR]);
    }

    /**
     * Phase 13 — Monthly Financial Settlement (Boss view; Accountant / Super Admin operate).
     * Stock Manager is intentionally excluded.
     */
    public function viewSettlement(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT, Roles::BOSS_CONTRACTOR]);
    }

    public function manageSettlement(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }

    public function refreshFx(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }

    public function overrideFx(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }

    public function viewAuditLog(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }

    public function manageBackups(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }

    public function manageImports(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT, Roles::BOSS_CONTRACTOR]);
    }

    public function manageExports(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT, Roles::BOSS_CONTRACTOR]);
    }

    public function viewPayroll(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT, Roles::BOSS_CONTRACTOR]);
    }

    /**
     * Boss may view insurance holds; Accountant/Admin operate release + settings.
     */
    public function viewRetention(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT, Roles::BOSS_CONTRACTOR]);
    }

    public function manageRetention(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT]);
    }
}
