<?php
namespace App\Filament\Resources\RoleResource\Pages;
use App\Filament\Resources\RoleResource;
use App\Models\Role;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $role = Role::create(['name' => $data['name']]);
        $role->permissions()->sync(RoleResource::extractPermissionIds($data));

        return $role;
    }

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}
