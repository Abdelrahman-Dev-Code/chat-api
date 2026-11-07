<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GoogleAuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_the_google_consent_url(): void
    {
        $this->mock(GoogleAuthService::class)->shouldReceive('redirectUrl')->andReturn('https://accounts.google.com/o/oauth2/auth?state=abc');

        $this->getJson('/api/auth/google/redirect')
            ->assertOk()
            ->assertJson(['url' => 'https://accounts.google.com/o/oauth2/auth?state=abc']);
    }

    #[Test]
    public function the_callback_requires_code_and_state(): void
    {
        $this->getJson('/api/auth/google/callback')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'state']);
    }

    #[Test]
    public function a_valid_callback_returns_a_working_api_token(): void
    {
        $user = User::factory()->create();
        $this->mock(GoogleAuthService::class)->shouldReceive('authenticate')->with('the-state')->andReturn($user);

        $response = $this->getJson('/api/auth/google/callback?code=the-code&state=the-state')
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id);

        $this->withToken($response->json('token'))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    #[Test]
    public function a_spoofed_callback_is_rejected(): void
    {
        $this->mock(GoogleAuthService::class)
            ->shouldReceive('authenticate')
            ->andThrow(new AuthenticationException('Invalid or expired OAuth state.'));

        $this->getJson('/api/auth/google/callback?code=x&state=forged')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Invalid or expired OAuth state.']);
    }

    #[Test]
    public function logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('google')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
    }

    #[Test]
    public function the_profile_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }
}
