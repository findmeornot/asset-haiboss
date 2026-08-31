<?php

namespace App\Filament\Inventory\Resources;

use App\Filament\Inventory\Resources\InventoryBalanceResource\Pages;
use App\Models\InventoryBalance;
use App\Models\AuditLog;
use App\Enums\AuditAction;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class InventoryBalanceResource extends Resource
{
    protected static ?string $model = InventoryBalance::class;
    protected static ?int $navigationSort = 4;

    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-archive-box';
    }

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return 'PENGELOLAAN BARANG';
    }

    public static function getModelLabel(): string
    {
        return 'Persediaan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Persediaan';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('campus.name')
                    ->label('Kampus / Gedung')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location.name')
                    ->label('Lokasi / Ruangan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Stok Tersedia')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'danger',
                        $state <= 10 => 'warning',
                        default => 'success',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\Action::make('stock_out')
                    ->label('Gunakan Stok')
                    ->icon('heroicon-o-minus-circle')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('sub_barcodes')
                            ->label('Scan/Pilih Barcode Unit')
                            ->multiple()
                            ->searchable()
                            ->options(fn (InventoryBalance $record) => 
                                $record->units()->where('status', 'available')->pluck('sub_barcode', 'sub_barcode')
                            )
                            ->required()
                            ->minItems(1)
                            ->maxItems(fn (InventoryBalance $record) => $record->quantity)
                            ->helperText('Pilih unit spesifik yang akan dikeluarkan.'),
                        Forms\Components\Textarea::make('reason')
                            ->label('Alasan / Tujuan Penggunaan')
                            ->required(),
                    ])
                    ->action(function (InventoryBalance $record, array $data) {
                        $subBarcodes = $data['sub_barcodes'];
                        $quantity = count($subBarcodes);
                        $reason = $data['reason'] ?? '';

                        try {
                            DB::transaction(function () use ($record, $subBarcodes, $quantity, $reason) {
                                // 1. Pessimistic Locking on Balance
                                $lockedBalance = InventoryBalance::where('id', $record->id)->lockForUpdate()->first();

                                // 2. Lock and check units
                                $lockedUnits = \App\Models\InventoryBalanceUnit::where('inventory_balance_id', $record->id)
                                    ->whereIn('sub_barcode', $subBarcodes)
                                    ->lockForUpdate()
                                    ->get();
                                
                                if ($lockedUnits->count() !== $quantity) {
                                    throw new \Exception("Beberapa barcode tidak valid atau bukan milik master barang ini.");
                                }
                                
                                $usedUnits = $lockedUnits->where('status', '!=', 'available');
                                if ($usedUnits->count() > 0) {
                                    throw new \Exception("Beberapa barcode sudah digunakan (Stock OUT) atau tidak tersedia.");
                                }

                                // 3. Update Units
                                \App\Models\InventoryBalanceUnit::whereIn('id', $lockedUnits->pluck('id'))->update(['status' => 'used']);

                                $oldQuantity = $lockedBalance->quantity;
                                $newQuantity = $oldQuantity - $quantity;
                                
                                if ($newQuantity < 0) {
                                    throw new \Exception("Stok tidak mencukupi. Stok saat ini: {$oldQuantity}");
                                }

                                // 4. Decrement Balance
                                $lockedBalance->quantity = $newQuantity;
                                $lockedBalance->save();

                                // 5. Audit Trail
                                AuditLog::create([
                                    'action' => AuditAction::UPDATED,
                                    'auditable_type' => InventoryBalance::class,
                                    'auditable_id' => $record->id,
                                    'user_id' => Auth::id(),
                                    'old_values' => ['quantity' => $oldQuantity],
                                    'new_values' => ['quantity' => $newQuantity],
                                    'metadata' => [
                                        'reason' => $reason,
                                        'usage_quantity' => $quantity,
                                        'sub_barcodes' => $subBarcodes,
                                        'transaction_type' => 'stock_out'
                                    ],
                                ]);
                            });

                            Notification::make()
                                ->title('Berhasil')
                                ->body("Stok berhasil dikeluarkan sebanyak {$quantity} unit.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (InventoryBalance $record): bool => $record->quantity > 0),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryBalances::route('/'),
        ];
    }
}
