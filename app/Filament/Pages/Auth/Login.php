<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as AuthLogin;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class Login extends AuthLogin
{
    /**
     * Custom layout Blade view untuk menempatkan header di luar card.
     */
    protected static string $layout = 'filament.layouts.simple-custom';

    /**
     * Custom view untuk halaman login.
     */
    public string $view = 'filament.pages.auth.login';

    /**
     * Sembunyikan logo bawaan di dalam card (karena logo sudah di luar card).
     */
    public function hasLogo(): bool
    {
        return false;
    }

    /**
     * Menyimpan token Turnstile yang di-set dari JS via $wire.set().
     * Harus public agar Livewire bisa menerima nilai dari frontend.
     */
    public string $turnstileToken = '';

    /**
     * Override form schema untuk menambahkan Turnstile widget.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
                $this->getTurnstileFormComponent(),
            ]);
    }

    /**
     * Embed Blade view widget Turnstile.
     */
    protected function getTurnstileFormComponent(): Component
    {
        return View::make('filament.auth.turnstile-widget');
    }

    /**
     * Override authenticate: verifikasi Turnstile dulu, baru proses login.
     */
    public function authenticate(): ?LoginResponse
    {
        $this->verifyTurnstile();

        return parent::authenticate();
    }

    /**
     * Verifikasi token ke Cloudflare API (server-side).
     */
    protected function verifyTurnstile(): void
    {
        // Skip verifikasi Turnstile saat environment local (development)
        if (app()->environment('local')) {
            return;
        }

        if (empty($this->turnstileToken)) {
            $this->dispatch('reset-turnstile');
            throw ValidationException::withMessages([
                'data.email' => 'Captcha verification required. Please complete the security check.',
            ]);
        }

        $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret'   => config('turnstile.secret_key'),
            'response' => $this->turnstileToken,
            'remoteip' => request()->ip(),
        ]);

        if (! $response->successful() || ! ($response->json('success') ?? false)) {
            $this->turnstileToken = '';
            $this->dispatch('reset-turnstile');
            throw ValidationException::withMessages([
                'data.email' => 'Captcha verification failed. Please try again.',
            ]);
        }

        // Reset token setelah berhasil diverifikasi (one-time use)
        $this->turnstileToken = '';
    }

    /**
     * Reset Turnstile widget saat login gagal (salah email/password).
     */
    protected function throwFailureValidationException(): never
    {
        $this->dispatch('reset-turnstile');

        parent::throwFailureValidationException();
    }
}
