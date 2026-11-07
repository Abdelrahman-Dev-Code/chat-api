<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProfileResource;
use App\Models\User;
use App\Services\GoogleAuthService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
}
