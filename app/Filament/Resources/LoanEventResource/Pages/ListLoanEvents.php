<?php

namespace App\Filament\Resources\LoanEventResource\Pages;

use App\Filament\Resources\LoanEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLoanEvents extends ListRecords
{
    protected static string $resource = LoanEventResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
