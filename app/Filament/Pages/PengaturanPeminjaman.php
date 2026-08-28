<?php

namespace App\Filament\Pages;

use App\Services\LoanSettings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class PengaturanPeminjaman extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Pengaturan Peminjaman';

    protected static ?string $title = 'Pengaturan Peminjaman';

    protected static string $view = 'filament.pages.pengaturan-peminjaman';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(['hold_hours' => app(LoanSettings::class)->holdHours()]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Masa hold persetujuan')
                ->description('Setelah durasi ini, kebutuhan yang masih menunggu keputusan akan kedaluwarsa dan aset dilepas kembali.')
                ->schema([
                    TextInput::make('hold_hours')
                        ->label('Durasi hold (jam)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(720)
                        ->required(),
                ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        app(LoanSettings::class)->setHoldHours((int) $data['hold_hours']);

        Notification::make()->title('Pengaturan peminjaman disimpan')->success()->send();
    }
}
