<?php

namespace App\Events;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base class for every message event pushed to the chat channel over Reverb.
 *
 * The payload is captured when the event is created, not when the queued
 * broadcast runs, so clients always receive the message as it was at that
 * moment (e.g. a quick edit cannot overtake the original "sent" event).
 */
abstract class MessageEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * The private channel every authenticated chat client listens on.
     */
    public const string CHANNEL = 'chat';

    /**
     * @var array<string, mixed>
     */
    public readonly array $payload;

    public function __construct(Message $message)
    {
        $this->payload = $this->payloadFor($message);
    }

    /**
     * The event name clients listen for, e.g. "message.sent".
     */
    abstract public function broadcastAs(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function payloadFor(Message $message): array;

    /**
     * The message as plain data (nested resources included), safe to queue.
     *
     * @return array<string, mixed>
     */
    protected function messageSnapshot(Message $message): array
    {
        return json_decode(new MessageResource($message->loadMissing('user'))->toJson(), true);
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(self::CHANNEL)];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
