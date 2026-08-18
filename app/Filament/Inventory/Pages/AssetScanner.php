<?php

namespace App\Filament\Inventory\Pages;

use App\Models\Asset;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Components;

class AssetScanner extends Page implements HasForms
{
    use InteractsWithForms;
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
    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Components\FileUpload::make('asset_photos')
                    ->label('Upload Foto (Maks 3)')
                    ->multiple()
                    ->maxFiles(3)
                    ->image()
                    ->imageEditor()
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth('2000')
                    ->imageResizeTargetHeight('2000')
                    ->maxSize(5120) // 5MB limit
                    ->directory('asset-photos')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->helperText('Dukungan format: JPG, PNG, WebP (Maks 5MB per file). Anda dapat memilih beberapa foto dari galeri atau kamera.')
                    ->panelLayout('grid')
                    ->appendFiles()
            ])
            ->statePath('data');
    }

    public function savePhotos()
    {
        if (!$this->scannedAsset) {
            return;
        }

        $state = $this->form->getState();
        $record = $this->scannedAsset;

        $existingPaths = $record->photos->pluck('file_path')->toArray();
        $newPaths = array_values($state['asset_photos'] ?? []);

        $deletedPaths = array_diff($existingPaths, $newPaths);
        foreach ($deletedPaths as $path) {
            $record->photos()->where('file_path', $path)->first()?->delete();
        }

        $addedPaths = array_diff($newPaths, $existingPaths);
        foreach ($addedPaths as $path) {
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            $record->photos()->create([
                'file_path' => $path,
                'file_size' => $disk->exists($path) ? $disk->size($path) : null,
                'mime_type' => $disk->exists($path) ? $disk->mimeType($path) : null,
            ]);
        }

        foreach ($newPaths as $index => $path) {
            $record->photos()->where('file_path', $path)->update(['sort_order' => $index]);
        }

        Notification::make()
            ->title('Foto Berhasil Disimpan')
            ->success()
            ->send();
            
        // Refresh scanned asset relationships
        $this->scannedAsset->load('photos');
    }

    public function handleScanResult($barcode)
    {
        // Server-side validation
        if (empty($barcode)) {
            return;
        }

        $asset = Asset::with(['category', 'location', 'pic', 'photos'])->withTrashed()->where('barcode', $barcode)->first();

        if ($asset) {
            if ($asset->trashed()) {
                $this->scannedAsset = null;
                $this->scanError = "Aset dengan barcode {$barcode} telah dihapus dari sistem.";
                Notification::make()
                    ->title('Aset Dihapus')
                    ->body($this->scanError)
                    ->danger()
                    ->send();
                return;
            }

            $this->scannedAsset = $asset;
            $this->scanError = null;
            
            // Populate form state with existing photos
            $this->form->fill([
                'asset_photos' => $asset->photos->sortBy('sort_order')->pluck('file_path')->toArray(),
            ]);

            Notification::make()
                ->title('Aset Ditemukan')
                ->success()
                ->send();
        } else {
            $this->scannedAsset = null;
            $this->scanError = "Tidak ditemukan barang dengan barcode {$barcode}.";
            
            Notification::make()
                ->title('Aset Tidak Ditemukan')
                ->body($this->scanError)
                ->danger()
                ->send();
        }
    }
}
