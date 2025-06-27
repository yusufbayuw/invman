<?php

namespace App\Filament\Resources\G005M016ItemReservationDetailResource\Pages;

use App\Filament\Resources\G005M016ItemReservationDetailResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG005M016ItemReservationDetail extends EditRecord
{
    protected static string $resource = G005M016ItemReservationDetailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
