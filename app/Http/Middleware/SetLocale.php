<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the UI language for every request.
 *
 * Priority:
 *  1. An explicit choice stored in the session.
 *  2. The persistent, non-sensitive language cookie.
 *  3. The browser's Accept-Language header (with English as fallback).
 *
 * The resolved locale is also applied to Carbon so dates, relative timestamps
 * and weekday/month names are rendered in the same language as the UI.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('app.supported_locales', ['en']);

        $locale = $request->hasSession()
            ? $request->session()->get('locale')
            : null;

        if (! is_string($locale) || ! in_array($locale, $supported, true)) {
            $locale = $request->cookie('chat_locale');
        }

        if (! is_string($locale) || ! in_array($locale, $supported, true)) {
            $locale = $request->getPreferredLanguage($supported);
        }

        if (! is_string($locale) || ! in_array($locale, $supported, true)) {
            $locale = config('app.locale', 'en');
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
