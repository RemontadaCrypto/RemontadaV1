<?php

namespace App\Policies;

use App\Models\Offer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OfferPolicy
{
    use HandlesAuthorization;

    public function owner(User $user, Offer $offer): bool
    {
        return $user['id'] === $offer['user_id'];
    }
}
