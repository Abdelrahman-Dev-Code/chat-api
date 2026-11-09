<?php

namespace App\Livewire;

use App\Http\Requests\SaveMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Models\User;
use App\Services\MessageService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * The global chat room.
 *
 * Every authenticated user shares one public room. Reads and writes go
 * through MessageService, exactly like the REST API, so validation,
 * authorization policies and broadcast events are shared.
 */
#[Layout('layouts.app')]
class Chat extends Component
{
    /**
     * Curated reaction palette. Reactions are intentionally ephemeral until a
     * persistence layer (table, API and broadcast events) is introduced.
     *
     * @var list<string>
     */
    public const array REACTION_OPTIONS = ['👍', '❤️', '😂', '😮', '😢', '🙏', '🔥', '🎉'];

    /**
     * Message snapshots currently rendered, oldest first.
     *
     * @var list<array<string, mixed>>
     */
    #[Locked]
    public array $messages = [];

    #[Locked]
    public int $page = 1;

    #[Locked]
    public bool $hasMore = false;

    #[Locked]
    public ?string $loadError = null;

    /**
     * Text search filter (case-insensitive, matches message content).
     */
    public string $search = '';

    /**
     * Sender filter: 'all' for everyone, or a user ID string.
     */
    public string $senderFilter = 'all';

    /**
     * Date filter preset: all | today | yesterday | last_7_days | last_30_days | custom.
     */
    public string $datePreset = 'all';

    /**
     * Custom range start date (Y-m-d), only used when datePreset is 'custom'.
     */
    public string $dateFrom = '';

    /**
     * Custom range end date (Y-m-d), only used when datePreset is 'custom'.
     */
    public string $dateTo = '';

    public string $filter = 'all';

    public string $newMessage = '';

    /**
     * Whether the last send attempt failed. Used to surface a retry action
     * while preserving the typed draft. Reset on every new send attempt.
     */
    public bool $sendFailed = false;

    /**
     * IDs of messages sent by the local user in the current request cycle,
     * used to suppress a duplicate `message-received` broadcast when Echo
     * echoes back a message we already announced via `message-sent`.
     *
     * @var list<int>
     */
    public array $justSentMessageIds = [];

    /**
     * Registered members shown in the sidebar. Only public profile fields are
     * exposed (id, name and avatar) — never email addresses or OAuth ids.
     *
     * @var list<array{id: int, name: string, avatar: string|null}>
     */
    public array $members = [];

    /**
     * Available senders for the sender filter dropdown (excludes the viewer).
     *
     * @var list<array{id: int, name: string, avatar: string|null}>
     */
    public array $senders = [];

    /**
     * Member name search in the sidebar.
     */
    public string $memberSearch = '';

    /**
     * Sidebar directory error. Kept separate from the transcript error so a
     * directory failure does not destroy a healthy chat.
     */
    public ?string $membersError = null;

    /**
     * Inline validation message for an incomplete or reversed custom range.
     */
    public ?string $dateError = null;

    /**
     * Ephemeral reaction state: [message id => [emoji => list of reactors]].
     *
     * This is deliberately not persisted. The shape matches the future
     * message_reactions persistence model so it can be swapped for a service.
     *
     * @var array<int, array<string, list<array{id: int, name: string, avatar: string|null}>>>
     */
    public array $reactions = [];

    /**
     * The message currently being replied to (preview state only).
     *
     * @var array{id: int, user_id: int, name: string, content: string, loaded: bool}|null
     */
    public ?array $replyingTo = null;

    #[Locked]
    public ?int $replyingToId = null;

    /**
     * Ephemeral quote cards for messages sent from this component instance:
     * [new message id => reply target snapshot].
     *
     * Quote cards are explicitly marked as previews in the UI because the
     * existing schema has no reply_to_message_id column.
     *
     * @var array<int, array{id: int, user_id: int, name: string, content: string, loaded: bool, deleted?: bool}>
     */
    public array $replyContext = [];

    #[Locked]
    public ?int $editingId = null;

    public string $editContent = '';

    private MessageService $messageService;

    /**
     * Per-request memo for the resolved date range. Livewire hydrates a fresh
     * component for every request, so no stale value can leak between users.
     *
     * @var array{from: Carbon|null, to: Carbon|null}|null
     */
    private ?array $dateRangeCache = null;

