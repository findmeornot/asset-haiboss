<?php

namespace App\Filament\Auth\Responses;

use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Panel;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class PanelPriorityLoginResponse implements LoginResponseContract
{
    public function __construct(protected Panel $panel) {}

    public function toResponse($request): RedirectResponse | Redirector
    {
        return redirect()->intended($this->panel->getUrl());
    }
}
