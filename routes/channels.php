<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('match.{matchId}', function ($user, $matchId) {
    return ['id' => $user->getKey(), 'name' => $user->name];
});
