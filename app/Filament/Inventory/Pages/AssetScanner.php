<?php

namespace App\Filament\Inventory\Pages;

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
    
    protected ?string $subheading = 'Scan barcode untuk menemukan aset.';

    public static function shouldRegisterNavigation(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        
        return $user ? $user->hasPermissionTo('asset_scanner.use') : false;
    }

    public function mount()
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        
        abort_unless($user && $user->hasPermissionTo('asset_scanner.use'), 403);
    }

    public ?Asset $scannedAsset = null;
    public ?string $scanError = null;

    public function handleScanResult($inventoryNumber)
    {
        // Server-side validation
        if (empty($inventoryNumber)) {
            return;
        }

        $asset = Asset::with(['category', 'location', 'pic'])->withTrashed()->where('inventory_number', $inventoryNumber)->first();

        if ($asset) {
            if ($asset->trashed()) {
                $this->scannedAsset = null;
                $this->scanError = "Aset {$inventoryNumber} telah dihapus dari sistem.";
                Notification::make()
                    ->title('Aset Dihapus')
                    ->body($this->scanError)
                    ->danger()
                    ->send();
                return;
            }

            $this->scannedAsset = $asset;
            $this->scanError = null;

            Notification::make()
                ->title('Aset Ditemukan')
                ->success()
                ->send();
        } else {
            $this->scannedAsset = null;
            $this->scanError = "Tidak ada aset dengan nomor inventaris: {$inventoryNumber}";
            
            Notification::make()
                ->title('Aset Tidak Ditemukan')
                ->body($this->scanError)
                ->danger()
                ->send();
        }
    }
}
