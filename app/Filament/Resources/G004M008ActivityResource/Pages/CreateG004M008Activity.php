<?php

namespace App\Filament\Resources\G004M008ActivityResource\Pages;

use App\Enums\ReservationStatus;
use App\Filament\Resources\G004M008ActivityResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateG004M008Activity extends CreateRecord
{
    protected static string $resource = G004M008ActivityResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (auth()->user()?->isSarpras()) {
            $data['user_id'] = auth()->id();
            $data['g001_m001_unit_id'] = auth()->user()->g001_m001_unit_id;
        }

        $data['status'] = ReservationStatus::Submitted->value;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
