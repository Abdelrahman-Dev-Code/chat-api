<?php

namespace Tests\Unit;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessageEventsTest extends TestCase
{
    #[Test]
    public function events_broadcast_on_the_private_chat_channel_with_named_events(): void
    {
        $message = $this->message();

        foreach ([
            'message.sent' => new MessageSent($message),
            'message.updated' => new MessageUpdated($message),
            'message.deleted' => new MessageDeleted($message),
        ] as $name => $event) {
            $channels = $event->broadcastOn();

            $this->assertCount(1, $channels);
            $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
            $this->assertSame('private-chat', $channels[0]->name);
            $this->assertSame($name, $event->broadcastAs());
        }
    }

    #[Test]
    public function sent_and_updated_events_carry_the_full_message(): void
    {
        $message = $this->message();

        foreach ([new MessageSent($message), new MessageUpdated($message)] as $event) {
            $payload = $event->broadcastWith();

            $this->assertSame(7, $payload['id']);
            $this->assertSame('Hello', $payload['content']);
            $this->assertSame('Sara', $payload['user']['name']);
        }
    }

    #[Test]
    public function the_payload_is_a_snapshot_taken_when_the_event_is_created(): void
    {
        $message = $this->message();
        $event = new MessageSent($message);

        $message->content = 'Edited later';

        $this->assertSame('Hello', $event->broadcastWith()['content']);
    }

    #[Test]
    public function the_payload_never_exposes_the_authors_email(): void
    {
        $message = $this->message();
        $message->user->email = 'sara@example.com';

        $this->assertArrayNotHasKey('email', (new MessageSent($message))->broadcastWith()['user']);
    }

    #[Test]
    public function deleted_event_only_carries_the_message_id(): void
    {
        $this->assertSame(['id' => 7], (new MessageDeleted($this->message()))->broadcastWith());
    }

    private function message(): Message
    {
        $author = (new User)->forceFill(['id' => 3, 'name' => 'Sara', 'avatar' => null]);

        return (new Message)
            ->forceFill(['id' => 7, 'user_id' => 3, 'content' => 'Hello', 'created_at' => now(), 'updated_at' => now()])
            ->setRelation('user', $author);
    }
}
