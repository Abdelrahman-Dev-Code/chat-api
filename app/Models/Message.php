<?php

namespace App\Models;

use App\Policies\MessagePolicy;
use Database\Factories\MessageFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A chat message. Deleting a message is a soft delete (`deleted_at`).
 *
 * @property int $id
 * @property int $user_id
 * @property string $content
 */
#[Fillable(['content'])]
#[UsePolicy(MessagePolicy::class)]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The author of the message.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether the given user wrote this message.
     */
    public function isAuthoredBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * Limit the query to messages written by the given users.
     *
     * @param  Builder<self>  $query
     * @param  list<int>  $userIds
     */
    #[Scope]
    protected function fromUsers(Builder $query, array $userIds): void
    {
        $query->whereIn('user_id', $userIds);
    }

    /**
     * Limit the query to messages containing the search term, ignoring case.
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $query->whereRaw('LOWER(content) LIKE ?', ['%'.mb_strtolower($term).'%']);
    }

    /**
     * Limit the query to messages created on or after the given date.
     */
    #[Scope]
    protected function fromDate(Builder $query, DateTimeInterface $date): void
    {
        $query->where('created_at', '>=', $date);
    }

    /**
     * Limit the query to messages created on or before the given date.
     */
    #[Scope]
    protected function toDate(Builder $query, DateTimeInterface $date): void
    {
        $query->where('created_at', '<=', $date);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
        ];
    }
}
