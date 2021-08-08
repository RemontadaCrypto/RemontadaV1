<?php

use App\Models\Trade;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('trade.{ref}', function ($user, $ref) {
    if ($trade = Trade::query()->where('ref', $ref)->first())
        return ((int) $user->id === (int) $trade['buyer_id']) || ((int) $user->id === (int) $trade['seller_id']);
    return false;
});
