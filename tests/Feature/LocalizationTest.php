<?php

namespace Tests\Feature;

use App\Livewire\Chat;
use App\Models\Message;
use App\Models\User;
use App\Services\GoogleAuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function english_is_the_default_direction_and_copy(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('lang="en"', escape: false)
            ->assertSee('dir="ltr"', escape: false)
            ->assertSee('Continue with Google');
    }

    #[Test]
    public function a_guest_can_switch_to_arabic_and_the_choice_is_persisted(): void
    {
        $this->post('/locale/ar')
            ->assertRedirect('/login')
            ->assertSessionHas('locale', 'ar')
            ->assertCookie('chat_locale', 'ar');
    }

    #[Test]
    public function a_locale_switch_can_return_a_navigation_target_as_json(): void
    {
        $this->withHeader('Accept', 'application/json')
            ->post('/locale/ar')
            ->assertOk()
            ->assertJson(['redirect' => route('login')])
            ->assertSessionHas('locale', 'ar')
            ->assertCookie('chat_locale', 'ar');
    }

    #[Test]
    public function an_authenticated_user_returns_to_the_chat_after_switching_language(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/locale/en')
            ->assertRedirect('/chat')
            ->assertSessionHas('locale', 'en');
    }

    #[Test]
    public function unsupported_locales_are_rejected(): void
    {
        $this->post('/locale/fr')->assertNotFound();
        $this->post('/locale/AR')->assertNotFound();
    }

    #[Test]
    public function the_persistent_language_cookie_wins_over_the_browser_language(): void
    {
        $this->withCookie('chat_locale', 'ar')
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get('/login')
            ->assertOk()
            ->assertSee('dir="rtl"', escape: false)
            ->assertSee('المتابعة باستخدام Google');
    }

    #[Test]
    public function an_explicit_session_choice_wins_over_the_cookie(): void
    {
        $this->withSession(['locale' => 'en'])
            ->withCookie('chat_locale', 'ar')
            ->get('/login')
            ->assertOk()
            ->assertSee('dir="ltr"', escape: false)
            ->assertSee('Continue with Google');
    }

    #[Test]
    public function the_browser_language_is_used_when_no_choice_exists(): void
    {
        $this->withHeader('Accept-Language', 'ar-SA,ar;q=0.9,en;q=0.5')
            ->get('/login')
            ->assertOk()
            ->assertSee('dir="rtl"', escape: false)
            ->assertSee('أهلًا بعودتك');
    }

    #[Test]
    public function the_login_screen_is_fully_localized_in_arabic(): void
    {
        $this->withSession(['locale' => 'ar'])
            ->get('/login')
            ->assertOk()
            ->assertSee('lang="ar"', escape: false)
            ->assertSee('dir="rtl"', escape: false)
            ->assertSee('تسجيل الدخول')
            ->assertSee('أهلًا بعودتك')
            ->assertSee('المتابعة باستخدام Google')
            ->assertSee('OAuth 2.0')
            ->assertDontSee('Continue with Google')
            ->assertDontSee('One calm room');
    }

    #[Test]
    public function the_chat_workspace_renders_right_to_left_with_localized_copy(): void
    {
        $user = User::factory()->create();
        Message::factory()->for($user)->create(['content' => 'مرحبا بالجميع']);

        $this->actingAs($user)
            ->withSession(['locale' => 'ar'])
            ->get('/chat')
            ->assertOk()
            ->assertSee('lang="ar"', escape: false)
            ->assertSee('dir="rtl"', escape: false)
            ->assertSee('محادثة المجتمع')
            ->assertSee('مرحبا بالجميع')
            ->assertSee('اختصار البحث: Ctrl+K أو ⌘K')
            ->assertSee('اليوم')
            ->assertSee('window.chatLocale = "ar"', escape: false)
            ->assertSee('window.chatDirection = "rtl"', escape: false);
    }

    #[Test]
    public function validation_errors_are_localized_in_arabic(): void
    {
        app()->setLocale('ar');

        Livewire::actingAs(User::factory()->create())
            ->test(Chat::class)
            ->set('newMessage', '   ')
            ->call('send')
            ->assertHasErrors(['newMessage' => 'حقل الرسالة مطلوب.']);
    }

    #[Test]
    public function oauth_failures_are_mapped_to_localized_copy(): void
    {
        $this->withSession(['locale' => 'ar']);

        $this->mock(GoogleAuthService::class)
            ->shouldReceive('authenticate')
            ->once()
            ->andThrow(new AuthenticationException('The Google account must have a verified e-mail address.'));

        $this->get('/auth/google/callback?code=the-code&state=the-state')
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'google' => 'يجب أن يمتلك حساب Google بريدًا إلكترونيًا موثّقًا.',
            ]);

        $this->assertGuest();
    }

    #[Test]
    public function signing_out_clears_the_session_locale_but_keeps_the_language_cookie(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->withSession(['locale' => 'ar'])
            ->post('/logout');

        $response
            ->assertRedirect('/login')
            ->assertSessionMissing('locale')
            ->assertCookieMissing('chat_locale');

        $this->withCookie('chat_locale', 'ar')
            ->get('/login')
            ->assertOk()
            ->assertSee('dir="rtl"', escape: false);
    }
}
