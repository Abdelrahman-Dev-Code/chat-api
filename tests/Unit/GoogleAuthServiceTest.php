<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\GoogleAuthService;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoogleAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = Mockery::mock(GoogleProvider::class);
        $this->provider->shouldReceive('stateless')->andReturnSelf();

        $this->mock(Socialite::class)->shouldReceive('driver')->with('google')->andReturn($this->provider);
    }

    #[Test]
    public function the_redirect_url_carries_a_one_time_state(): void
    {
        $service = $this->realGoogleService();

        $url = $service->redirectUrl();

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame(40, strlen($query['state']));
        $this->assertTrue(app(Cache::class)->has('oauth:google:state:'.$query['state']));
    }

    #[Test]
    public function an_unknown_state_is_rejected(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid or expired OAuth state.');

        app(GoogleAuthService::class)->authenticate('forged-state');
    }

    #[Test]
    public function a_state_can_only_be_used_once(): void
    {
        $state = $this->issueState();
        $this->googleReturns($this->googleUser());
        $service = app(GoogleAuthService::class);

        $service->authenticate($state);

        $this->expectException(AuthenticationException::class);
        $service->authenticate($state);
    }

    #[Test]
    public function a_new_user_is_created_from_a_verified_google_account(): void
    {
        $this->googleReturns($this->googleUser(email: ' Sara@Example.com '));

        $user = app(GoogleAuthService::class)->authenticate($this->issueState());

        $this->assertTrue($user->exists);
        $this->assertSame('sara@example.com', $user->email);
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame('Sara', $user->name);
        $this->assertNotNull($user->email_verified_at);
    }

    #[Test]
    public function a_returning_user_is_matched_by_google_id_and_refreshed(): void
    {
        $existing = User::factory()->create(['google_id' => 'google-123', 'name' => 'Old name']);
        $this->googleReturns($this->googleUser(name: 'New name'));

        $user = app(GoogleAuthService::class)->authenticate($this->issueState());

        $this->assertTrue($user->is($existing));
        $this->assertSame('New name', $user->name);
        $this->assertSame(1, User::count());
    }

    #[Test]
    public function an_existing_account_is_linked_by_verified_email(): void
    {
        $existing = User::factory()->create(['email' => 'sara@example.com', 'google_id' => null]);
        $this->googleReturns($this->googleUser());

        $user = app(GoogleAuthService::class)->authenticate($this->issueState());

        $this->assertTrue($user->is($existing));
        $this->assertSame('google-123', $user->google_id);
    }

    #[Test]
    public function an_unverified_google_email_is_rejected(): void
    {
        $this->googleReturns($this->googleUser(emailVerified: false));

        $this->expectException(AuthenticationException::class);

        try {
            app(GoogleAuthService::class)->authenticate($this->issueState());
        } finally {
            $this->assertSame(0, User::count());
        }
    }

    #[Test]
    public function a_code_rejected_by_google_is_reported_as_unauthenticated(): void
    {
        $this->provider->shouldReceive('user')->andThrow(
            new ClientException('invalid_grant', new Request('POST', 'https://oauth2.googleapis.com/token'), new Response(400)),
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Unable to verify the Google authorization code.');

        app(GoogleAuthService::class)->authenticate($this->issueState());
    }

    private function issueState(): string
    {
        $state = 'valid-state-'.uniqid();
        app(Cache::class)->put('oauth:google:state:'.$state, true, 60);

        return $state;
    }

    private function googleReturns(SocialiteUser $user): void
    {
        $this->provider->shouldReceive('user')->andReturn($user);
    }

    private function googleUser(string $email = 'sara@example.com', string $name = 'Sara', bool $emailVerified = true): SocialiteUser
    {
        return new SocialiteUser()
            ->setRaw(['sub' => 'google-123', 'email' => $email, 'email_verified' => $emailVerified])
            ->map(['id' => 'google-123', 'name' => $name, 'email' => $email, 'avatar' => 'https://example.com/a.png']);
    }

    /**
     * A service wired to the real Socialite manager, to check the generated URL.
     */
    private function realGoogleService(): GoogleAuthService
    {
        config(['services.google' => ['client_id' => 'client-id', 'client_secret' => 'secret', 'redirect' => 'http://localhost/callback']]);

        return new GoogleAuthService(new SocialiteManager($this->app), app(Cache::class));
    }
}
