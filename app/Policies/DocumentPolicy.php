<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Support\Roles;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            Roles::SUPER_ADMIN,
            Roles::ACCOUNTANT,
            Roles::BOSS_CONTRACTOR,
        ]);
    }

    public function view(User $user, Document $document): bool
    {
        return $user->hasAnyRole([
            Roles::SUPER_ADMIN,
            Roles::ACCOUNTANT,
            Roles::BOSS_CONTRACTOR,
        ]);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::BOSS_CONTRACTOR, Roles::ACCOUNTANT]);
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::BOSS_CONTRACTOR, Roles::ACCOUNTANT]);
    }
}
