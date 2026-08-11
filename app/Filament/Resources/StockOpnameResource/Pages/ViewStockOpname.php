<?php
namespace App\Filament\Resources\StockOpnameResource\Pages;
use App\Filament\Resources\StockOpnameResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Infolists\Components;
use App\Models\StockOpnameItem;

class ViewStockOpname extends ViewRecord
{
    protected static string $resource = StockOpnameResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Rekapitulasi Stock Opname')
                    ->schema([
                        Components\TextEntry::make('total_asset')
                            ->label('Total Aset (Target)')
                            ->state(fn ($record) => StockOpnameItem::where('stock_opname_id', $record->id)->count()),
                        Components\TextEntry::make('found')
                            ->label('Ditemukan')
                            ->state(fn ($record) => StockOpnameItem::where('stock_opname_id', $record->id)->where('is_found', true)->count()),
                        Components\TextEntry::make('not_found')
                            ->label('Tidak Ditemukan')
                            ->state(fn ($record) => StockOpnameItem::where('stock_opname_id', $record->id)->where('is_found', false)->count()),
                        Components\TextEntry::make('good_condition')
                            ->label('Kondisi Baik')
                            ->state(fn ($record) => StockOpnameItem::where('stock_opname_id', $record->id)->where('condition', 'active')->count()),
                        Components\TextEntry::make('minor_damage')
                            ->label('Rusak Ringan')
                            ->state(fn ($record) => StockOpnameItem::where('stock_opname_id', $record->id)->where('condition', 'minor_damage')->count()),
                        Components\TextEntry::make('major_damage')
                            ->label('Rusak Berat')
                            ->state(fn ($record) => StockOpnameItem::where('stock_opname_id', $record->id)->where('condition', 'major_damage')->count()),
                        Components\TextEntry::make('location_mismatch')
                            ->label('Lokasi Tidak Sesuai')
                            ->state(fn ($record) => StockOpnameItem::where('stock_opname_id', $record->id)->whereRaw('expected_location_id != actual_location_id')->whereNotNull('actual_location_id')->count()),
                    ])->columns(3),
            ]);
    }
}
