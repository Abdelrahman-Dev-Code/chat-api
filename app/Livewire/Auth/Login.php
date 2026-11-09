<?php

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Sign-in screen for the browser (session-based) chat UI.
 */
#[Layout('layouts.app')]
class Login extends Component
{
    public function render(): View
    {
        return view('livewire.auth.login')->title(__('chat.meta.login_title'));
    }
}
