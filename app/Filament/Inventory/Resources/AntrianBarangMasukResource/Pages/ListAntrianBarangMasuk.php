<?php

namespace App\Filament\Inventory\Resources\AntrianBarangMasukResource\Pages;

use App\Filament\Inventory\Resources\AntrianBarangMasukResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAntrianBarangMasuk extends ListRecords
{
    protected static string $resource = AntrianBarangMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'baru_dilaporkan';
    }

    /**
     * Tab mengikuti siklus hidup alur intake:
     * 1. Baru Dilaporkan (OB selesai lapor, Finance belum lengkapi)
     * 2. Menunggu Pengecekan (Finance sudah lengkapi, menunggu OB cek fisik)
     * 3. Selesai (OB sudah cek fisik dan data masuk ke stok)
     */
    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua')
                ->badge(static::getResource()::getEloquentQuery()->count()),

            'baru_dilaporkan' => Tab::make('Baru Dilaporkan')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'baru_dilaporkan'))
                ->badge(static::getResource()::getEloquentQuery()->where('status', 'baru_dilaporkan')->count())
                ->badgeColor('primary'),

            'menunggu_pengecekan' => Tab::make('Menunggu Pengecekan')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'menunggu_pengecekan'))
                ->badge(static::getResource()::getEloquentQuery()->where('status', 'menunggu_pengecekan')->count())
                ->badgeColor('warning'),

            'selesai' => Tab::make('Selesai')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('status', ['baru_dilaporkan', 'menunggu_pengecekan']))
                ->badge(static::getResource()::getEloquentQuery()->whereNotIn('status', ['baru_dilaporkan', 'menunggu_pengecekan'])->count())
                ->badgeColor('success'),
        ];
    }
}
