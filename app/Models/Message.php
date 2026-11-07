<?php

namespace App\Models;

use App\Policies\MessagePolicy;
use Database\Factories\MessageFactory;
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
