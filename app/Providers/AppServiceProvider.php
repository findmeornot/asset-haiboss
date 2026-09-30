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
        
        \Filament\Support\Facades\FilamentIcon::register([
            'panels::sidebar.collapse-button' => 'heroicon-o-bars-3-bottom-right',
            'panels::sidebar.collapse-button.rtl' => 'heroicon-o-bars-3-bottom-left',
            'panels::sidebar.expand-button' => 'heroicon-o-bars-3',
            'panels::sidebar.expand-button.rtl' => 'heroicon-o-bars-3',
        ]);
        \Filament\Tables\Table::configureUsing(
            fn (\Filament\Tables\Table $table) => $table->paginationPageOptions([10, 25, 50, 100, 'all'])
        );

        // Dropdown Gedung/Ruangan sebaris dengan search/filter/kolom (paling kiri).
        \Filament\Support\Facades\FilamentView::registerRenderHook(
            \Filament\Tables\View\TablesRenderHook::TOOLBAR_START,
            fn (): \Illuminate\Contracts\View\View => view('filament.tables.campus-location-toolbar'),
            scopes: [
                \App\Filament\Inventory\Resources\AssetResource\Pages\ListAssets::class,
                \App\Filament\Inventory\Resources\UnifiedItemResource\Pages\ListUnifiedItems::class,
                \App\Filament\Inventory\Resources\AssetCategoryResource\Pages\ListAssetCategory::class,
                \App\Filament\Inventory\Resources\InventoryCategoryResource\Pages\ListInventoryCategory::class,
                \App\Filament\Inventory\Resources\SupplyCategoryResource\Pages\ListSupplyCategory::class,
            ],
        );

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
