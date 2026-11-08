<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\WorkOrderAssignmentResource;

class UserWorkOrderAssignmentsRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'workOrderAssignments';

    protected static string $relatedResource = WorkOrderAssignmentResource::class;

    protected static ?string $title = 'Penugasan Jabatan';
}
