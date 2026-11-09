<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\User;
use App\Services\GoogleAuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebAuthTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_login_screen_is_a_livewire_page(): void
    {
        Livewire::test(Login::class)
            ->assertOk()
            ->assertSee('Continue with Google');
    }

    #[Test]
    public function guests_are_sent_to_the_login_screen_from_the_home_page(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    #[Test]
    public function the_web_redirect_route_returns_googles_consent_screen(): void
    {
        $this->mock(GoogleAuthService::class)
            ->shouldReceive('redirectUrl')
            ->once()
            ->andReturn('https://accounts.google.com/o/oauth2/auth?state=web-state');

        $this->get('/auth/google/redirect')
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth?state=web-state');
    }

    #[Test]
    public function the_web_redirect_uses_select_account_prompt(): void
    {
        $this->mock(GoogleAuthService::class)
            ->shouldReceive('redirectUrl')
            ->once()
            ->andReturn('https://accounts.google.com/o/oauth2/auth?state=web-state&prompt=select_account');

        $this->get('/auth/google/redirect')
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth?state=web-state&prompt=select_account');
    }

    #[Test]
    public function the_web_callback_starts_a_session_and_redirects_to_chat(): void
    {
        $user = User::factory()->create();

        $this->mock(GoogleAuthService::class)
            ->shouldReceive('authenticate')
            ->once()
            ->andReturn($user);

        $this->get('/auth/google/callback?code=the-code&state=the-state')
            ->assertRedirect('/chat');

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function the_web_callback_requires_code_and_state(): void
    {
        $this->get('/auth/google/callback')
            ->assertRedirect()
            ->assertSessionHasErrors(['code', 'state']);
    }

    #[Test]
    public function an_invalid_web_state_is_rejected_without_logging_in(): void
    {
        $this->mock(GoogleAuthService::class)
            ->shouldReceive('authenticate')
            ->andThrow(new AuthenticationException('Invalid or expired OAuth state.'));

        $this->get('/auth/google/callback?code=the-code&state=forged')
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['google' => 'Invalid or expired OAuth state.']);

        $this->assertGuest();
    }

    #[Test]
    public function an_unexpected_oauth_failure_uses_the_generic_sign_in_message(): void
    {
        $this->mock(GoogleAuthService::class)
            ->shouldReceive('authenticate')
            ->andThrow(new AuthenticationException('Unexpected provider failure.'));

        $this->get('/auth/google/callback?code=the-code&state=valid')
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'google' => 'We could not complete sign-in. Please try again, or choose a different Google account.',
            ]);

        $this->assertGuest();
    }

    #[Test]
    public function signing_out_ends_the_browser_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    #[Test]
    public function the_chat_page_exposes_only_the_public_reverb_configuration(): void
    {
        config([
            'broadcasting.connections.reverb.key' => 'public-key',
            'reverb.client.host' => 'ws.example.test',
            'reverb.client.port' => 8080,
            'reverb.client.scheme' => 'https',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/chat')
            ->assertOk()
            ->assertSee('"key":"public-key"', escape: false)
            ->assertSee('"host":"ws.example.test"', escape: false)
            ->assertDontSee('change-me-secret');
    }
}
