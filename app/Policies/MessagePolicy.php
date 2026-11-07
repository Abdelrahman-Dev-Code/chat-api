<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

/**
 * Users may only edit or delete the messages they wrote.
 */
class MessagePolicy
{
    public function update(User $user, Message $message): bool
    {
        return $message->isAuthoredBy($user);
    }

    public function delete(User $user, Message $message): bool
    {
        return $message->isAuthoredBy($user);
    }
}
