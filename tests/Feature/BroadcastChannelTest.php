<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BroadcastChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Use the real Reverb (Pusher protocol) broadcaster; signing is local, no server needed.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        Broadcast::purge('reverb');

        // Channels are registered on the broadcaster resolved at boot, so register them again.
        require base_path('routes/channels.php');
    }

    #[Test]
    public function an_authenticated_user_can_join_the_chat_channel(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-chat'])
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    #[Test]
    public function guests_cannot_join_the_chat_channel(): void
    {
        $this->postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-chat'])
            ->assertUnauthorized();
    }
}
