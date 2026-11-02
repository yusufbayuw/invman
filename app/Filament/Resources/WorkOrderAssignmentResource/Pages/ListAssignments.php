<?php

namespace App\Filament\Resources\WorkOrderAssignmentResource\Pages;

use App\Filament\Resources\WorkOrderAssignmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAssignments extends ListRecords
{
    protected static string $resource = WorkOrderAssignmentResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
