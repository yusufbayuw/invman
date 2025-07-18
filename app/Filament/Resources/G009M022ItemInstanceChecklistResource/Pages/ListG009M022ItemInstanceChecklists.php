<?php

namespace App\Filament\Resources\G009M022ItemInstanceChecklistResource\Pages;

use Filament\Actions;
use App\Models\G002M015ItemInstance;
use Filament\Resources\Pages\ListRecords;
use App\Models\G009M022ItemInstanceChecklist;
use App\Filament\Resources\G009M022ItemInstanceChecklistResource;

class ListG009M022ItemInstanceChecklists extends ListRecords
{
    protected static string $resource = G009M022ItemInstanceChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate_bulan')
                ->label(fn () => 'Generate Bulan ' . (date('MM')))
                ->action(function () {
                    $instanceAll = G002M015ItemInstance::all();
                    foreach ($instanceAll as $instance) {
                        G009M022ItemInstanceChecklist::updateOrCreate(
                            [
                                'g002_m015_item_instance_id' => $instance->id,
                                'date' => date('d-m-Y'),
                            ],
                            [
                                // Tambahkan field lain yang ingin diisi/update di sinigs
                            ]
                        );
                    }
                    
                }),
            Actions\CreateAction::make(),
        ];
    }
}
