<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\CustomRequestPasswordReset;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class InventoryPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('inventory')
            ->path('inventory')
            ->login(Login::class)
            ->passwordReset(CustomRequestPasswordReset::class)
            ->profile(EditProfile::class)
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label(fn () => auth()->user()?->name ?? 'Profile')
                    ->url(fn (): string => EditProfile::getUrl())
                    ->icon('heroicon-o-user-circle')
                    ->sort(-100),
            ])
            ->brandLogo(asset('logo.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('favicon.png'))
            ->colors([
                'primary' => Color::Blue,
            ])
            ->databaseNotifications()
            ->sidebarWidth('17rem')
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                'Asset Management',
                'Pengelolaan Barang',
                'Transaksi',
                'Reports',
            ])
            ->maxContentWidth(Width::Full)
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn () => view('filament.components.header-tools')
            )
            ->renderHook(
                PanelsRenderHook::CONTENT_START,
                fn () => view('filament.components.custom-stats-css')
            )
            ->renderHook(
                PanelsRenderHook::CONTENT_START,
                fn () => view('filament.components.sticky-table-filters-css')
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn () => view('filament.components.sidebar-header-subtitle')
            )
            ->viteTheme('resources/css/filament/inventory/theme.css')
            ->discoverResources(in: app_path('Filament/Inventory/Resources'), for: 'App\Filament\Inventory\Resources')
            ->discoverPages(in: app_path('Filament/Inventory/Pages'), for: 'App\Filament\Inventory\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Inventory/Widgets'), for: 'App\Filament\Inventory\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->renderHook(
                \Filament\View\PanelsRenderHook::SIDEBAR_FOOTER,
                fn () => view('filament.components.floating-collapse-btn')
            )
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
