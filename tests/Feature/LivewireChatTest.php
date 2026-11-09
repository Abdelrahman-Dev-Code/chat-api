<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Livewire\Chat;
use App\Models\Message;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LivewireChatTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/chat')->assertRedirect('/login');
    }

    #[Test]
    public function the_login_page_offers_google_sign_in(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Continue with Google')
            ->assertSee(route('auth.google.web.redirect'));
    }

    #[Test]
    public function an_authenticated_user_sees_the_chat_transcript(): void
    {
        $message = Message::factory()->for($this->user)->create(['content' => 'Hello from the room']);

        $this->actingAs($this->user)
            ->get('/chat')
            ->assertOk()
            ->assertSee('Community chat')
            ->assertSee('Hello from the room')
            ->assertSee($this->user->name)
            ->assertSee('Shortcut: Ctrl+K or ⌘K')
            ->assertSee('wire:key="message-'.$message->id.'"', escape: false);
    }

    #[Test]
    public function a_user_can_send_a_trimmed_message(): void
    {
        Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->set('newMessage', '  Hello everyone  ')
            ->call('send')
            ->assertHasNoErrors()
            ->assertSet('newMessage', '')
            ->assertCount('messages', 1)
            ->assertDispatched('message-sent');

        $this->assertDatabaseHas('messages', [
            'user_id' => $this->user->id,
            'content' => 'Hello everyone',
        ]);
        Event::assertDispatched(MessageSent::class);
    }

    #[Test]
    public function message_content_is_required_and_limited(): void
    {
        $component = Livewire::actingAs($this->user)->test(Chat::class);

        $component->set('newMessage', '   ')->call('send')->assertHasErrors(['newMessage' => 'required']);
        $component->set('newMessage', str_repeat('a', 2001))->call('send')->assertHasErrors(['newMessage' => 'max']);

        $this->assertSame(0, Message::count());
    }

    #[Test]
    public function sending_is_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            RateLimiter::hit('chat-send:'.$this->user->id, 60);
        }

        Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->set('newMessage', 'Too fast')
            ->call('send')
            ->assertDispatched('message-send-failed')
            ->assertHasErrors(['newMessage' => 'You are sending messages too quickly. Please wait a moment.']);

        $this->assertSame(0, Message::count());
    }

    #[Test]
    public function the_first_page_contains_ten_messages_and_load_more_appends_older_ones(): void
    {
        Message::factory()->count(12)->create();

        $component = Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->assertCount('messages', 10)
            ->assertSet('hasMore', true);

        $component->call('loadMore')
            ->assertCount('messages', 12)
            ->assertSet('hasMore', false)
            ->assertSet('page', 2);

        // The oldest message ends up at the top of the transcript.
        $component->assertSet(
            'messages',
            fn (array $messages): bool => $messages[0]['id'] === Message::query()->oldest('id')->value('id'),
        );

        $component->call('loadMore')->assertCount('messages', 12);
    }

    #[Test]
    public function the_mine_filter_only_shows_own_messages(): void
    {
        Message::factory()->for($this->user)->count(2)->create();
        Message::factory()->count(3)->create();

        Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->set('filter', 'mine')
            ->assertCount('messages', 2)
            ->assertSet('messages', function (array $messages): bool {
                return collect($messages)->every(fn (array $message): bool => $message['user']['id'] === $this->user->id);
            });
    }

    #[Test]
    public function the_author_can_edit_their_message(): void
    {
        // An older created_at guarantees updated_at differs after the edit,
        // even when both writes happen within the same database second.
        $message = Message::factory()->for($this->user)->create([
            'content' => 'Before',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->call('startEditing', $message->id)
            ->assertSet('editingId', $message->id)
            ->assertSet('editContent', 'Before')
            ->set('editContent', 'After')
            ->call('saveEdit')
            ->assertHasNoErrors()
            ->assertSet('editingId', null)
            ->assertSet('messages', function (array $messages) use ($message): bool {
                $edited = collect($messages)->firstWhere('id', $message->id);

                return $edited['content'] === 'After' && $edited['edited'] === true;
            })
            ->assertDispatched('message-updated');

        $this->assertSame('After', $message->fresh()->content);
    }

    #[Test]
    public function the_author_can_delete_their_message(): void
    {
        $message = Message::factory()->for($this->user)->create();

        Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->call('delete', $message->id)
            ->assertCount('messages', 0)
            ->assertDispatched('message-deleted');

        $this->assertSoftDeleted($message);
    }

    #[Test]
    public function users_cannot_edit_or_delete_someone_elses_message(): void
    {
        $message = Message::factory()->create(['content' => 'Not yours']);

        $component = Livewire::actingAs($this->user)->test(Chat::class);

        $component->call('startEditing', $message->id)
            ->assertSet('editingId', null)
            ->assertHasErrors(['chat' => 'You can only edit or delete your own messages.']);

        $component->call('delete', $message->id)
            ->assertHasErrors(['chat' => 'You can only edit or delete your own messages.']);

        $this->assertSame('Not yours', $message->fresh()->content);
        $this->assertNotSoftDeleted($message);
    }

    #[Test]
    public function edits_and_deletes_of_missing_messages_return_not_found(): void
    {
        // Livewire's exception handler turns an aborted model lookup into a
        // 404 response instead of letting the exception escape the component.
        // Each failed request is independent: a 404 response no longer carries
        // a snapshot, so the two calls need separate component instances.
        Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->call('startEditing', 999_999)
            ->assertStatus(404);

        Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->call('delete', 999_999)
            ->assertStatus(404);
    }

    #[Test]
    public function real_time_events_update_the_transcript(): void
    {
        $message = Message::factory()->for($this->user)->create(['content' => 'Live']);
        $payload = (new MessageSent($message))->broadcastWith();

        $component = Livewire::actingAs($this->user)->test(Chat::class);

        $component->call('onMessageSent', $payload)
            ->assertCount('messages', 1)
            ->assertDispatched('message-received');

        $edited = [...$payload, 'content' => 'Live (edited)', 'edited' => true];

        $component->call('onMessageUpdated', $edited)
            ->assertSet('messages', fn (array $messages): bool => $messages[0]['content'] === 'Live (edited)');

        $component->call('onMessageDeleted', ['id' => $message->id])
            ->assertCount('messages', 0);
    }

    #[Test]
    public function real_time_messages_are_ignored_by_the_mine_filter(): void
    {
        $other = User::factory()->create();
        $message = Message::factory()->for($other)->create();
        $payload = (new MessageSent($message))->broadcastWith();

        Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->set('filter', 'mine')
            ->call('onMessageSent', $payload)
            ->assertCount('messages', 0);
    }

    /* ------------------------------------------------------------------ */
    /* Search filter tests */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function search_filters_messages_by_content(): void
    {
        Message::factory()->for($this->user)->create(['content' => 'unique searchable phrase']);
        Message::factory()->for($this->user)->create(['content' => 'other message']);

        $component = Livewire::actingAs($this->user)->test(Chat::class);
        $component->set('search', 'unique searchable')
            ->assertCount('messages', 1)
            ->assertSet('messages', fn (array $messages): bool => $messages[0]['content'] === 'unique searchable phrase');
    }

    #[Test]
    public function search_is_case_insensitive(): void
    {
        Message::factory()->for($this->user)->create(['content' => 'Hello World']);

        $component = Livewire::actingAs($this->user)->test(Chat::class);
        $component->set('search', 'hello world')
            ->assertCount('messages', 1);

        $component->set('search', 'HELLO WORLD')
            ->assertCount('messages', 1);
    }

    #[Test]
    public function search_with_no_matches_shows_empty_state(): void
    {
        Message::factory()->for($this->user)->create(['content' => 'Hello']);

        $component = Livewire::actingAs($this->user)->test(Chat::class);
        $component->set('search', 'zzznothing')
            ->assertCount('messages', 0);
    }

    #[Test]
    public function clear_filters_removes_search(): void
    {
        Message::factory()->for($this->user)->create(['content' => 'Hello']);

        $component = Livewire::actingAs($this->user)->test(Chat::class);
        $component->set('search', 'Hello')
            ->assertCount('messages', 1);

        $component->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('senderFilter', 'all')
            ->assertSet('datePreset', 'all')
            ->assertSet('dateFrom', '')
            ->assertSet('dateTo', '')
            ->assertSet('filter', 'all')
            ->assertCount('messages', 1);
    }

    /* ------------------------------------------------------------------ */
    /* Sender filter tests */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function sender_filter_shows_only_selected_senders_messages(): void
    {
        $other = User::factory()->create();
        Message::factory()->for($this->user)->create(['content' => 'Message from me']);
        Message::factory()->for($other)->create(['content' => 'Message from other']);

        $component = Livewire::actingAs($this->user)->test(Chat::class);
        $component->set('senderFilter', $other->id)
            ->assertCount('messages', 1)
            ->assertSet('messages', fn (array $messages): bool => $messages[0]['content'] === 'Message from other');
    }

    #[Test]
    public function sender_filter_includes_senders_in_dropdown(): void
    {
        $other = User::factory()->create();

        $component = Livewire::actingAs($this->user)->test(Chat::class);
        $component->assertSet('senders', fn (array $senders): bool => collect($senders)->contains('id', $other->id));
    }

    /* ------------------------------------------------------------------ */
    /* Date filter tests */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function today_date_preset_filters_messages(): void
    {
        $today = Message::factory()->for($this->user)->create(['content' => 'Today message']);
        Message::factory()->for($this->user)->create(['content' => 'Old message', 'created_at' => now()->subWeek()]);

        $component = Livewire::actingAs($this->user)->test(Chat::class);
        $component->set('datePreset', 'today')
            ->assertCount('messages', 1)
            ->assertSet('messages', fn (array $messages): bool => $messages[0]['id'] === $today->id);
    }

    #[Test]
    public function custom_date_range_filters_messages(): void
    {
        $start = now()->subDays(10)->startOfDay()->format('Y-m-d');
        $end = now()->subDays(5)->endOfDay()->format('Y-m-d');

        $inRange = Message::factory()->for($this->user)->create(['content' => 'In range', 'created_at' => now()->subDays(7)]);
        Message::factory()->for($this->user)->create(['content' => 'Too old', 'created_at' => now()->subDays(20)]);
        Message::factory()->for($this->user)->create(['content' => 'Too new', 'created_at' => now()->subDays(1)]);

        $component = Livewire::actingAs($this->user)->test(Chat::class);
        $component->set('datePreset', 'custom')
            ->set('dateFrom', $start)
            ->set('dateTo', $end)
            ->call('applyCustomDateRange')
            ->assertCount('messages', 1)
            ->assertSet('messages', fn (array $messages): bool => $messages[0]['id'] === $inRange->id);
    }

    #[Test]
    public function combining_search_sender_and_date_filters(): void
    {
        $other = User::factory()->create();
        $matching = Message::factory()->for($this->user)->create([
            'content' => 'Project update meeting',
            'created_at' => now(),
        ]);
        Message::factory()->for($other)->create(['content' => 'Project update meeting', 'created_at' => now()]);
        Message::factory()->for($this->user)->create(['content' => 'Old project note', 'created_at' => now()->subMonth()]);

        $component = Livewire::actingAs($this->user)->test(Chat::class);
        $component->set('search', 'project update')
            ->set('senderFilter', $this->user->id)
            ->set('datePreset', 'today')
            ->assertCount('messages', 1)
            ->assertSet('messages', fn (array $messages): bool => $messages[0]['id'] === $matching->id);
    }

    /* ------------------------------------------------------------------ */
    /* Sender presentation tests */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function messages_show_sender_name_and_avatar(): void
    {
        $message = Message::factory()->for($this->user)->create(['content' => 'Hello']);

        $this->actingAs($this->user)
            ->get('/chat')
            ->assertOk()
            ->assertSee($this->user->name);
    }

    #[Test]
    public function own_messages_show_you_label(): void
    {
        $message = Message::factory()->for($this->user)->create(['content' => 'Hello']);

        $this->actingAs($this->user)
            ->get('/chat')
            ->assertOk()
            ->assertSee('You');
    }

    /* ------------------------------------------------------------------ */
    /* Workspace sidebar + member directory */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function the_sidebar_shows_the_shared_room_and_a_public_member_directory(): void
    {
        $other = User::factory()->create(['name' => 'Zelda Quux']);

        $this->actingAs($this->user)
            ->get('/chat')
            ->assertOk()
            ->assertSee('Global chat')
            ->assertSee('shared room')
            ->assertSee('Find a member…')
            ->assertSee('Zelda Quux')
            ->assertSee('Direct messages')
            ->assertSee('Coming soon')
            ->assertDontSee($other->email)
            ->assertDontSee($other->google_id);
    }

    #[Test]
    public function member_search_filters_the_directory_case_insensitively(): void
    {
        $matching = User::factory()->create(['name' => 'Zelda Quux']);
        $other = User::factory()->create(['name' => 'Yolanda Yarrow']);

        Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->set('memberSearch', 'ZELDA')
            ->call('visibleMembers')
            ->assertReturned(fn (array $members): bool => count($members) === 1
                && (int) $members[0]['id'] === $matching->id
                && $members[0]['name'] === 'Zelda Quux')
            ->call('clearMemberSearch')
            ->assertSet('memberSearch', '');
    }

    #[Test]
    public function clicking_a_sidebar_member_filters_the_transcript(): void
    {
        $other = User::factory()->create(['name' => 'Maya Chen']);
        Message::factory()->for($this->user)->create(['content' => 'From me']);
        Message::factory()->for($other)->create(['content' => 'From Maya']);

        $component = Livewire::actingAs($this->user)->test(Chat::class);

        $component->call('filterBySender', $other->id)
            ->assertSet('senderFilter', (string) $other->id)
            ->assertSet('filter', 'all')
            ->assertCount('messages', 1)
            ->assertSet('messages', fn (array $messages): bool => $messages[0]['content'] === 'From Maya');

        $component->call('filterBySender', $this->user->id)
            ->assertSet('filter', 'mine')
            ->assertSet('senderFilter', 'all')
            ->assertCount('messages', 1)
            ->assertSet('messages', fn (array $messages): bool => $messages[0]['content'] === 'From me');
    }

    #[Test]
    public function an_unknown_member_cannot_be_used_as_a_filter(): void
    {
        Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->set('senderFilter', 'all')
            ->call('filterBySender', 999_999)
            ->assertSet('senderFilter', 'all')
            ->assertSet('filter', 'all');
    }

    #[Test]
    public function the_member_directory_never_exposes_private_profile_fields(): void
    {
        $other = User::factory()->create(['name' => 'Private Person']);

        Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->assertSet('members', function (array $members) use ($other): bool {
                $member = collect($members)->firstWhere('id', $other->id);

                return $member !== null
                    && array_keys($member) === ['id', 'name', 'avatar'];
            });
    }

    /* ------------------------------------------------------------------ */
    /* Ephemeral reactions */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function reactions_toggle_on_and_off_without_persisting_anything(): void
    {
        $message = Message::factory()->for($this->user)->create(['content' => 'React to me']);

        $component = Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->call('toggleReaction', $message->id, '👍')
            ->assertHasNoErrors()
            ->assertDispatched('reaction-preview-updated')
            ->assertSet('reactions', function (array $reactions) use ($message): bool {
                $reactors = $reactions[$message->id]['👍'] ?? [];

                return count($reactors) === 1
                    && (int) $reactors[0]['id'] === $this->user->id;
            });

        $component->call('reactionSummary', $message->id)
            ->assertReturned(fn (array $summary): bool => count($summary) === 1
                && $summary[0]['emoji'] === '👍'
                && $summary[0]['count'] === 1
                && $summary[0]['mine'] === true);

        $component->call('selectedReactions', $message->id)
            ->assertReturned(fn (array $selected): bool => $selected === ['👍']);

        $component->call('toggleReaction', $message->id, '👍')
            ->assertSet('reactions', [])
            ->call('reactionSummary', $message->id)
            ->assertReturned(fn (array $summary): bool => $summary === []);

        // Only the original message exists: reactions never create records.
        $this->assertSame(1, Message::withTrashed()->count());
    }

    #[Test]
    public function one_user_can_choose_multiple_reaction_types_on_a_message(): void
    {
        $message = Message::factory()->for($this->user)->create();

        Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->call('toggleReaction', $message->id, '👍')
            ->call('toggleReaction', $message->id, '🎉')
            ->call('selectedReactions', $message->id)
            ->assertReturned(fn (array $selected): bool => $selected === ['👍', '🎉'])
            ->call('reactionSummary', $message->id)
            ->assertReturned(fn (array $summary): bool => count($summary) === 2);
    }

    #[Test]
    public function unavailable_reactions_are_rejected(): void
    {
        $message = Message::factory()->for($this->user)->create();

        Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->call('toggleReaction', $message->id, '🚀')
            ->assertHasErrors(['reaction' => 'That reaction is not available.'])
            ->assertSet('reactions', [])
            ->call('toggleReaction', 999_999, '👍')
            ->assertHasErrors(['reaction' => 'That message is no longer available.']);
    }

    /* ------------------------------------------------------------------ */
    /* Ephemeral reply previews */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function a_reply_preview_is_attached_to_the_next_sent_message(): void
    {
        $target = Message::factory()->for($this->user)->create(['content' => 'Original thought']);

        $component = Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->call('startReply', $target->id)
            ->assertSet('replyingToId', $target->id)
            ->assertSee('Replying to')
            ->assertSee('Preview · not saved')
            ->assertSee('Original thought');

        $component->set('newMessage', 'A reply')
            ->call('send')
            ->assertHasNoErrors()
            ->assertSet('replyingTo', null)
            ->assertSet('replyingToId', null);

        $reply = Message::query()->where('content', 'A reply')->firstOrFail();

        $component->assertSet('replyContext', function (array $context) use ($reply, $target): bool {
            $quote = $context[$reply->id] ?? null;

            return $quote !== null
                && (int) $quote['id'] === $target->id
                && $quote['content'] === 'Original thought'
                && ($quote['deleted'] ?? false) === false;
        });

        $this->assertFalse(Schema::hasColumn('messages', 'reply_to_message_id'));
        $this->assertSame(2, Message::count());
    }

    #[Test]
    public function replying_to_a_missing_message_is_rejected(): void
    {
        Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->call('startReply', 999_999)
            ->assertSet('replyingTo', null)
            ->assertSet('replyingToId', null)
            ->assertHasErrors(['chat' => 'This message is no longer available to reply to.']);
    }

    #[Test]
    public function a_deleted_reply_target_is_dropped_before_sending(): void
    {
        $target = Message::factory()->for($this->user)->create();

        $component = Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->call('startReply', $target->id)
            ->call('onMessageDeleted', ['id' => $target->id])
            ->assertSet('replyingTo', null)
            ->assertSet('replyingToId', null);

        $component->set('newMessage', 'No forged quote')
            ->call('send')
            ->assertHasNoErrors()
            ->assertSet('replyContext', []);
    }

    #[Test]
    public function quote_cards_are_marked_deleted_when_the_original_disappears(): void
    {
        $target = Message::factory()->for($this->user)->create(['content' => 'Will vanish']);

        $component = Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->call('startReply', $target->id)
            ->set('newMessage', 'Quoted reply')
            ->call('send')
            ->assertHasNoErrors();

        $reply = Message::query()->where('content', 'Quoted reply')->firstOrFail();

        $component->call('delete', $target->id)
            ->assertHasNoErrors()
            ->assertSet('replyContext', function (array $context) use ($reply): bool {
                $quote = $context[$reply->id] ?? null;

                return $quote !== null
                    && ($quote['deleted'] ?? false) === true
                    && $quote['content'] === '';
            });
    }

    #[Test]
    public function jumping_to_an_unloaded_reply_target_dispatches_a_safe_notice(): void
    {
        $target = Message::factory()->for($this->user)->create(['content' => 'Oldest message']);

        Message::factory()->for($this->user)->count(10)->create();

        Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->assertCount('messages', 10)
            ->call('startReply', $target->id)
            ->assertSet('replyingTo.loaded', false)
            ->call('jumpToMessage', $target->id)
            ->assertDispatched('reply-target-unavailable');
    }

    #[Test]
    public function reactions_and_replies_create_no_tables_or_columns(): void
    {
        $this->assertFalse(Schema::hasTable('message_reactions'));
        $this->assertFalse(Schema::hasTable('replies'));
        $this->assertFalse(Schema::hasColumn('messages', 'reply_to_message_id'));
        $this->assertFalse(Schema::hasColumn('messages', 'reactions'));
    }

    /* ------------------------------------------------------------------ */
    /* Custom date range validation */
    /* ------------------------------------------------------------------ */

    #[Test]
    public function incomplete_or_reversed_custom_dates_show_an_inline_error(): void
    {
        $component = Livewire::actingAs($this->user)->test(Chat::class);

        $component->set('datePreset', 'custom')
            ->set('dateFrom', now()->format('Y-m-d'))
            ->assertSet('dateError', 'Choose both a start and an end date to complete the custom range.');

        $component->set('dateTo', now()->subDays(3)->format('Y-m-d'))
            ->assertSet('dateError', 'The start date must be on or before the end date.');

        $component->set('dateFrom', now()->subDays(5)->format('Y-m-d'))
            ->assertSet('dateError', null);
    }

    #[Test]
    public function directory_helpers_and_filter_clear_actions_keep_the_sidebar_consistent(): void
    {
        $other = User::factory()->create(['name' => 'Zelda Quux']);

        $component = Livewire::actingAs($this->user)
        ->test(Chat::class)
        ->call('retryMembers')
        ->set('memberSearch', '  zelda  ')
        ->call('visibleMembers')
        ->assertReturned(fn (array $members): bool => count($members) === 1
            && $members[0]['id'] === $other->id)
        ->call('isMemberActive', $this->user->id)
        ->assertReturned(false)
        ->set('filter', 'mine')
        ->call('isMemberActive', $this->user->id)
        ->assertReturned(true)
        ->call('clearMemberSearch')
        ->assertSet('memberSearch', '')
        ->call('clearSearchFilter')
        ->set('senderFilter', $other->id)
        ->call('clearSenderFilter')
        ->set('datePreset', 'today')
        ->call('clearDateFilter')
        ->set('filter', 'mine')
        ->call('clearOnlyMeFilter')
        ->assertSet('filter', 'all')
        ->call('hasActiveFilters')
        ->assertReturned(false);

        $component->set('search', 'missing')
        ->call('clearSearchFilter')
        ->assertSet('search', '');
    }

    #[Test]
    public function filter_by_sender_resets_other_filters_and_unknown_dates_are_rejected(): void
    {
        $other = User::factory()->create();

        Livewire::actingAs($this->user)
        ->test(Chat::class)
        ->set('search', 'old search')
        ->set('datePreset', 'custom')
        ->set('dateFrom', 'not-a-date')
        ->set('dateTo', '2025-01-01')
        ->call('applyCustomDateRange')
        ->assertSet('dateError', 'Enter valid dates in YYYY-MM-DD format.')
        ->call('filterBySender', $other->id)
        ->assertSet('datePreset', 'all')
        ->assertSet('dateFrom', '')
        ->assertSet('dateTo', '')
        ->assertSet('dateError', null)
        ->assertSet('senderFilter', (string) $other->id)
        ->call('clearFilters')
        ->call('hasActiveFilters')
        ->assertReturned(false);
    }

    #[Test]
    public function incoming_local_echoes_are_deduplicated_and_reply_previews_follow_edits(): void
    {
        $target = Message::factory()->for($this->user)->create(['content' => 'Original']);
        $component = Livewire::actingAs($this->user)
        ->test(Chat::class)
        ->call('startReply', $target->id)
        ->set('newMessage', 'Reply')
        ->call('send');

        $reply = Message::query()->where('content', 'Reply')->firstOrFail();
        $replyPayload = (new MessageSent($reply))->broadcastWith();

        $component->call('onMessageSent', $replyPayload)
        ->assertNotDispatched('message-received')
        ->assertCount('messages', 2)
        ->call('startReply', $target->id)
        ->call('onMessageUpdated', [
            ...$replyPayload,
            'id' => $target->id,
            'content' => 'Edited original',
            'user' => ['id' => $this->user->id, 'name' => $this->user->name],
        ])
        ->assertSet('replyingTo.content', 'Edited original')
        ->assertSet('replyContext', function (array $context) use ($reply): bool {
            return $context[$reply->id]['content'] === 'Edited original';
        })
        ->call('jumpToMessage', $target->id)
        ->assertDispatched('scroll-to-message');
    }

    #[Test]
    public function date_presets_filter_messages_and_custom_invalid_ranges_do_not_reload(): void
    {
        $yesterday = Message::factory()->for($this->user)->create([
        'content' => 'Yesterday',
        'created_at' => now()->subDay(),
        ]);
        Message::factory()->for($this->user)->create([
        'content' => 'Last week',
        'created_at' => now()->subDays(8),
        ]);

        $component = Livewire::actingAs($this->user)->test(Chat::class);
        $component->set('datePreset', 'yesterday')
        ->assertCount('messages', 1)
        ->assertSet('messages.0.id', $yesterday->id);

        $component->set('datePreset', 'last_7_days')->assertCount('messages', 1);

        Message::factory()->for($this->user)->create([
        'content' => 'Recent',
        'created_at' => now()->subDays(3),
        ]);
        $component->set('datePreset', 'last_30_days')->assertCount('messages', 3);

        $component->set('datePreset', 'custom')
        ->set('dateFrom', 'not-a-date')
        ->set('dateTo', now()->addDay()->format('Y-m-d'))
        ->assertSet('dateError', 'Enter valid dates in YYYY-MM-DD format.')
        ->assertCount('messages', 3);
    }

    #[Test]
    public function service_failures_are_reported_and_failed_sends_can_be_retried(): void
    {
        $realService = new MessageService;
        $attempts = 0;
        $service = \Mockery::mock(MessageService::class)->makePartial();
        $service->shouldReceive('sendFromChat')
        ->twice()
        ->andReturnUsing(function (User $author, string $content) use ($realService, &$attempts): Message {
            if (++$attempts === 1) {
                throw new \RuntimeException('temporary send failure');
            }

            return $realService->sendFromChat($author, $content);
        });
        app()->instance(MessageService::class, $service);

        Livewire::actingAs($this->user)
        ->test(Chat::class)
        ->set('newMessage', 'Retry me')
        ->call('send')
        ->assertSet('sendFailed', true)
        ->assertHasErrors(['newMessage' => "We couldn't send your message. Your draft is safe — please try again."])
        ->assertDispatched('message-send-failed')
        ->call('retrySend')
        ->assertSet('sendFailed', false)
        ->assertSet('newMessage', '');

        $this->assertDatabaseHas('messages', ['user_id' => $this->user->id, 'content' => 'Retry me']);
    }

    #[Test]
    public function failed_page_loads_and_older_page_requests_expose_retryable_errors(): void
    {
        $realService = new MessageService;
        $pageTwoAttempts = 0;
        $service = \Mockery::mock(MessageService::class)->makePartial();
        $service->shouldReceive('paginateForChat')
        ->times(4)
        ->andReturnUsing(function (...$arguments) use ($realService, &$pageTwoAttempts): \Illuminate\Contracts\Pagination\Paginator {
            if ($arguments[4] === 2 && ++$pageTwoAttempts === 1) {
                throw new \RuntimeException('temporary page failure');
            }

            return $realService->paginateForChat(...$arguments);
        });
        app()->instance(MessageService::class, $service);
        Message::factory()->count(11)->create();

        $component = Livewire::actingAs($this->user)->test(Chat::class)
        ->assertSet('loadError', null)
        ->call('loadMore')
        ->assertHasErrors(['chat' => "We couldn't load older messages. Please try again."])
        ->call('retryLoad')
        ->assertSet('loadError', null)
        ->assertDispatched('transcript-reset');

        $component->call('loadMore')->assertCount('messages', 11);
    }

    #[Test]
    public function an_initial_transcript_failure_is_reported_without_hiding_the_retry_action(): void
    {
        $service = \Mockery::mock(MessageService::class)->makePartial();
        $service->shouldReceive('paginateForChat')->once()->andThrow(new \RuntimeException('database unavailable'));
        app()->instance(MessageService::class, $service);

        Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->assertSet('messages', [])
            ->assertSet('page', 1)
            ->assertSet('hasMore', false)
            ->assertSet('loadError', 'Check your connection and try again — the room will be waiting right here.');
    }

    #[Test]
    public function saving_without_an_active_edit_selection_is_rejected(): void
    {
        Livewire::actingAs($this->user)
            ->test(Chat::class)
            ->call('saveEdit')
            ->assertHasErrors(['editContent' => 'Select a message to edit first.']);
    }

    #[Test]
    public function update_and_delete_service_failures_leave_the_message_unchanged(): void
    {
        $message = Message::factory()->for($this->user)->create(['content' => 'Keep me']);
        $service = \Mockery::mock(MessageService::class)->makePartial();
        $service->shouldReceive('update')->once()->andThrow(new \RuntimeException('update unavailable'));
        $service->shouldReceive('delete')->once()->andThrow(new \RuntimeException('delete unavailable'));
        app()->instance(MessageService::class, $service);

        $component = Livewire::actingAs($this->user)->test(Chat::class)
        ->call('startEditing', $message->id)
        ->set('editContent', 'Changed')
        ->call('saveEdit')
        ->assertHasErrors(['editContent' => "We couldn't save your changes. Please try again."])
        ->call('delete', $message->id)
        ->assertHasErrors(['chat' => "We couldn't delete this message. Please try again."]);

        $this->assertSame('Keep me', $message->fresh()->content);
        $this->assertNotSoftDeleted($message);
    }
}
