<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Store the visitor's language choice.
 *
 * The session keeps the active locale for the current browser session, while a
 * long-lived preference cookie survives a complete logout/session reset. The
 * cookie holds only a two-letter locale code and is encrypted by Laravel.
 */
class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): JsonResponse|RedirectResponse
    {
        abort_unless(
            in_array($locale, config('app.supported_locales', ['en']), true),
            404,
        );

        $request->session()->put('locale', $locale);

        Cookie::queue('chat_locale', $locale, 60 * 24 * 365);

        $destination = route(auth()->check() ? 'chat' : 'login');

        return $request->expectsJson()
            ? response()->json(['redirect' => $destination])
            : redirect()->to($destination);
    }
}