    public function boot(MessageService $messages): void
    {
        $this->messageService = $messages;
    }

    public function mount(): void
    {
        $this->loadDirectory();
        $this->loadFirstPage();
    }

    /**
     * Load the public member directory used by the sidebar and sender filter.
     *
     * One query powers both lists, so they can never disagree about who is in
     * the room. Only display-safe columns are selected.
     */
    public function loadDirectory(): void
    {
        $this->membersError = null;

        try {
            $this->members = User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'avatar'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                ])
                ->values()
                ->all();
        } catch (Throwable $exception) {
            $this->reportFailure($exception, 'load_directory');
            $this->members = [];
            $this->senders = [];
            $this->membersError = __('chat.errors.members_failed');

            return;
        }

        $viewerId = $this->user()->id;

        $this->senders = array_values(array_filter(
            $this->members,
            fn (array $member): bool => $member['id'] !== $viewerId,
        ));
    }

    public function retryMembers(): void
    {
        $this->loadDirectory();
    }

    public function clearMemberSearch(): void
    {
        $this->memberSearch = '';
    }

    /**
     * Members matching the sidebar name search.
     *
     * @return list<array{id: int, name: string, avatar: string|null}>
     */
    public function visibleMembers(): array
    {
        $term = mb_strtolower(trim($this->memberSearch));

        if ($term === '') {
            return $this->members;
        }

        return array_values(array_filter(
            $this->members,
            fn (array $member): bool => str_contains(mb_strtolower($member['name']), $term),
        ));
    }

    /**
     * Whether a sidebar member row reflects the active transcript filter.
     */
    public function isMemberActive(int $memberId): bool
    {
        if ($this->filter === 'mine') {
            return $memberId === $this->user()->id;
        }

        return $this->senderFilter !== 'all' && (int) $this->senderFilter === $memberId;
    }

    /**
     * Reload the newest page when any filter changes.
     */
    public function updatedSearch(): void
    {
        $this->applyFilters();
    }

    public function updatedSenderFilter(): void
    {
        if ($this->senderFilter !== 'all') {
            $this->filter = 'all';
        }

        $this->applyFilters();
    }

    public function updatedDatePreset(): void
    {
        $this->applyFilters();
    }

    public function updatedDateFrom(): void
    {
        $this->dateRangeCache = null;
        $this->dateError = $this->dateRangeError();
    }

    public function updatedDateTo(): void
    {
        $this->dateRangeCache = null;
        $this->dateError = $this->dateRangeError();
    }

    public function applyCustomDateRange(): void
    {
        $this->applyFilters();
    }

    /**
     * Reload the newest page when the author filter changes.
     */
    public function updatedFilter(): void
    {
        if ($this->filter === 'mine') {
            $this->senderFilter = 'all';
        }

        $this->applyFilters();
    }

    /**
     * Focus the transcript on one member from the sidebar.
     */
    public function filterBySender(int $memberId): void
    {
        if (User::query()->whereKey($memberId)->doesntExist()) {
            return;
        }

        if ($memberId === $this->user()->id) {
            $this->filter = 'mine';
            $this->senderFilter = 'all';
        } else {
            $this->filter = 'all';
            $this->senderFilter = (string) $memberId;
        }

        $this->datePreset = 'all';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->dateError = null;

        $this->applyFilters();
    }

    public function clearSearchFilter(): void
    {
        $this->search = '';
        $this->applyFilters();
    }

    public function clearSenderFilter(): void
    {
        $this->senderFilter = 'all';
        $this->applyFilters();
    }

    public function clearDateFilter(): void
    {
        $this->datePreset = 'all';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->dateError = null;
        $this->applyFilters();
    }

    public function clearOnlyMeFilter(): void
    {
        $this->filter = 'all';
        $this->applyFilters();
    }

    /**
     * Clear all filters and return to the full transcript.
     */
    public function clearFilters(): void
    {
        $this->search = '';
        $this->senderFilter = 'all';
        $this->datePreset = 'all';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->dateError = null;
        $this->filter = 'all';
        $this->applyFilters();
    }

    /**
     * Validate the current filter state, then reload page one.
     */
    private function applyFilters(): void
    {
        $this->dateRangeCache = null;
        $this->dateError = $this->dateRangeError();

        if ($this->dateError !== null) {
            return;
        }

        $this->page = 1;
        $this->loadFirstPage();
        $this->dispatch('transcript-reset');
    }

    /**
     * Retry the first page after a failed initial load or filter change.
     */
    public function retryLoad(): void
    {
        $this->loadError = null;
        $this->loadFirstPage();
        $this->dispatch('transcript-reset');
    }

    /**
     * Append the previous page (10 older messages) at the top of the list.
     */
    public function loadMore(): void
    {
        if (! $this->hasMore || $this->dateError !== null) {
            return;
        }

        $range = $this->resolveDateRange();

        try {
            $paginator = $this->messageService->paginateForChat(
                $this->filterUserIds(),
                $this->searchTerm(),
                $range['from'],
                $range['to'],
                $this->page + 1,
            );
        } catch (Throwable $exception) {
            $this->reportFailure($exception, 'load_more');
            $this->addError('chat', __('chat.errors.load_older_failed'));

            return;
        }

        $this->messages = $this->mergeMessages($this->present($paginator), $this->messages);
        $this->page = $paginator->currentPage();
        $this->hasMore = $paginator->hasMorePages();
    }

    /**
     * Create a message for the signed-in user and broadcast it to everyone else.
     */
    public function send(): void
    {
        $this->newMessage = trim($this->newMessage);
        $this->sendFailed = false;

        $this->validateOnly('newMessage', [
            'newMessage' => SaveMessageRequest::contentRules(),
        ], attributes: ['newMessage' => __('chat.validation.message')]);

        $user = $this->user();
        $rateLimitKey = 'chat-send:'.$user->id;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 30)) {
            $this->sendFailed = true;
            $this->addError('newMessage', __('chat.errors.rate_limited'));
            $this->dispatch('message-send-failed');

            return;
        }

        RateLimiter::hit($rateLimitKey, 60);

        try {
            $message = $this->messageService->sendFromChat($user, $this->newMessage);
        } catch (Throwable $exception) {
            $this->reportFailure($exception, 'send');
            $this->sendFailed = true;
            $this->addError('newMessage', __('chat.errors.send_failed'));
            $this->dispatch('message-send-failed');

            return;
        }

        $snapshot = $this->snapshot($message);
        $replyTarget = $this->replyingTo;

        // A reply quote is preview-only, but it must still never point at a
        // message that no longer exists when the message is sent.
        if ($replyTarget !== null && Message::query()->whereKey($replyTarget['id'])->doesntExist()) {
            $replyTarget = null;
        }

        if ($replyTarget !== null) {
            $this->replyContext[$message->id] = $replyTarget;
        }

        if ($this->matchesFilter($snapshot)) {
            $this->messages = $this->mergeMessages([$snapshot], $this->messages);
            $this->justSentMessageIds = array_slice([...$this->justSentMessageIds, $message->id], -20);
        }

        $this->newMessage = '';
        $this->cancelReply();
        $this->resetValidation('newMessage');
        $this->dispatch('message-sent');
    }

    /**
     * Retry a failed send. Preserves the current draft in newMessage.
     */
    public function retrySend(): void
    {
        $this->send();
    }

    /**
     * Enter inline edit mode. Only the author may do so.
     */
    public function startEditing(int $messageId): void
    {
        $message = Message::query()->findOrFail($messageId);

        if (! $this->canModify('update', $message)) {
            return;
        }

        $this->editingId = $message->id;
        $this->editContent = $message->content;
        $this->resetValidation('editContent');
    }

    public function cancelEditing(): void
    {
        $this->editingId = null;
        $this->editContent = '';
        $this->resetValidation('editContent');
    }

    /**
     * Persist an inline edit and broadcast the new content.
     */
    public function saveEdit(): void
    {
        if ($this->editingId === null) {
            $this->addError('editContent', __('chat.errors.no_edit_selection'));

            return;
        }

        $this->editContent = trim($this->editContent);

        $this->validateOnly('editContent', [
            'editContent' => SaveMessageRequest::contentRules(),
        ], attributes: ['editContent' => __('chat.validation.message')]);

        $message = Message::query()->findOrFail($this->editingId);

        if (! $this->canModify('update', $message)) {
            return;
        }

        try {
            $updated = $this->snapshot($this->messageService->update($message, $this->editContent));
        } catch (Throwable $exception) {
            $this->reportFailure($exception, 'update');
            $this->addError('editContent', __('chat.errors.update_failed'));

            return;
        }

        $this->messages = array_map(
            fn (array $current): array => $current['id'] === $updated['id'] ? $updated : $current,
            $this->messages,
        );

        $this->syncReplyTarget($updated);
        $this->cancelEditing();
        $this->dispatch('message-updated');
    }

    /**
     * Soft-delete an owned message and remove it from every connected client.
     */
    public function delete(int $messageId): void
    {
        $message = Message::query()->findOrFail($messageId);

        if (! $this->canModify('delete', $message)) {
            return;
        }

        try {
            $this->messageService->delete($message);
        } catch (Throwable $exception) {
            $this->reportFailure($exception, 'delete');
            $this->addError('chat', __('chat.errors.delete_failed'));

            return;
        }

        $this->messages = array_values(array_filter(
            $this->messages,
            fn (array $current): bool => $current['id'] !== $messageId,
        ));

        $this->forgetMessageState($messageId);

        if ($this->editingId === $messageId) {
            $this->cancelEditing();
        }

        $this->dispatch('message-deleted');
    }

    /**
     * A new message was broadcast by another client.
     *
     * @param  array<string, mixed>  $message
     */
    #[On('echo-private:chat,.message.sent')]
    public function onMessageSent(array $message): void
    {
        if (! $this->matchesFilter($message)) {
            return;
        }

        $messageId = (int) $message['id'];

        // Suppress the broadcast echo of a message we just sent locally; it was
        // already announced via `message-sent`. Any other incoming message is
        // genuinely new and should be announced.
        $isLocalEcho = in_array($messageId, $this->justSentMessageIds, true);

        $this->messages = $this->mergeMessages([$message], $this->messages);

        $this->justSentMessageIds = array_values(array_filter(
            $this->justSentMessageIds,
            fn (int $id): bool => $id !== $messageId,
        ));

        if (! $isLocalEcho) {
            $this->dispatch('message-received');
        }
    }

    /**
     * A message was edited by its author in another browser.
     *
     * @param  array<string, mixed>  $message
     */
    #[On('echo-private:chat,.message.updated')]
    public function onMessageUpdated(array $message): void
    {
        $this->syncReplyTarget($message);

        if (! $this->containsMessage((int) $message['id'])) {
            return;
        }

        $this->messages = array_map(
            fn (array $current): array => $current['id'] === (int) $message['id'] ? $message : $current,
            $this->messages,
        );
    }

    /**
     * A message was soft-deleted by its author in another browser.
     *
     * @param  array{id: int}  $payload
     */
    #[On('echo-private:chat,.message.deleted')]
    public function onMessageDeleted(array $payload): void
    {
        $this->messages = array_values(array_filter(
            $this->messages,
            fn (array $current): bool => $current['id'] !== (int) $payload['id'],
        ));

        $this->forgetMessageState((int) $payload['id']);

        if ($this->editingId === (int) $payload['id']) {
            $this->cancelEditing();
        }
    }

    public function render(): View
    {
        return view('livewire.chat', [
            'reactionOptions' => self::REACTION_OPTIONS,
        ])->title(__('chat.meta.title'));
    }

    /* ------------------------------------------------------------------ */
    /* Reactions — ephemeral UI preview */
    /* ------------------------------------------------------------------ */

    /**
     * Toggle the current user's reaction on a message.
     *
     * No database write and no broadcast occur: the existing schema has no
     * reactions table, so this state lives for the lifetime of the component.
     */
    public function toggleReaction(int $messageId, string $emoji): void
    {
        if (! in_array($emoji, self::REACTION_OPTIONS, true)) {
            $this->addError('reaction', __('chat.errors.invalid_reaction'));

            return;
        }

        if (Message::query()->whereKey($messageId)->doesntExist()) {
            $this->addError('reaction', __('chat.errors.message_missing'));

            return;
        }

        $user = $this->user();
        $reactors = $this->reactions[$messageId][$emoji] ?? [];
        $alreadyReacted = false;

        foreach ($reactors as $index => $reactor) {
            if ((int) $reactor['id'] === $user->id) {
                unset($reactors[$index]);
                $alreadyReacted = true;

                break;
            }
        }

        if (! $alreadyReacted) {
            $reactors[] = [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar,
            ];
        }

        $reactors = array_values($reactors);

        if ($reactors === []) {
            unset($this->reactions[$messageId][$emoji]);

            if (empty($this->reactions[$messageId])) {
                unset($this->reactions[$messageId]);
            }
        } else {
            $this->reactions[$messageId][$emoji] = $reactors;
        }

        $this->dispatch('reaction-preview-updated');
    }

    /**
     * Summary used by the message reaction chips.
     *
     * @return list<array{emoji: string, count: int, mine: bool, people: list<string>, title: string}>
     */
    public function reactionSummary(int $messageId): array
    {
        $groups = $this->reactions[$messageId] ?? [];
        $summary = [];

        foreach (self::REACTION_OPTIONS as $emoji) {
            $reactors = $groups[$emoji] ?? [];

            if ($reactors === []) {
                continue;
            }

            $people = array_map(
                fn (array $reactor): string => (int) $reactor['id'] === $this->user()->id
                    ? __('chat.common.you')
                    : (string) $reactor['name'],
                $reactors,
            );

            $mine = in_array(
                $this->user()->id,
                array_map(fn (array $reactor): int => (int) $reactor['id'], $reactors),
                true,
            );

            $summary[] = [
                'emoji' => $emoji,
                'count' => count($reactors),
                'mine' => $mine,
                'people' => $people,
                'title' => __('chat.reactions.title_people', ['names' => implode(', ', $people)]),
            ];
        }

        return $summary;
    }

    /**
     * Emoji this user has selected for the picker's pressed state.
     *
     * @return list<string>
     */
    public function selectedReactions(int $messageId): array
    {
        $selected = [];

        foreach ($this->reactions[$messageId] ?? [] as $emoji => $reactors) {
            foreach ($reactors as $reactor) {
                if ((int) $reactor['id'] === $this->user()->id) {
                    $selected[] = $emoji;

                    break;
                }
            }
        }

        return $selected;
    }

    /* ------------------------------------------------------------------ */
    /* Reply preview — ephemeral UI */
    /* ------------------------------------------------------------------ */

    /**
     * Start a reply. The target is validated against the existing messages
     * table, so a stale button cannot forge a deleted/nonexistent reference.
     */
    public function startReply(int $messageId): void
    {
        $message = Message::query()->with('user')->find($messageId);

        if ($message === null) {
            $this->addError('chat', __('chat.errors.reply_missing'));

            return;
        }

        $this->replyingToId = $message->id;
        $this->replyingTo = [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'name' => $message->user?->name ?? __('chat.common.unknown_member'),
            'content' => $message->content,
            'loaded' => $this->containsMessage($message->id),
        ];

        $this->resetValidation('chat');
        $this->dispatch('reply-started');
    }

    public function cancelReply(): void
    {
        $this->replyingToId = null;
        $this->replyingTo = null;
    }

    /**
     * Scroll to a loaded message, or explain why it cannot be reached.
     */
    public function jumpToMessage(int $messageId): void
    {
        if ($this->containsMessage($messageId)) {
            $this->dispatch('scroll-to-message', messageId: $messageId);

            return;
        }

        $this->dispatch(
            'reply-target-unavailable',
            message: __('chat.errors.outside_view'),
        );
    }

    /**
     * Keep ephemeral quote previews in sync with message edits/removals.
     *
     * @param  array<string, mixed>  $message
     */
    private function syncReplyTarget(array $message): void
    {
        $messageId = (int) ($message['id'] ?? 0);

        if ($messageId === 0) {
            return;
        }

        $name = (string) ($message['user']['name'] ?? __('chat.common.unknown_member'));
        $content = (string) ($message['content'] ?? '');

        if ($this->replyingToId === $messageId) {
            $this->replyingTo = [
                ...($this->replyingTo ?? []),
                'content' => $content,
                'name' => $name,
            ];
        }

        foreach ($this->replyContext as $replyMessageId => $context) {
            if ((int) $context['id'] === $messageId) {
                $this->replyContext[$replyMessageId] = [
                    ...$context,
                    'content' => $content,
                    'name' => $name,
                    'deleted' => false,
                ];
            }
        }
    }

    /**
     * Remove ephemeral state for a deleted message and mark any quotes that
     * pointed at it as safely deleted.
     */
    private function forgetMessageState(int $messageId): void
    {
        unset($this->reactions[$messageId], $this->replyContext[$messageId]);

        foreach ($this->replyContext as $replyMessageId => $context) {
            if ((int) $context['id'] === $messageId) {
                $this->replyContext[$replyMessageId] = [
                    ...$context,
                    'content' => '',
                    'deleted' => true,
                ];
            }
        }

        if ($this->replyingToId === $messageId) {
            $this->cancelReply();
        }
    }

    /**
     * Resolve the date filter into from/to Carbon instances.
     *
     * @return array{from: Carbon|null, to: Carbon|null}
     */
    private function resolveDateRange(): array
    {
        if ($this->dateRangeCache !== null) {
            return $this->dateRangeCache;
        }

        $now = now();

        return $this->dateRangeCache = match ($this->datePreset) {
            'today' => [
                'from' => $now->copy()->startOfDay(),
                'to' => $now->copy()->endOfDay(),
            ],
            'yesterday' => [
                'from' => $now->copy()->subDay()->startOfDay(),
                'to' => $now->copy()->subDay()->endOfDay(),
            ],
            'last_7_days' => [
                'from' => $now->copy()->subDays(6)->startOfDay(),
                'to' => $now->copy()->endOfDay(),
            ],
            'last_30_days' => [
                'from' => $now->copy()->subDays(29)->startOfDay(),
                'to' => $now->copy()->endOfDay(),
            ],
            'custom' => [
                'from' => $this->parseFilterDate($this->dateFrom),
                'to' => $this->parseFilterDate($this->dateTo, endOfDay: true),
            ],
            default => [
                'from' => null,
                'to' => null,
            ],
        };
    }

    private function parseFilterDate(string $value, bool $endOfDay = false): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }

    /**
     * A human-safe validation message for the current custom range.
     */
    private function dateRangeError(): ?string
    {
        if ($this->datePreset !== 'custom') {
            return null;
        }

        if ($this->dateFrom === '' && $this->dateTo === '') {
            return null;
        }

        if ($this->dateFrom === '' || $this->dateTo === '') {
            return __('chat.errors.date_incomplete');
        }

        $from = $this->parseFilterDate($this->dateFrom);
        $to = $this->parseFilterDate($this->dateTo);

        if ($from === null || $to === null) {
            return __('chat.errors.date_invalid');
        }

        if ($from->greaterThan($to)) {
            return __('chat.errors.date_reversed');
        }

        return null;
    }

    /**
     * Normalize the free-text search to the same bounds as the REST API.
     */
    private function searchTerm(): ?string
    {
        $term = trim($this->search);

        return $term === '' ? null : mb_substr($term, 0, 255);
    }

    private function loadFirstPage(): void
    {
        // Every request starts from a fresh component instance; clear the memo
        // before resolving so filter changes can never reuse a stale range.
        $this->dateRangeCache = null;

        if ($this->dateError !== null) {
            return;
        }

        $this->loadError = null;
        $range = $this->resolveDateRange();

        try {
            $paginator = $this->messageService->paginateForChat(
                $this->filterUserIds(),
                $this->searchTerm(),
                $range['from'],
                $range['to'],
                1,
            );
        } catch (Throwable $exception) {
            $this->reportFailure($exception, 'load_first_page');
            $this->messages = [];
            $this->page = 1;
            $this->hasMore = false;
            $this->loadError = __('chat.errors.load_failed');

            return;
        }

        $this->messages = $this->present($paginator);
        $this->page = 1;
        $this->hasMore = $paginator->hasMorePages();

        if ($this->editingId !== null && ! $this->containsMessage($this->editingId)) {
            $this->cancelEditing();
        }
    }

    /**
     * Turn the newest-first paginator into an oldest-first chat transcript.
     *
     * @return list<array<string, mixed>>
     */
    private function present(PaginatorContract $paginator): array
    {
        return collect($paginator->items())
            ->reverse()
            ->map(fn (Message $message): array => $this->snapshot($message))
            ->values()
            ->all();
    }

    /**
     * Serialize the message exactly like the API and WebSocket payloads so the
     * Livewire state, API responses and Echo events always agree.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Message $message): array
    {
        $snapshot = json_decode(
            (new MessageResource($message->loadMissing('user')))->toJson(),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        $snapshot['edited'] = $snapshot['updated_at'] !== $snapshot['created_at'];

        return $snapshot;
    }

    /**
     * @return list<int>
     */
    private function filterUserIds(): array
    {
        if ($this->filter === 'mine') {
            return [$this->user()->id];
        }

        if ($this->senderFilter !== 'all') {
            return [(int) $this->senderFilter];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function matchesFilter(array $message): bool
    {
        // Author filter.
        if ($this->filter === 'mine'
            && (int) ($message['user']['id'] ?? 0) !== $this->user()->id) {
            return false;
        }

        if ($this->senderFilter !== 'all'
            && (int) ($message['user']['id'] ?? 0) !== (int) $this->senderFilter) {
            return false;
        }

        // Search filter (case-insensitive content match).
        $term = $this->searchTerm();

        if ($term !== null
            && ! str_contains(mb_strtolower((string) ($message['content'] ?? '')), mb_strtolower($term))) {
            return false;
        }

        // Date filter.
        if ($this->datePreset !== 'all') {
            $createdAt = \Illuminate\Support\Carbon::parse($message['created_at']);
            ['from' => $from, 'to' => $to] = $this->resolveDateRange();

            if ($from !== null && $createdAt->startOfDay() < $from->copy()->startOfDay()) {
                return false;
            }

            if ($to !== null && $createdAt->endOfDay() > $to->copy()->endOfDay()) {
                return false;
            }
        }

        return true;
    }

    private function containsMessage(int $messageId): bool
    {
        foreach ($this->messages as $message) {
            if ($message['id'] === $messageId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Merge, de-duplicate and sort snapshots chronologically. Page boundaries
     * move when new messages arrive, so duplicates are possible without this.
     *
     * @param  list<array<string, mixed>>  $incoming
     * @param  list<array<string, mixed>>  $current
     * @return list<array<string, mixed>>
     */
    private function mergeMessages(array $incoming, array $current): array
    {
        $byId = [];

        foreach ([...$incoming, ...$current] as $message) {
            $byId[$message['id']] = $message;
        }

        $sorted = array_values($byId);

        usort($sorted, fn (array $a, array $b): int => [$a['created_at'], $a['id']] <=> [$b['created_at'], $b['id']]);

        return $sorted;
    }

    private function canModify(string $ability, Message $message): bool
    {
        if (Gate::allows($ability, $message)) {
            return true;
        }

        $this->addError('chat', __('chat.errors.not_owner'));

        return false;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    /**
     * Whether any filter is currently active.
     */
    public function hasActiveFilters(): bool
    {
        return $this->searchTerm() !== null
            || $this->senderFilter !== 'all'
            || $this->datePreset !== 'all'
            || $this->dateFrom !== ''
            || $this->dateTo !== ''
            || $this->filter !== 'all';
    }

    /**
     * Human-readable label for the active date preset.
     */
    public function datePresetLabel(): string
    {
        return match ($this->datePreset) {
            'today' => __('chat.header.today'),
            'yesterday' => __('chat.header.yesterday'),
            'last_7_days' => __('chat.header.last_7_days'),
            'last_30_days' => __('chat.header.last_30_days'),
            'custom' => __('chat.header.custom_range'),
            default => __('chat.header.all_time'),
        };
    }

    /**
     * Log a failed chat operation with enough context to debug production
     * issues without leaking message contents.
     */
    private function reportFailure(Throwable $exception, string $operation): void
    {
        Log::warning('chat.operation_failed', [
            'operation' => $operation,
            'user_id' => Auth::id(),
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        report($exception);
    }
}
