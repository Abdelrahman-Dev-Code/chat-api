<?php

namespace App\Services;

use App\Models\User;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\Contracts\User as GoogleUser;
use Laravel\Socialite\Two\AbstractProvider;

/**
 * Google OAuth 2.0 sign-in for a stateless API.
 *
 * Protection against spoofed callbacks:
 *  1. A one-time random `state` is issued with the redirect URL and must come
 *     back unchanged on the callback (CSRF / replay protection).
 *  2. The profile is never taken from the client: the authorization `code` is
 *     exchanged server-to-server with Google using our client secret.
 *  3. Only Google accounts with a verified e-mail address are accepted.
 */
class GoogleAuthService
{
    private const string STATE_KEY_PREFIX = 'oauth:google:state:';

    private const int STATE_TTL_SECONDS = 600;

    public function __construct(
        private readonly Socialite $socialite,
        private readonly Cache $cache,
    ) {}

    /**
     * Build the Google consent-screen URL the client should open.
     */
    public function redirectUrl(): string
    {
        $state = Str::random(40);

        $this->cache->put(self::STATE_KEY_PREFIX.$state, true, self::STATE_TTL_SECONDS);

        return $this->google()
            ->with(['state' => $state])
            ->redirect()
            ->getTargetUrl();
    }

    /**
     * Validate the OAuth callback and return the matching local user.
     *
     * @throws AuthenticationException
     */
    public function authenticate(string $state): User
    {
        if (! $this->cache->pull(self::STATE_KEY_PREFIX.$state)) {
            throw new AuthenticationException('Invalid or expired OAuth state.');
        }

        try {
            $googleUser = $this->google()->user();
        } catch (GuzzleException) {
            throw new AuthenticationException('Unable to verify the Google authorization code.');
        }

        $this->ensureTrusted($googleUser);

        return $this->findOrCreateUser($googleUser);
    }

    /**
     * @throws AuthenticationException
     */
    private function ensureTrusted(GoogleUser $googleUser): void
    {
        $emailVerified = $googleUser->getRaw()['email_verified'] ?? false;

        if (blank($googleUser->getId()) || blank($googleUser->getEmail()) || $emailVerified !== true) {
            throw new AuthenticationException('The Google account must have a verified e-mail address.');
        }
    }

    private function findOrCreateUser(GoogleUser $googleUser): User
    {
        $email = $googleUser->getEmail() |> trim(...) |> mb_strtolower(...);

        // Match on the Google ID first, then link an existing account by its verified e-mail.
        $user = User::query()->where('google_id', $googleUser->getId())->first()
            ?? User::query()->where('email', $email)->first()
            ?? new User;

        $user->fill([
            'name' => $googleUser->getName() ?: $email,
            'email' => $email,
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return $user;
    }

    private function google(): AbstractProvider
    {
        /** @var AbstractProvider $driver */
        $driver = $this->socialite->driver('google');

        return $driver->stateless();
    }
}
