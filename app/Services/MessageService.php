<?php

namespace App\Services;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Chat use cases: listing, sending, editing and deleting messages.
 *
 * Every write is broadcast to the other connected clients through Reverb.
 */
class MessageService
{
    public const int PER_PAGE = 10;

    /**
     * Latest messages first, optionally limited to the given authors.
     *
     * @param  list<int>  $userIds
     * @return LengthAwarePaginator<int, Message>
     */
    public function paginate(array $userIds = []): LengthAwarePaginator
    {
        return Message::query()
            ->with('user')
            ->when($userIds, fn (Builder $query) => $query->fromUsers($userIds))
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    public function send(User $author, string $content): Message
    {
        $message = $author->messages()->create(['content' => $content]);

        broadcast(new MessageSent($message))->toOthers();

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
