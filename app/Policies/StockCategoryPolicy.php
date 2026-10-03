<?php

namespace App\Policies;

use App\Models\StockCategory;
use App\Models\User;
use App\Support\Permissions;

class StockCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::STOCK_VIEW_ANY);
    }

    public function view(User $user, StockCategory $category): bool
    {
        return $user->can(Permissions::STOCK_VIEW_ANY);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::STOCK_MANAGE_ITEMS);
    }

    public function update(User $user, StockCategory $category): bool
    {
        return $user->can(Permissions::STOCK_MANAGE_ITEMS);
    }

    public function delete(User $user, StockCategory $category): bool
    {
        return $user->can(Permissions::STOCK_MANAGE_ITEMS);
    }
}
