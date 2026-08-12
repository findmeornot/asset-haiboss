<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Storage;

class EditProfile extends BaseEditProfile
{
    protected bool $hasTopbar = false;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('avatar_url')
                    ->hiddenLabel()
                    ->image()
                    ->imageEditor()
                    ->circleCropper()
                    ->avatar()
                    ->alignment(Alignment::Center)
                    ->disk('public')
                    ->directory('avatars')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg'])
                    ->maxSize(2048)
                    ->deletable()
                    ->deleteUploadedFileUsing(function ($file) {
                        Storage::disk('public')->delete($file);
                    }),
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->icon('heroicon-o-check');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->icon('heroicon-o-arrow-left');
    }
}
