<?php

namespace App\Policies;

use App\Models\Trade;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TradePolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function buyer(User $user, Trade $trade): bool
    {
        return $user['id'] === $trade['buyer_id'];
    }

    public function seller(User $user, Trade $trade): bool
    {
        return $user['id'] === $trade['seller_id'];
    }

    public function party(User $user, Trade $trade): bool
    {
        return $user['id'] === $trade['buyer_id'] || $user['id'] === $trade['seller_id'];
    }
}
