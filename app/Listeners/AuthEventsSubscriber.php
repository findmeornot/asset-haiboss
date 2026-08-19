<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;
use App\Services\AuditLogger;

class AuthEventsSubscriber
{
    /**
     * Handle user login events.
     */
    public function handleUserLogin(Login $event): void
    {
        AuditLogger::log(\App\Enums\AuditAction::LOGIN, userId: $event->user->id);
    }

    /**
     * Handle user logout events.
     */
    public function handleUserLogout(Logout $event): void
    {
        if ($event->user) {
            AuditLogger::log(\App\Enums\AuditAction::LOGOUT, userId: $event->user->id);
        }
    }

    /**
     * Handle user failed login events.
     */
    public function handleUserFailedLogin(Failed $event): void
    {
        $credentials = $event->credentials;
        if (is_array($credentials)) {
            $credentials = \Illuminate\Support\Arr::except($credentials, \App\Services\AuditLogger::SENSITIVE_FIELDS);
        }

        AuditLogger::log(
            action: \App\Enums\AuditAction::LOGIN_FAILED,
            metadata: ['credentials' => $credentials],
            userId: null
        );
    }

    /**
     * Handle user password reset events.
     */
    public function handleUserPasswordReset(PasswordReset $event): void
    {
        AuditLogger::log(
            action: \App\Enums\AuditAction::PASSWORD_RESET,
            model: $event->user,
            userId: $event->user->id
        );
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleUserLogin',
            Logout::class => 'handleUserLogout',
            Failed::class => 'handleUserFailedLogin',
            PasswordReset::class => 'handleUserPasswordReset',
        ];
    }
}
