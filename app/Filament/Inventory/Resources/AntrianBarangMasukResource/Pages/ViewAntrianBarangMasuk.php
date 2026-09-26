<?php

namespace App\Filament\Inventory\Resources\AntrianBarangMasukResource\Pages;

use App\Filament\Inventory\Resources\AntrianBarangMasukResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAntrianBarangMasuk extends ViewRecord
{
    protected static string $resource = AntrianBarangMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('index')),

            Actions\EditAction::make()
                ->label('Lengkapi Data')
                ->icon('heroicon-o-pencil-square'),
        ];
    }
}
