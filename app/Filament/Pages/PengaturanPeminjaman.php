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

    protected static ?int $navigationSort = 9;

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
                        ->native(false)
                        ->helperText('Mode A langsung mencadangkan aset saat pengajuan. Mode B baru mencadangkan saat disetujui dan selalu memeriksa ketersediaan ulang.'),
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
                        ->required()
                        ->live(onBlur: true)
                        ->helperText('Lama menunggu keputusan, BUKAN lama meminjam aset. Batas maksimum 720 jam; tenggat dapat berakhir lebih awal saat jadwal peminjaman mulai.'),
                    Select::make('hold_clock')
                        ->label('Cara menghitung durasi')
                        ->options([
                            LoanSettings::CLOCK_ELAPSED => 'Jam kalender (termasuk malam dan akhir pekan)',
                            LoanSettings::CLOCK_WORKING => 'Jam kerja terpilih (di luar jam kerja berhenti)',
                        ])
                        ->required()
                        ->native(false)
                        ->live()
                        ->helperText('Jam kalender menghitung malam dan akhir pekan. Jam kerja hanya menghitung jam pada hari dan rentang waktu yang dipilih.'),
                    Select::make('work_days')
                        ->label('Hari kerja')
                        ->multiple()
                        ->options([
                            '1' => 'Senin', '2' => 'Selasa', '3' => 'Rabu',
                            '4' => 'Kamis', '5' => 'Jumat', '6' => 'Sabtu', '7' => 'Minggu',
                        ])
                        ->required()
                        ->live()
                        ->visible(fn (Get $get): bool => $get('hold_clock') === LoanSettings::CLOCK_WORKING)
                        ->helperText('Hanya hari terpilih yang dihitung. Sabtu/Minggu dapat dipilih jika operasional berjalan. Belum menghitung tanggal merah/libur nasional.'),
                    TextInput::make('work_start')
                        ->label('Mulai jam kerja (HH:MM)')
                        ->required()
                        ->live(onBlur: true)
                        ->helperText('Awal jam yang ikut dihitung. Contoh 07:00; waktu sebelum jam ini tidak mengurangi kuota hold.')
                        ->visible(fn (Get $get): bool => $get('hold_clock') === LoanSettings::CLOCK_WORKING),
                    TextInput::make('work_end')
                        ->label('Selesai jam kerja (HH:MM)')
                        ->required()
                        ->live(onBlur: true)
                        ->helperText('Akhir jam yang ikut dihitung. Contoh 15:00; waktu setelah jam ini dilanjutkan pada hari kerja berikutnya.')
                        ->visible(fn (Get $get): bool => $get('hold_clock') === LoanSettings::CLOCK_WORKING),
                    Placeholder::make('deadline_information')
                        ->label('Ketentuan penting')
                        ->content('Batas keputusan selalu dipotong pada waktu mulai peminjaman jika jatuh lebih awal. Penyimpanan pengaturan tidak mengubah tenggat pengajuan lama. Scheduler loans:expire-holds harus aktif.'),
                    Placeholder::make('hold_policy_impact')
                        ->label('Dampak konfigurasi saat ini')
                        ->content(function (Get $get): string {
                            $hours = (int) ($get('hold_hours') ?: 24);
                            $clock = $get('hold_clock');

                            if ($clock === LoanSettings::CLOCK_WORKING) {
                                $weekdays = [
                                    '1' => 'Senin', '2' => 'Selasa', '3' => 'Rabu',
                                    '4' => 'Kamis', '5' => 'Jumat', '6' => 'Sabtu', '7' => 'Minggu',
                                ];
                                $selectedDays = array_values(array_filter(array_map(
                                    fn ($day): ?string => $weekdays[(string) $day] ?? null,
                                    (array) $get('work_days'),
                                )));
                                $schedule = implode(', ', $selectedDays) ?: 'belum dipilih';

                                return "{$hours} jam KERJA: hanya {$schedule} pukul "
                                    .($get('work_start') ?: '--:--').'–'.($get('work_end') ?: '--:--')
                                    .' yang dihitung; malam/akhir pekan di luar pilihan tidak dihitung. '
                                    .'Contoh 24 jam dengan Senin–Jumat 07:00–15:00 berarti 3 hari kerja. '
                                    .'Jika peminjaman dimulai lebih awal, tenggat dipotong. Perubahan hanya untuk pengajuan baru.';
                            }

                            return "{$hours} jam KALENDER: waktu malam, Sabtu, Minggu, dan hari libur tetap dihitung. "
                                .'Contoh pengajuan Senin 14:00 dengan 24 jam kalender berakhir Selasa 14:00, '
                                .'kecuali jadwal peminjaman mulai lebih awal. Perubahan hanya untuk pengajuan baru.';
                        })
                        ->columnSpanFull(),
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
