<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\WorkOrderWorkflowResource;

class WorkOrderJobTitleAssignmentWorkflowsRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'assignmentWorkflows';

    protected static string $relatedResource = WorkOrderWorkflowResource::class;

    protected static ?string $title = 'Workflow Penugasan';

    protected static bool $allowMutations = false;
}
