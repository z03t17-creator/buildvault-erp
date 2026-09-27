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
            Roles::SITE_ENGINEER,
            Roles::WORKER,
        ]);
    }

    public function view(User $user, Document $document): bool
    {
        if ($user->hasAnyRole([Roles::SUPER_ADMIN, Roles::ACCOUNTANT, Roles::SITE_ENGINEER])) {
            return true;
        }

        if ($user->hasRole(Roles::WORKER)) {
            return $user->worker && (int) $document->worker_id === (int) $user->worker->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::SITE_ENGINEER, Roles::ACCOUNTANT]);
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->hasAnyRole([Roles::SUPER_ADMIN, Roles::SITE_ENGINEER, Roles::ACCOUNTANT]);
    }
}
