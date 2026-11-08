<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\WorkOrderWorkflowResource;

class WorkOrderJobTitleDispatchWorkflowsRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'dispatchWorkflows';

    protected static string $relatedResource = WorkOrderWorkflowResource::class;

    protected static ?string $title = 'Workflow Lintas Unit';

    protected static bool $allowMutations = false;
}
