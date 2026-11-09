<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProfileResource;
use App\Models\User;
use App\Services\GoogleAuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Sign-in with Google and API token management.
 */
class AuthController extends Controller
{
    public function __construct(private readonly GoogleAuthService $google) {}

    /**
     * Step 1: the client opens the returned Google consent-screen URL.
     */
    public function redirect(): JsonResponse
    {
        return response()->json(['url' => $this->google->redirectUrl()]);
    }

    /**
     * Step 2: Google redirects back here with `code` and `state`.
     * Returns a Sanctum bearer token for the API and the WebSocket auth endpoint.
     */
    public function callback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        $user = $this->google->authenticate($validated['state']);

        return response()->json([
            'token' => $user->createToken('google')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new ProfileResource($user),
        ]);
    }

    /**
     * Send the browser to Google's consent screen with session-bound state.
     */
    public function webRedirect(Request $request): RedirectResponse
    {
        $url = $this->google->redirectUrl(
            config('services.google.web_redirect'),
            $request->session()->token(),
        );

        return redirect()->away($url);
    }

    /**
     * Verify the web callback, start a session, and enter the chat.
     */
    public function webCallback(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        try {
            $user = $this->google->authenticate(
                $validated['state'],
                config('services.google.web_redirect'),
                $request->session()->token(),
            );
        } catch (AuthenticationException $exception) {
            return redirect()
                ->route('login')
                ->withErrors(['google' => $this->oauthError($exception->getMessage())]);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('chat'));
    }

    public function me(#[CurrentUser] User $user): ProfileResource
    {
        return new ProfileResource($user);
    }

    /**
     * Revoke the token used for the current request.
     */
    public function logout(#[CurrentUser] User $user): Response
    {
        $user->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function webLogout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function oauthError(string $message): string
    {
        return match ($message) {
            'Invalid or expired OAuth state.' => __('chat.oauth.errors.state'),
            'Unable to verify the Google authorization code.' => __('chat.oauth.errors.code'),
            'The Google account must have a verified e-mail address.' => __('chat.oauth.errors.unverified'),
            default => __('chat.oauth.errors.generic'),
        };
    }
}
