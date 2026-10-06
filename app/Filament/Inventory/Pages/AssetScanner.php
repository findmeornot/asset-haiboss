<?php

namespace App\Filament\Inventory\Pages;

use App\Models\Asset;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Schema;
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

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Components\TextInput::make('name')
                    ->label('Nama Barang')
                    ->required()
                    ->maxLength(255),

                Components\TextInput::make('brand')
                    ->label('Merk/Tipe')
                    ->maxLength(255),

                Components\Select::make('category_id')
                    ->label('Kategori')
                    ->options(fn () => \App\Models\Category::whereHas(
                        'classifications',
                        fn ($q) => $q->whereKey($this->scannedAsset?->classification_id)
                    )->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->disabled(fn () => $this->scannedAsset?->status === 'baru_dilaporkan'),

                Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'stock'               => 'Stok (Gudang)',
                        'active'              => 'Aktif / Digunakan',
                        'borrowed'            => 'Dipinjam',
                        'maintenance'         => 'Dalam Perbaikan',
                        'lost'                => 'Hilang',
                        'sold'                => 'Terjual',
                        'disposed'            => 'Dihapuskan / Musnah',
                        'baru_dilaporkan'     => 'Baru',
                        'menunggu_pengecekan' => 'Menunggu Pengecekan',
                    ])
                    ->required()
                    ->native(false)
                    ->disabled(fn () => $this->scannedAsset?->status === 'baru_dilaporkan'),

                Components\Select::make('kondisi')
                    ->label('Kondisi')
                    ->options([
                        'good'         => 'Baik',
                        'minor_damage' => 'Rusak Ringan',
                        'major_damage' => 'Rusak Berat',
                        'unchecked'    => 'Belum Dicek',
                    ])
                    ->required()
                    ->native(false)
                    ->disabled(fn () => $this->scannedAsset?->status === 'baru_dilaporkan'),

                Components\Select::make('pic_id')
                    ->label('Penanggung Jawab (PIC)')
                    ->options(fn () => \App\Models\Employee::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        Components\TextInput::make('name')->label('Nama Lengkap')->required(),
                        Components\TextInput::make('employee_number')->label('Nomor Induk / NIP')->nullable(),
                        Components\TextInput::make('department')->label('Departemen')->nullable(),
                    ])
                    ->createOptionUsing(fn (array $data) => \App\Models\Employee::create($data)->getKey()),

                Components\Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->rows(3)
                    ->nullable(),

                Components\FileUpload::make('asset_photos')
                    ->disk('s3')
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
                    ->helperText('Dukungan format: JPG, PNG, WebP (Maks 5MB per file). Ambil foto dari kamera atau pilih dari galeri.')
                    ->panelLayout('grid')
                    ->appendFiles()
                    // Tanpa atribut 'capture', browser mobile akan menampilkan
                    // menu pilihan: "Ambil Foto" (kamera) ATAU "Pilih dari Galeri"
                    ->extraInputAttributes(['accept' => 'image/*'])
            ])
            ->statePath('data');
    }

    public function saveDetails()
    {
        if (!$this->scannedAsset) {
            return;
        }

        $state = $this->form->getState();
        $record = $this->scannedAsset;

        $record->update([
            'name' => $state['name'],
            'brand' => $state['brand'],
            'category_id' => $state['category_id'],
            'status' => $state['status'],
            'kondisi' => $state['kondisi'],
            'pic_id' => $state['pic_id'],
            'keterangan' => $state['keterangan'] ?? null,
        ]);

        $existingPaths = $record->photos->pluck('file_path')->toArray();
        $newPaths = array_values($state['asset_photos'] ?? []);

        $deletedPaths = array_diff($existingPaths, $newPaths);
        foreach ($deletedPaths as $path) {
            $record->photos()->where('file_path', $path)->first()?->delete();
        }

        $addedPaths = array_diff($newPaths, $existingPaths);
        foreach ($addedPaths as $path) {
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = \Illuminate\Support\Facades\Storage::disk('s3');
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
            ->title('Perubahan Berhasil Disimpan')
            ->success()
            ->send();

        // Kosongkan form dan state aset agar kembali ke tampilan awal (siap scan baru)
        $this->scannedAsset = null;
        $this->scanError = null;
        $this->form->fill([]);
    }

    public function handleScanResult($barcode)
    {
        // Server-side validation
        if (empty($barcode)) {
            return;
        }

        $normalized = \App\Services\InventoryNumberGenerator::normalizeManualInput($barcode);

        $asset = Asset::with(['category', 'location', 'pic', 'photos'])
            ->withTrashed()
            ->where(fn ($q) => $q->where('barcode', $barcode)
                                 ->orWhere('inventory_number', $barcode)
                                 ->when($normalized, fn ($query) => $query->orWhere('inventory_number', $normalized))
            )
            ->first();

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
            
            // Populate form state with existing details & photos
            $this->form->fill([
                'name' => $asset->name,
                'brand' => $asset->brand,
                'category_id' => $asset->category_id,
                'status' => $asset->status,
                'kondisi' => $asset->kondisi,
                'pic_id' => $asset->pic_id,
                'keterangan' => $asset->keterangan,
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
