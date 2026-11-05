<?php

namespace App\Filament\Pages;

use App\Services\LoanSettings;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
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
        abort_unless(static::canAccess(), 403);
        $this->form->fill(app(LoanSettings::class)->configuration());
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Kapan aset dicadangkan?')
                ->description('Berlaku untuk barang, ruangan, dan kendaraan pada pengajuan baru. Pengajuan yang sudah dikirim tidak berubah.')
                ->schema([
                    Select::make('hold_strategy')
                        ->label('Strategi pencadangan')
                        ->options([
                            LoanSettings::HOLD_ON_SUBMISSION => 'A - Sejak pengajuan dikirim',
                            LoanSettings::HOLD_ON_APPROVAL => 'B - Setelah kebutuhan disetujui',
                        ])
                        ->required()
                        ->live()
                        ->native(false),
                    Placeholder::make('strategy_consequences')
                        ->label('Konsekuensi pilihan')
                        ->content(fn (Get $get): string => $get('hold_strategy') === LoanSettings::HOLD_ON_APPROVAL
                            ? 'Pengajuan yang menunggu tidak mengurangi stok/jadwal. Beberapa pengajuan boleh masuk untuk aset yang sama; ketika disetujui sistem mengecek ketersediaan lagi secara terkunci. Pengajuan yang gagal pengecekan tidak disetujui.'
                            : 'Pengajuan langsung mengurangi stok/jadwal sampai disetujui, ditolak, dibatalkan, atau batas persetujuan habis. Pengajuan lain tidak dapat memakai alokasi sementara itu.'),
                ]),
            Section::make('Batas waktu keputusan')
                ->description('Setelah tenggat, kebutuhan yang belum diputuskan otomatis kedaluwarsa. Kebutuhan yang sudah disetujui tetap dicadangkan.')
                ->schema([
                    TextInput::make('hold_hours')
                        ->label('Durasi menunggu persetujuan (jam)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(720)
                        ->required(),
                    Select::make('hold_clock')
                        ->label('Cara menghitung durasi')
                        ->options([
                            LoanSettings::CLOCK_ELAPSED => 'Jam kalender (termasuk malam dan akhir pekan)',
                            LoanSettings::CLOCK_WORKING => 'Jam kerja terpilih (di luar jam kerja berhenti)',
                        ])
                        ->required()
                        ->native(false)
                        ->live(),
                    Select::make('work_days')
                        ->label('Hari kerja')
                        ->multiple()
                        ->options([
                            '1' => 'Senin', '2' => 'Selasa', '3' => 'Rabu',
                            '4' => 'Kamis', '5' => 'Jumat', '6' => 'Sabtu', '7' => 'Minggu',
                        ])
                        ->required()
                        ->visible(fn (Get $get): bool => $get('hold_clock') === LoanSettings::CLOCK_WORKING)
                        ->helperText('Belum memperhitungkan kalender libur nasional atau tanggal merah.'),
                    TextInput::make('work_start')
                        ->label('Mulai jam kerja (HH:MM)')
                        ->required()
                        ->visible(fn (Get $get): bool => $get('hold_clock') === LoanSettings::CLOCK_WORKING),
                    TextInput::make('work_end')
                        ->label('Selesai jam kerja (HH:MM)')
                        ->required()
                        ->visible(fn (Get $get): bool => $get('hold_clock') === LoanSettings::CLOCK_WORKING),
                    Placeholder::make('deadline_information')
                        ->label('Ketentuan penting')
                        ->content('Batas keputusan selalu dipotong pada waktu mulai peminjaman jika jatuh lebih awal. Penyimpanan pengaturan tidak mengubah tenggat pengajuan lama. Scheduler loans:expire-holds harus aktif.'),
                ])->columns(2),
        ])->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        app(LoanSettings::class)->saveConfiguration($this->form->getState());

        Notification::make()->title('Pengaturan peminjaman disimpan untuk pengajuan baru')->success()->send();
    }
}
