<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// House-wide channel: only members of the house may subscribe.
Broadcast::channel('private-house.{houseId}', function ($user, $houseId) {
    return (int) $user->house_id === (int) $houseId;
});

// User-only channel: only the same user may subscribe.
Broadcast::channel('private-user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// House Wall channel: separated from financial traffic.
Broadcast::channel('private-house-wall.{houseId}', function ($user, $houseId) {
    return (int) $user->house_id === (int) $houseId;
});
