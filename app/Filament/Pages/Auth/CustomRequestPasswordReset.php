<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Support\Icons\Heroicon;

class CustomRequestPasswordReset extends BaseRequestPasswordReset
{
    /**
     * Custom layout Blade view untuk menempatkan header di luar card.
     */
    protected static string $layout = 'filament.layouts.simple-custom';

    /**
     * Sembunyikan logo bawaan di dalam card (karena logo sudah di luar card).
     */
    public function hasLogo(): bool
    {
        return false;
    }

    public function getHeading(): string
    {
        return 'Forgot Password?';
    }

    public function getSubheading(): string
    {
        return 'Enter your registered email to get a reset link.';
    }

    protected function getRequestFormAction(): Action
    {
        return parent::getRequestFormAction()
            ->icon(Heroicon::Envelope);
    }
}
