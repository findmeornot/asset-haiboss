<?php
namespace App\Filament\Inventory\Resources\AssetMovementResource\Pages;
use App\Filament\Inventory\Resources\AssetMovementResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
class CreateAssetMovement extends CreateRecord
{
    protected static string $resource = AssetMovementResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requested_by'] = Auth::id();
        $data['status'] = 'pending';
        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $asset = \App\Models\Asset::where('id', $data['asset_id'])->lockForUpdate()->first();
            
            $hasPending = \App\Models\AssetMovement::where('asset_id', $data['asset_id'])
                ->where('status', 'pending')
                ->exists();
                
            if ($hasPending) {
                \Filament\Notifications\Notification::make()
                    ->title('Gagal')
                    ->body('Aset ini masih memiliki pengajuan mutasi yang pending.')
                    ->danger()
                    ->send();
                throw new \Filament\Support\Exceptions\Halt();
            }
            
            return static::getModel()::create($data);
        });
    }
}
