<?php

namespace App\Filament\Inventory\Resources\ReportedAssetResource\Pages;

use App\Filament\Inventory\Resources\AssetResource;
use App\Filament\Inventory\Resources\ReportedAssetResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewReportedAsset extends ViewRecord
{
    protected static string $resource = ReportedAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('complete')
                ->label('Lengkapi Data Barang')
                ->icon('heroicon-o-pencil-square')
                ->url(fn () => AssetResource::getUrl('edit', ['record' => $this->record]))
                ->visible(fn () => Auth::user()->can('update', $this->record)),
        ];
    }
}
