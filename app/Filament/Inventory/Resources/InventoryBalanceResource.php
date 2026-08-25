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

    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-archive-box';
    }

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return 'Asset Management';
    }

    public static function getModelLabel(): string
    {
        return 'Stok Persediaan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Stok Persediaan';
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
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\Action::make('stock_out')
                    ->label('Gunakan Stok')
                    ->icon('heroicon-o-minus-circle')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Jumlah yang Digunakan')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(fn (InventoryBalance $record) => $record->quantity)
                            ->required(),
                        Forms\Components\Textarea::make('reason')
                            ->label('Alasan / Tujuan Penggunaan')
                            ->required(),
                    ])
                    ->action(function (InventoryBalance $record, array $data) {
                        $quantity = (int) $data['quantity'];
                        $reason = $data['reason'] ?? '';

                        try {
                            DB::transaction(function () use ($record, $quantity, $reason) {
                                // 1. Pessimistic Locking
                                $lockedBalance = InventoryBalance::where('id', $record->id)->lockForUpdate()->first();

                                // 2. Re-check quantity after locking
                                if ($lockedBalance->quantity < $quantity) {
                                    throw new \Exception("Stok tidak mencukupi. Stok saat ini: {$lockedBalance->quantity}");
                                }

                                $oldQuantity = $lockedBalance->quantity;
                                $newQuantity = $oldQuantity - $quantity;

                                // 3. Decrement
                                $lockedBalance->quantity = $newQuantity;
                                $lockedBalance->save();

                                // 4. Audit Trail
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
                                        'transaction_type' => 'stock_out'
                                    ],
                                ]);
                            });

                            Notification::make()
                                ->title('Berhasil')
                                ->body("Stok berhasil dikeluarkan sebanyak {$quantity}.")
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
