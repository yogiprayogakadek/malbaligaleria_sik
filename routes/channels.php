<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('validators.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id
        && $user->is_active
        && (($user->role === 'validator' && in_array($user->division, ['TR', 'MEP', 'FIN'], true)) || in_array($user->role, ['admin', 'secretary'], true));
});
