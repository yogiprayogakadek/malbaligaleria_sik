<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('validators.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id
        && $user->is_active
        && (($user->role === 'validator' && $user->division === 'TR') || $user->role === 'admin');
});
