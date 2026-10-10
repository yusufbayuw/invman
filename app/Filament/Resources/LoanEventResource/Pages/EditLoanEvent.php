<?php

namespace App\Filament\Resources\LoanEventResource\Pages;

use App\Filament\Resources\LoanEventResource;
use Filament\Resources\Pages\EditRecord;

class EditLoanEvent extends EditRecord
{
    protected static string $resource = LoanEventResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Never transfer an event to another unit or rewrite its creator.
        $data['g001_m001_unit_id'] = $this->record->g001_m001_unit_id;
        $data['created_by'] = $this->record->created_by;

        return $data;
    }
}
