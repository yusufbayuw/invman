<?php

namespace App\Filament\Support;

use Filament\Forms;

final class TicketWorkForms
{
    /** Reused by the technician workspace and the existing ticket detail action. */
    public static function finish(): array
    {
        return [
            Forms\Components\Textarea::make('diagnosis')
                ->label('Diagnosis / penyebab masalah')
                ->helperText('Apa penyebab kerusakan yang ditemukan?')
                ->required()->minLength(5)->maxLength(10000)->rows(3),
            Forms\Components\Textarea::make('actions')
                ->label('Pekerjaan yang dilakukan')
                ->helperText('Tuliskan perbaikan dan hasil pemeriksaan. Unggah foto melalui aksi Bukti Foto Kerja.')
                ->required()->minLength(5)->maxLength(10000)->rows(3),
            Forms\Components\Repeater::make('materials')
                ->label('Material / suku cadang (opsional)')
                ->schema([
                    Forms\Components\TextInput::make('name')->label('Nama material')
                        ->required()->maxLength(200),
                    Forms\Components\TextInput::make('quantity')->label('Jumlah')
                        ->numeric()->gt(0)->required(),
                    Forms\Components\TextInput::make('unit')->label('Satuan')
                        ->default('pcs')->required()->maxLength(25),
                ])->columns(3)->maxItems(30)->default([])->addActionLabel('Tambah Material'),
            Forms\Components\Select::make('outcome')
                ->label('Hasil pekerjaan')->required()->native(false)
                ->options([
                    'complete' => 'Selesai Sempurna',
                    'temporary' => 'Perbaikan Sementara',
                    'unrepairable' => 'Tidak Dapat Diperbaiki',
                ])->helperText('Hasil pekerjaan tetap memerlukan verifikasi pelapor dan pengesahan fasilitas.'),
        ];
    }
}
