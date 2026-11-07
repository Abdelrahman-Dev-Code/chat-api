<?php

use App\Events\MessageEvent;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
| The chat room. The auth endpoint (/api/broadcasting/auth) already requires a
| Sanctum token, so every authenticated user may join.
*/
Broadcast::channel(MessageEvent::CHANNEL, fn (User $user): bool => true);
