<?php

namespace Tests\Unit;

use App\Models\Message;
use App\Models\User;
use App\Policies\MessagePolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MessagePolicyTest extends TestCase
{
    private MessagePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new MessagePolicy;
    }

    #[Test]
    public function the_author_can_update_and_delete_their_message(): void
    {
        $author = $this->user(1);
        $message = $this->messageBy($author);

        $this->assertTrue($this->policy->update($author, $message));
        $this->assertTrue($this->policy->delete($author, $message));
    }

    #[Test]
    public function other_users_cannot_update_or_delete_the_message(): void
    {
        $message = $this->messageBy($this->user(1));
        $stranger = $this->user(2);

        $this->assertFalse($this->policy->update($stranger, $message));
        $this->assertFalse($this->policy->delete($stranger, $message));
    }

    private function user(int $id): User
    {
        return new User()->forceFill(['id' => $id]);
    }

    private function messageBy(User $author): Message
    {
        return new Message()->forceFill(['user_id' => $author->id]);
    }
}
