<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class AssetScanner extends Page
{
    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-bars-4';
    }
    
    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return 'Asset Management';
    }
    
    protected string $view = 'filament.pages.asset-scanner';
    
    protected static ?string $title = 'Scanner Aset';

    public static function shouldRegisterNavigation(): bool
    {
        // Require permission from milestone 03
        return Auth::user()->hasPermissionTo('asset_scanner.use');
    }

    public function mount()
    {
        abort_unless(Auth::user()->hasPermissionTo('asset_scanner.use'), 403);
    }

    public function handleScanResult($inventoryNumber)
    {
        // Server-side validation
        if (empty($inventoryNumber)) {
            return;
        }

        $asset = Asset::withTrashed()->where('inventory_number', $inventoryNumber)->first();

        if ($asset) {
            if ($asset->trashed()) {
                Notification::make()
                    ->title('Aset Dihapus')
                    ->body("Aset {$inventoryNumber} telah dihapus dari sistem (Soft Deleted).")
                    ->danger()
                    ->send();
                return;
            }

            Notification::make()
                ->title('Aset Ditemukan')
                ->success()
                ->send();
                
            return redirect()->to(\App\Filament\Resources\AssetResource::getUrl('view', ['record' => $asset]));
        } else {
            Notification::make()
                ->title('Aset Tidak Ditemukan')
                ->body("Tidak ada aset dengan nomor inventaris: {$inventoryNumber}")
                ->danger()
                ->send();
        }
    }
}
