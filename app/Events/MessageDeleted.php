<?php

namespace App\Events;

use App\Models\Message;

class MessageDeleted extends MessageEvent
{
    public function broadcastAs(): string
    {
        return 'message.deleted';
    }

    protected function payloadFor(Message $message): array
    {
        return ['id' => $message->id];
    }
}
