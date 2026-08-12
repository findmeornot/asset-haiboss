<?php
namespace App\Filament\Resources\RoleResource\Pages;
use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            ...$data,
            ...RoleResource::permissionGroupDataFromRecord($this->record),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update(['name' => $data['name']]);
        $record->permissions()->sync(RoleResource::extractPermissionIds($data));

        return $record;
    }
}
