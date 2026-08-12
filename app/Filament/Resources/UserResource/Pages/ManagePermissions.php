<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class ManagePermissions extends EditRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return 'Kelola Permission Langsung: '.$this->getRecord()->name;
    }

    public function getSubheading(): ?string
    {
        return 'Permission di sini ditambahkan langsung ke user, di luar permission bawaan dari role. Permission yang sudah aktif lewat role ditandai keterangannya.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(UserResource::permissionChecklistSchema($this->getRecord()))
            ->columns([
                'default' => 1,
                'md' => 2,
            ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_merge($data, UserResource::permissionGroupDataFromRecord($this->getRecord()));
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->permissions()->sync(UserResource::extractPermissionIds($data));

        return $record;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Permission User Berhasil Diperbarui';
    }

    protected function getRedirectUrl(): ?string
    {
        return static::getResource()::getUrl('index');
    }
}
