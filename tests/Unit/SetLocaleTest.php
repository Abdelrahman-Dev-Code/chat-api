<?php

namespace Tests\Unit;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SetLocaleTest extends TestCase
{
    #[Test]
    public function it_falls_back_to_the_application_locale_when_no_supported_locale_is_available(): void
    {
        config([
            'app.locale' => 'en',
            'app.supported_locales' => [],
        ]);
        $request = Request::create('/', 'GET', [], ['chat_locale' => 'unsupported']);
        $request->headers->set('Accept-Language', '');

        $response = (new SetLocale)->handle($request, fn (): Response => new Response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('en', app()->getLocale());
    }
}
