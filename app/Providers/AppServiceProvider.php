<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use App\Models\Asset;
use App\Models\User;
use App\Observers\AssetObserver;
use BezhanSalleh\PanelSwitch\PanelSwitch;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::subscribe(\App\Listeners\AuthEventsSubscriber::class);
        \App\Models\User::observe(\App\Observers\UserObserver::class);
        
        
        if (!app()->environment('local')) {
            URL::forceScheme('https');
        }

        PanelSwitch::configureUsing(function (PanelSwitch $panelSwitch) {
            $panelSwitch
                ->renderHook('panel-switch::disabled')
                ->labels([
                    'admin' => 'Admin',
                    'inventory' => 'Inventory',
                ])
                ->icons([
                    'admin' => 'heroicon-o-shield-check',
                    'inventory' => 'heroicon-o-archive-box',
                ])
                ->panels(function (): array {
                    /** @var User|null $user */
                    $user = auth()->user();

                    if (! $user) {
                        return [];
                    }

                    return collect(\Filament\Facades\Filament::getPanels())
                        ->filter(fn ($panel) => $user->canAccessPanel($panel))
                        ->keys()
                        ->values()
                        ->all();
                });
        });
    }
}
