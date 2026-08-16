<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Auth\Responses\PanelPriorityLoginResponse;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as AuthLogin;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Timebox;
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
     * Override authenticate: verifikasi Turnstile dulu, lalu login tanpa
     * dibatasi ke panel tempat form login dibuka (beda dari default Filament
     * yang menolak login jika user tidak punya akses ke panel saat ini).
     * Redirect tujuan ditentukan dari panel yang benar-benar bisa diakses user.
     */
    public function authenticate(): ?LoginResponse
    {
        $this->verifyTurnstile();

        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        /** @var SessionGuard $authGuard */
        $authGuard = Filament::auth();

        $authProvider = $authGuard->getProvider();
        $credentials = $this->getCredentialsFromFormData($data);
        $remember = $data['remember'] ?? false;
        $timeboxDuration = (int) config('auth.timebox_duration', 200_000);

        $user = app(Timebox::class)->call(function (Timebox $timebox) use ($authProvider, $authGuard, $credentials, $remember): Authenticatable {
            $this->fireAttemptingEvent($authGuard, $credentials, $remember);

            $user = $authProvider->retrieveByCredentials($credentials);

            if ((! $user) || (! $authProvider->validateCredentials($user, $credentials))) {
                $this->fireFailedEvent($authGuard, $user, $credentials);
                $this->throwFailureValidationException();
            }

            $timebox->returnEarly();

            return $user;
        }, $timeboxDuration);

        $targetPanel = $this->resolveAccessiblePanel($user);

        if (
            (! $authGuard->attemptWhen($credentials, fn (): bool => $targetPanel !== null, $remember))
            || $targetPanel === null
        ) {
            $this->fireFailedEvent($authGuard, $user, $credentials);
            $this->throwFailureValidationException();
        }

        session()->regenerate();

        return new PanelPriorityLoginResponse($targetPanel);
    }

    /**
     * Tentukan panel tujuan berdasarkan Role user.
     */
    protected function resolveAccessiblePanel(Authenticatable $user): ?Panel
    {
        if (! ($user instanceof \App\Models\User)) {
            return Filament::getCurrentOrDefaultPanel();
        }

        // 1. Superadmin -> Admin Panel
        if ($user->hasRole('Superadmin')) {
            $panel = Filament::getPanel('admin', isStrict: false);
            if ($panel && $user->canAccessPanel($panel)) {
                return $panel;
            }
        }

        // 2. Tim Inventaris, Finance, Approver -> Inventory Panel
        if ($user->hasRole('Tim Inventaris') || $user->hasRole('Finance') || $user->hasRole('Approver')) {
            $panel = Filament::getPanel('inventory', isStrict: false);
            if ($panel && $user->canAccessPanel($panel)) {
                return $panel;
            }
        }

        // Fallback: kembalikan panel pertama yang user punya akses
        foreach (Filament::getPanels() as $panel) {
            if ($user->canAccessPanel($panel)) {
                return $panel;
            }
        }

        return Filament::getCurrentOrDefaultPanel();
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
