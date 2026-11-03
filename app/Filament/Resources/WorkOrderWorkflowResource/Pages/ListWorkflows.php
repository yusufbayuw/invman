<?php

namespace App\Filament\Resources\WorkOrderWorkflowResource\Pages;

use App\Filament\Resources\WorkOrderWorkflowResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWorkflows extends ListRecords
{
    protected static string $resource = WorkOrderWorkflowResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make('Buat Draft Baru')]; }
}
