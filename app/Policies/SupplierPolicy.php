<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;
use App\Support\Permissions;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::STOCK_VIEW_ANY);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can(Permissions::STOCK_VIEW_ANY);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::STOCK_MANAGE_SUPPLIERS);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can(Permissions::STOCK_MANAGE_SUPPLIERS);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can(Permissions::STOCK_MANAGE_SUPPLIERS);
    }
}
