<?php

namespace App\Filament\Resources\WorkOrderJobTitleResource\Pages;

use App\Filament\Resources\WorkOrderJobTitleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJobTitles extends ListRecords
{
    protected static string $resource = WorkOrderJobTitleResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
