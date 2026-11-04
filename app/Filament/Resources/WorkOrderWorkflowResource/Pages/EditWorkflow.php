<?php

namespace App\Filament\Resources\WorkOrderWorkflowResource\Pages;

use App\Filament\Resources\WorkOrderWorkflowResource;
use App\Services\WorkOrderWorkflowService;
use Filament\Resources\Pages\EditRecord;

class EditWorkflow extends EditRecord
{
    protected static string $resource = WorkOrderWorkflowResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['steps'] = app(WorkOrderWorkflowService::class)->validateSteps($data['steps'] ?? []);
        return $data;
    }
}
