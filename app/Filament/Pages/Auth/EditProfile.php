<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;
use Illuminate\Support\Str;

class EditProfile extends BaseEditProfile
{
    public static function getLabel(): string
    {
        return 'Profil saya';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            FileUpload::make('avatar')
                ->label('Foto profil')
                ->avatar()
                ->image()
                ->imageEditor()
                ->disk(config('chatify.storage_disk_name'))
                ->directory(config('chatify.user_avatar.folder'))
                ->visibility('public')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(2048)
                ->helperText('Gunakan JPG, PNG, atau WebP. Ukuran maksimal 2 MB.'),
            $this->getNameFormComponent()
                ->label('Nama'),
            $this->getEmailFormComponent()
                ->label('Email'),
            $this->getPasswordFormComponent()
                ->label('Kata sandi baru'),
            $this->getPasswordConfirmationFormComponent()
                ->label('Konfirmasi kata sandi baru'),
        ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $avatar = $data['avatar'] ?? null;

        if (blank($avatar) || $avatar === config('chatify.user_avatar.default')) {
            $data['avatar'] = null;
        } elseif (! Str::startsWith($avatar, config('chatify.user_avatar.folder') . '/')) {
            $data['avatar'] = config('chatify.user_avatar.folder') . '/' . $avatar;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['avatar'] = filled($data['avatar'] ?? null)
            ? basename($data['avatar'])
            : config('chatify.user_avatar.default');

        return $data;
    }
}
