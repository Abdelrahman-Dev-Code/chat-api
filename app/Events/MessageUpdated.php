<?php

namespace App\Events;

use App\Models\Message;

class MessageUpdated extends MessageEvent
{
    public function broadcastAs(): string
    {
        return 'message.updated';
    }

    protected function payloadFor(Message $message): array
    {
        return $this->messageSnapshot($message);
    }
}
