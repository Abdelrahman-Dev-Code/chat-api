<?php

namespace Tests\Unit;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Models\Message;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessageServiceTest extends TestCase
{
    use RefreshDatabase;

    private MessageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        $this->service = new MessageService;
    }

    #[Test]
    public function it_paginates_the_latest_messages_ten_per_page(): void
    {
        $old = Message::factory()->create(['created_at' => now()->subDay()]);
        Message::factory()->count(10)->create();

        $page = $this->service->paginate();

        $this->assertSame(MessageService::PER_PAGE, $page->perPage());
        $this->assertSame(11, $page->total());
        $this->assertNotContains($old->id, $page->pluck('id'));
        $this->assertTrue($page->first()->relationLoaded('user'));
    }

    #[Test]
    public function chat_pagination_avoids_a_total_count_query(): void
    {
        Message::factory()->count(MessageService::PER_PAGE + 1)->create();

        $page = $this->service->paginateForChat();

        $this->assertInstanceOf(PaginatorContract::class, $page);
        $this->assertSame(MessageService::PER_PAGE, $page->perPage());
        $this->assertTrue($page->hasMorePages());
        $this->assertTrue($page->first()->relationLoaded('user'));
    }

    #[Test]
    public function it_filters_messages_by_authors(): void
    {
        [$sara, $omar, $lina] = User::factory()->count(3)->create();
        Message::factory()->for($sara)->count(2)->create();
        Message::factory()->for($omar)->create();
        Message::factory()->for($lina)->count(3)->create();

        $page = $this->service->paginate([$sara->id, $omar->id]);

        $this->assertSame(3, $page->total());
        $this->assertEqualsCanonicalizing([$sara->id, $omar->id], $page->pluck('user_id')->unique()->values()->all());
    }

    #[Test]
    public function sending_a_message_stores_and_broadcasts_it(): void
    {
        $author = User::factory()->create();

        $message = $this->service->send($author, 'Hello');

        $this->assertDatabaseHas('messages', ['id' => $message->id, 'user_id' => $author->id, 'content' => 'Hello']);
        Event::assertDispatched(MessageSent::class, fn (MessageSent $event) => $event->payload['id'] === $message->id);
    }

    #[Test]
    public function chat_sending_persists_the_message_before_deferred_broadcasting(): void
    {
        $author = User::factory()->create();

        $message = $this->service->sendFromChat($author, 'Sent from chat');

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'user_id' => $author->id,
            'content' => 'Sent from chat',
        ]);
        $this->assertTrue($message->relationLoaded('user'));
    }

    #[Test]
    public function updating_a_message_saves_and_broadcasts_it(): void
    {
        $message = Message::factory()->create(['content' => 'Before']);

        $this->service->update($message, 'After');

        $this->assertSame('After', $message->fresh()->content);
        Event::assertDispatched(MessageUpdated::class, fn (MessageUpdated $event) => $event->payload['content'] === 'After');
    }

    #[Test]
    public function deleting_a_message_soft_deletes_and_broadcasts_it(): void
    {
        $message = Message::factory()->create();

        $this->service->delete($message);

        $this->assertSoftDeleted($message);
        Event::assertDispatched(MessageDeleted::class);
    }
}
