<?php
namespace App\Filament\Inventory\Pages;

use App\Models\Asset;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class StockOpnameScanner extends Page
{
    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-bars-4';
    }
    
    public static function shouldRegisterNavigation(): bool
    {
        return false; // Hidden from sidebar, accessed via Action
    }

    protected string $view = 'filament.pages.stock-opname-scanner';
    
    public ?StockOpname $opname = null;
    public $scannedAsset = null;
    public ?StockOpnameItem $savedItem = null;
    public ?StockOpnameItem $alreadyVerifiedItem = null;
    public $totalAssets = 0;
    public $checkedAssets = 0;
    
    public $condition = 'active';
    public $actual_location_id = null;
    public $notes = '';
    public $is_found = true;

    public function mount()
    {
        $opnameId = request()->query('opname');
        if (!$opnameId) {
            abort(404);
        }
        $this->opname = StockOpname::findOrFail($opnameId);
        $this->updateStats();
    }
    
    public function updateStats()
    {
        if ($this->opname) {
            $this->totalAssets = \App\Models\StockOpnameItem::where('stock_opname_id', $this->opname->id)->count();
            $this->checkedAssets = \App\Models\StockOpnameItem::where('stock_opname_id', $this->opname->id)->whereNotNull('checked_at')->count();
        }
    }

    public function handleScanResult($inventoryNumber)
    {
        if (empty($inventoryNumber)) return;
        
        $this->scannedAsset = null;
        $this->savedItem = null;
        $this->alreadyVerifiedItem = null;

        $asset = Asset::withTrashed()->where('inventory_number', $inventoryNumber)->first();

        if ($asset) {
            if ($asset->trashed()) {
                Notification::make()
                    ->title('Aset Dihapus')
                    ->body("Aset {$inventoryNumber} telah dihapus dari sistem (Soft Deleted) sehingga tidak valid untuk Stock Opname.")
                    ->danger()
                    ->send();
                return;
            }

            // Check if asset is part of the stock opname items
            $opnameItem = \App\Models\StockOpnameItem::where('stock_opname_id', $this->opname->id)
                ->where('asset_id', $asset->id)
                ->first();

            if (!$opnameItem) {
                Notification::make()
                    ->title('Aset Tidak Termasuk')
                    ->body("Aset ditemukan, tetapi tidak terdaftar dalam sesi Stock Opname ini.")
                    ->warning()
                    ->send();
                return;
            }

            if ($opnameItem->checked_at) {
                // Already checked
                $opnameItem->load(['asset', 'expectedLocation', 'actualLocation', 'checkedBy']);
                $this->alreadyVerifiedItem = $opnameItem;
            } else {
                // Ready to check
                $this->scannedAsset = $asset;
                $this->actual_location_id = $asset->location_id;
                $this->condition = $asset->status;
                $this->is_found = true;
                $this->notes = '';
            }
        } else {
            Notification::make()
                ->title('Tidak Ditemukan')
                ->body("Aset dengan nomor inventaris {$inventoryNumber} tidak ditemukan.")
                ->danger()
                ->send();
        }
    }

    public function saveVerification()
    {
        if (!$this->scannedAsset || !$this->opname) return;

        $this->savedItem = StockOpnameItem::updateOrCreate(
            [
                'stock_opname_id' => $this->opname->id,
                'asset_id' => $this->scannedAsset->id,
            ],
            [
                'scanned_inventory_number' => $this->scannedAsset->inventory_number,
                'is_found' => $this->is_found,
                'condition' => $this->condition,
                'expected_location_id' => $this->scannedAsset->location_id,
                'actual_location_id' => $this->actual_location_id,
                'notes' => $this->notes,
                'checked_by' => Auth::id(),
                'checked_at' => now(),
            ]
        );

        // Load relations for display
        $this->savedItem->load(['asset', 'expectedLocation', 'actualLocation', 'checkedBy']);
        
        $this->scannedAsset = null;
        $this->alreadyVerifiedItem = null;
        $this->updateStats();

        Notification::make()
            ->title('Verifikasi Tersimpan')
            ->success()
            ->send();
    }
    
    public function recheckItem()
    {
        if ($this->alreadyVerifiedItem) {
            $this->scannedAsset = $this->alreadyVerifiedItem->asset;
            $this->actual_location_id = $this->alreadyVerifiedItem->actual_location_id;
            $this->condition = $this->alreadyVerifiedItem->condition;
            $this->is_found = $this->alreadyVerifiedItem->is_found;
            $this->notes = $this->alreadyVerifiedItem->notes;
            
            $this->alreadyVerifiedItem = null;
            $this->savedItem = null;
        }
    }

    public function resetScanner()
    {
        $this->scannedAsset = null;
        $this->savedItem = null;
        $this->alreadyVerifiedItem = null;
        $this->is_found = true;
        $this->condition = 'active';
        $this->actual_location_id = null;
        $this->notes = '';
    }
}
