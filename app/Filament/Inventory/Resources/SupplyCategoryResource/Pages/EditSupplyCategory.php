<?php

namespace App\Filament\Inventory\Resources\SupplyCategoryResource\Pages;

use App\Filament\Inventory\Resources\SupplyCategoryResource;
use App\Models\Asset;
use Filament\Actions;
use Filament\Forms\Components;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Halaman edit khusus Barang Habis Pakai (BHP).
 *
 * Scope: hanya field yang relevan untuk BHP — data umum barang,
 * kondisi, lokasi, dan FOTO. Tidak menyentuh purchase/invoice
 * karena alur keuangan BHP ditangani terpisah.
 */
class EditSupplyCategory extends EditRecord
{
    protected static string $resource = SupplyCategoryResource::class;

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Edit Barang Habis Pakai: ' . $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => SupplyCategoryResource::getUrl('index')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return SupplyCategoryResource::getUrl('view', ['record' => $this->record]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Informasi Barang')
                ->description('Edit data umum barang habis pakai.')
                ->schema([
                    Components\TextInput::make('name')
                        ->label('Nama Barang')
                        ->required()
                        ->maxLength(255),
                    Components\TextInput::make('brand')
                        ->label('Merk / Tipe')
                        ->maxLength(255),
                    Components\TextInput::make('serial_number')
                        ->label('No. Seri')
                        ->maxLength(255),
                    Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'stock'                    => 'Stok (Gudang)',
                            'active'                   => 'Aktif / Digunakan',
                            'borrowed'                 => 'Dipinjam',
                            'maintenance'              => 'Dalam Perbaikan',
                            'lost'                     => 'Hilang',
                            'disposed'                 => 'Dihapuskan / Musnah',
                            'destroyed'                => 'Dimusnahkan',
                        ])
                        ->required(),
                    Components\Select::make('kondisi')
                        ->label('Kondisi')
                        ->options([
                            'good'         => 'Baik',
                            'minor_damage' => 'Rusak Ringan',
                            'major_damage' => 'Rusak Berat',
                        ])
                        ->required(),
                    Components\Textarea::make('notes')
                        ->label('Keterangan')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Lokasi & PIC')
                ->schema([
                    Components\Select::make('campus_id')
                        ->label('Gedung / Kampus')
                        ->relationship('campus', 'name')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(fn ($set) => $set('location_id', null)),
                    Components\Select::make('location_id')
                        ->label('Ruangan / Lokasi')
                        ->relationship(
                            'location',
                            'name',
                            fn ($query, $get) => $query->when(
                                $get('campus_id'),
                                fn ($q, $campusId) => $q->where('campus_id', $campusId)
                            )
                        )
                        ->searchable()
                        ->preload(),
                    Components\Select::make('pic_id')
                        ->label('PIC (Penanggungjawab)')
                        ->relationship('pic', 'name')
                        ->searchable()
                        ->preload(),
                ])
                ->columns(2),

            Section::make('Foto Barang')
                ->description('Tambah atau hapus foto barang. Maksimal 3 foto.')
                ->schema([
                    Components\FileUpload::make('asset_photos')
                        ->disk('s3')
                        ->label('Foto Barang')
                        ->multiple()
                        ->maxFiles(3)
                        ->image()
                        ->imageEditor()
                        ->imageResizeMode('contain')
                        ->imageResizeTargetWidth('2000')
                        ->imageResizeTargetHeight('2000')
                        ->maxSize(5120) // 5MB per file
                        ->directory('asset-photos')
                        ->panelLayout('grid')
                        ->appendFiles()
                        ->formatStateUsing(function ($record) {
                            if (! $record) return [];
                            return $record->photos->sortBy('sort_order')->pluck('file_path')->toArray();
                        })
                        ->saveRelationshipsUsing(function ($component, $state, $record) {
                            $existingPaths = $record->photos->pluck('file_path')->toArray();
                            $newPaths      = array_values($state ?? []);

                            // Hapus foto yang dihilangkan user
                            foreach (array_diff($existingPaths, $newPaths) as $path) {
                                $record->photos()->where('file_path', $path)->first()?->delete();
                            }

                            // Tambah foto baru
                            $disk = Storage::disk('s3');
                            foreach (array_diff($newPaths, $existingPaths) as $path) {
                                $record->photos()->create([
                                    'file_path' => $path,
                                    'file_size' => $disk->exists($path) ? $disk->size($path) : null,
                                    'mime_type' => $disk->exists($path) ? $disk->mimeType($path) : null,
                                ]);
                            }

                            // Update sort_order sesuai urutan yang dipilih user
                            foreach ($newPaths as $index => $path) {
                                $record->photos()->where('file_path', $path)->update(['sort_order' => $index]);
                            }
                        })
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
