<?php

namespace App\Filament\Resources\G004M008ActivityResource\Pages;

use App\Filament\Resources\G004M008ActivityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG004M008Activity extends EditRecord
{
    protected static string $resource = G004M008ActivityResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (auth()->user()?->isSarpras()) {
            $data['user_id'] = $this->record->user_id;
            $data['g001_m001_unit_id'] = $this->record->g001_m001_unit_id;
            $data['status'] = $this->record->status;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => auth()->user()?->isAdmin()),
        ];
    }
}
