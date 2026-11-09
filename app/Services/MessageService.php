<?php

namespace App\Services;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Chat use cases: listing, sending, editing and deleting messages.
 *
 * Every write is broadcast to the other connected clients through Reverb.
 */
class MessageService
{
    public const int PER_PAGE = 10;

    /**
     * Latest messages first, optionally filtered by authors, search term, and date range.
     *
     * @param  list<int>  $userIds
     * @param  \DateTimeInterface|null  $from
     * @param  \DateTimeInterface|null  $to
     * @return LengthAwarePaginator<int, Message>
     */
    public function paginate(
        array $userIds = [],
        ?string $search = null,
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?int $page = null,
    ): LengthAwarePaginator {
        return $this->filteredMessages($userIds, $search, $from, $to)
            ->paginate(self::PER_PAGE, page: $page)
            ->withQueryString();
    }

    /**
     * Chat only needs to know whether another page exists, not the total count.
     * Avoiding the paginator count query makes searches and date filters cheaper.
     *
     * @param  list<int>  $userIds
     * @param  \DateTimeInterface|null  $from
     * @param  \DateTimeInterface|null  $to
     * @return PaginatorContract<int, Message>
     */
    public function paginateForChat(
        array $userIds = [],
        ?string $search = null,
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?int $page = null,
    ): PaginatorContract {
        return $this->filteredMessages($userIds, $search, $from, $to)
            ->simplePaginate(self::PER_PAGE, page: $page)
            ->withQueryString();
    }

    /**
     * @param  list<int>  $userIds
     */
    private function filteredMessages(
        array $userIds,
        ?string $search,
        ?Carbon $from,
        ?Carbon $to,
    ): Builder {
        return Message::query()
            ->with('user:id,name,avatar')
            ->when($userIds, fn (Builder $query) => $query->fromUsers($userIds))
            ->when($search, fn (Builder $query) => $query->search($search))
            ->when($from, fn (Builder $query) => $query->fromDate($from))
            ->when($to, fn (Builder $query) => $query->toDate($to))
            ->latest()
            ->latest('id');
    }

    public function send(User $author, string $content): Message
    {
        $message = $this->createMessage($author, $content);

        broadcast(new MessageSent($message))->toOthers();

        return $message;
    }

    /**
     * Save immediately and publish after the HTTP response so a slow Reverb
     * connection cannot hold the sender's Livewire request open.
     */
    public function sendFromChat(User $author, string $content): Message
    {
        $message = $this->createMessage($author, $content);
        $event = new MessageSent($message);

        dispatch(function () use ($event): void {
            try {
                broadcast($event)->toOthers();
            } catch (Throwable $exception) {
                Log::warning('chat.broadcast_failed', [
                    'event' => $event::class,
                    'message_id' => $event->payload['id'] ?? null,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                report($exception);
            }
        })->afterResponse();

        return $message;
    }

    private function createMessage(User $author, string $content): Message
    {
        $message = $author->messages()->create(['content' => $content]);
        // Eager-load the author once so the broadcast payload builder's
        // loadMissing('user') is a no-op instead of an extra query on every
        // send. The broadcast only runs after the insert succeeds.
        $message->load('user');

        return $message;
    }

    public function update(Message $message, string $content): Message
    {
        $message->update(['content' => $content]);

        broadcast(new MessageUpdated($message))->toOthers();

        return $message;
    }

    public function delete(Message $message): void
    {
        $message->delete();

        broadcast(new MessageDeleted($message))->toOthers();
    }
}
