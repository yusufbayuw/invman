<?php

namespace App\Filament\Resources\G009M022ItemInstanceChecklistResource\Pages;

use App\Filament\Resources\G009M022ItemInstanceChecklistResource;
use App\Models\G002M015ItemInstance;
use App\Models\G009M022ItemInstanceChecklist;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListG009M022ItemInstanceChecklists extends ListRecords
{
    protected static string $resource = G009M022ItemInstanceChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate_bulan')
                ->label(fn () => 'Siapkan Checklist '.now()->locale('id')->translatedFormat('F Y'))
                ->icon('heroicon-o-calendar-days')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Siapkan checklist barang bulan ini?')
                ->modalDescription('Semua barang satuan akan ditambahkan ke daftar checklist bulan berjalan. Data yang sudah ada tidak akan diduplikasi.')
                ->action(function () {
                    $instanceAll = G002M015ItemInstance::all();
                    foreach ($instanceAll as $instance) {
                        G009M022ItemInstanceChecklist::firstOrCreate(
                            [
                                'g002_m015_item_instance_id' => $instance->id,
                                'date' => now()->startOfMonth(),
                            ],
                            [
                                // Tambahkan field lain yang ingin diisi/update di sinigs
                            ]
                        );
                    }

                    Notification::make()
                        ->title('Checklist bulan ini siap')
                        ->body($instanceAll->count().' barang tersedia untuk diperiksa.')
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make()
                ->hidden(),
        ];
    }
}
