<?php

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;
use App\Support\Permissions;

class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::STOCK_VIEW_ANY);
    }

    public function view(User $user, StockMovement $movement): bool
    {
        return $user->can(Permissions::STOCK_VIEW_ANY);
    }

    public function stockIn(User $user): bool
    {
        return $user->can(Permissions::STOCK_STOCK_IN);
    }

    public function stockOut(User $user): bool
    {
        return $user->can(Permissions::STOCK_STOCK_OUT);
    }
}
