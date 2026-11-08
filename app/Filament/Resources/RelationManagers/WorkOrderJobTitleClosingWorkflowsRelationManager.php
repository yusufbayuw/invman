<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\WorkOrderWorkflowResource;

class WorkOrderJobTitleClosingWorkflowsRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'closingWorkflows';

    protected static string $relatedResource = WorkOrderWorkflowResource::class;

    protected static ?string $title = 'Workflow Pengesahan';

    protected static bool $allowMutations = false;
}
