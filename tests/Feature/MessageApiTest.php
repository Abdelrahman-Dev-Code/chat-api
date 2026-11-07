<?php

namespace Tests\Feature;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessageApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        $this->user = User::factory()->create();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function protectedEndpoints(): array
    {
        return [
            'list' => ['GET', '/api/messages'],
            'send' => ['POST', '/api/messages'],
            'edit' => ['PATCH', '/api/messages/1'],
            'delete' => ['DELETE', '/api/messages/1'],
        ];
    }

    #[Test]
    #[DataProvider('protectedEndpoints')]
    public function chat_endpoints_require_authentication(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
    }

    #[Test]
    public function it_lists_the_latest_messages_ten_per_page(): void
    {
        Message::factory()->count(12)->create();
        Sanctum::actingAs($this->user);

        $this->getJson('/api/messages')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 12)
            ->assertJsonStructure(['data' => [['id', 'content', 'user' => ['id', 'name', 'avatar'], 'created_at', 'updated_at']], 'links', 'meta']);

        $this->getJson('/api/messages?page=2')->assertOk()->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_filters_messages_by_users(): void
    {
        $sara = User::factory()->create();
        Message::factory()->for($sara)->count(3)->create();
        Message::factory()->count(4)->create();
        Sanctum::actingAs($this->user);

        $this->getJson('/api/messages?user_ids[]='.$sara->id)
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.user.id', $sara->id);
    }

    #[Test]
    public function it_validates_the_list_filters(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/messages?user_ids[]=abc&page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_ids.0', 'page']);
    }

    #[Test]
    public function deleted_messages_are_not_listed(): void
    {
        Message::factory()->create()->delete();
        Sanctum::actingAs($this->user);

        $this->getJson('/api/messages')->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function a_user_can_send_a_message(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/messages', ['content' => '  Hello everyone  '])
            ->assertCreated()
            ->assertJsonPath('data.content', 'Hello everyone')
            ->assertJsonPath('data.user.id', $this->user->id)
            ->assertJsonMissingPath('data.user.email');

        $this->assertDatabaseHas('messages', ['user_id' => $this->user->id, 'content' => 'Hello everyone']);
        Event::assertDispatched(MessageSent::class);
    }

    #[Test]
    public function the_author_is_taken_from_the_token_not_the_request(): void
    {
        $other = User::factory()->create();
        Sanctum::actingAs($this->user);

        $this->postJson('/api/messages', ['content' => 'Hi', 'user_id' => $other->id])->assertCreated();

        $this->assertDatabaseMissing('messages', ['user_id' => $other->id]);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidContent(): array
    {
        return [
            'missing' => [null],
            'blank' => ['   '],
            'not a string' => [['array']],
            'too long' => [str_repeat('a', 2001)],
        ];
    }

    #[Test]
    #[DataProvider('invalidContent')]
    public function message_content_is_required_and_valid(mixed $content): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/messages', ['content' => $content])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');

        Event::assertNotDispatched(MessageSent::class);
    }

    #[Test]
    public function the_author_can_edit_their_message(): void
    {
        $message = Message::factory()->for($this->user)->create();
        Sanctum::actingAs($this->user);

        $this->patchJson("/api/messages/{$message->id}", ['content' => 'Edited'])
            ->assertOk()
            ->assertJsonPath('data.content', 'Edited');

        Event::assertDispatched(MessageUpdated::class);
    }

    #[Test]
    public function a_user_cannot_edit_someone_elses_message(): void
    {
        $message = Message::factory()->create(['content' => 'Original']);
        Sanctum::actingAs($this->user);

        $this->patchJson("/api/messages/{$message->id}", ['content' => 'Hacked'])->assertForbidden();

        $this->assertSame('Original', $message->fresh()->content);
        Event::assertNotDispatched(MessageUpdated::class);
    }

    #[Test]
    public function the_author_can_delete_their_message(): void
    {
        $message = Message::factory()->for($this->user)->create();
        Sanctum::actingAs($this->user);

        $this->deleteJson("/api/messages/{$message->id}")->assertNoContent();

        $this->assertSoftDeleted($message);
        Event::assertDispatched(MessageDeleted::class);
    }

    #[Test]
    public function a_user_cannot_delete_someone_elses_message(): void
    {
        $message = Message::factory()->create();
        Sanctum::actingAs($this->user);

        $this->deleteJson("/api/messages/{$message->id}")->assertForbidden();

        $this->assertNotSoftDeleted($message);
    }

    #[Test]
    public function a_deleted_message_can_no_longer_be_edited(): void
    {
        $message = Message::factory()->for($this->user)->create();
        $message->delete();
        Sanctum::actingAs($this->user);

        $this->patchJson("/api/messages/{$message->id}", ['content' => 'Edited'])->assertNotFound();
    }
}
