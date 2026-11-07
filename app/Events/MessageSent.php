<?php

namespace App\Events;

use App\Models\Message;

class MessageSent extends MessageEvent
{
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    protected function payloadFor(Message $message): array
    {
        return $this->messageSnapshot($message);
    }
}
