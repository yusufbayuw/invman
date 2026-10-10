<?php

namespace App\Filament\Resources\LoanEventResource\Pages;

use App\Filament\Resources\LoanEventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLoanEvent extends CreateRecord
{
    protected static string $resource = LoanEventResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['g001_m001_unit_id'] = auth()->user()->g001_m001_unit_id;

        return $data;
    }
}
