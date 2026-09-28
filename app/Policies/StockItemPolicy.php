<?php

namespace App\Policies;

use App\Models\StockItem;
use App\Models\User;
use App\Support\Permissions;

class StockItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::STOCK_VIEW_ANY);
    }

    public function view(User $user, StockItem $item): bool
    {
        return $user->can(Permissions::STOCK_VIEW_ANY);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::STOCK_MANAGE_ITEMS);
    }

    public function update(User $user, StockItem $item): bool
    {
        return $user->can(Permissions::STOCK_MANAGE_ITEMS);
    }

    public function delete(User $user, StockItem $item): bool
    {
        return $user->can(Permissions::STOCK_MANAGE_ITEMS);
    }
}
