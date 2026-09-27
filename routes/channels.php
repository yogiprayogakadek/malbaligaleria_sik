<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('validators.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id
        && (($user->role === 'validator' && $user->division === 'TR') || $user->role === 'admin');
});
