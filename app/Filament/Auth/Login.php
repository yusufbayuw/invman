<?php

namespace App\Filament\Auth;

use Filament\Forms\Form;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login as BaseAuth;
use Coderflex\FilamentTurnstile\Forms\Components\Turnstile;
use DominionSolutions\FilamentCaptcha\Forms\Components\Captcha;

class Login extends BaseAuth 
{
    public function form(Form $form): Form
    {
        return $form
        ->schema([
        $this->getEmailFormComponent(),
        //$this->getLoginFormComponent(),
        $this->getPasswordFormComponent(),
        config('base_urls.login_turnstile') ? 
        Turnstile::make('captcha')
            ->label('Kode Keamanan')
            ->theme('auto')
        : Captcha::make('captcha')
            ->label('Kode Keamanan')
            ->helperText('Masukkan 5 karakter pada gambar. Huruf besar/kecil tidak dibedakan.')
            ->rules(['captcha'])
            ->required()
            ->validationMessages([
                'captcha' => __('Captcha tidak sesuai'),
            ]),
        $this->getRememberFormComponent(),
        ])
        ->statePath('data');
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
        ->label('Email/Username')
        ->required()
        ->autocomplete()
        ->autofocus()
        ->extraInputAttributes(['tabindex' => 1]);;
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $login_type = filter_var($data['email'], FILTER_VALIDATE_EMAIL ) ? 'email' : 'username';
 
        return [
            $login_type => $data['email'],
            'password'  => $data['password'],
            'is_enabled' => true,
        ];
    }

    protected function throwFailureValidationException(): never
    {
        // Clear the rejected challenge so the next attempt uses a fresh code.
        $this->data['captcha'] = null;
        $this->dispatch('reset-captcha');
 
        parent::throwFailureValidationException();
    }
}