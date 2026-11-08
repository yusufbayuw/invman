<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\WorkOrderAssignmentResource;

class WorkOrderJobTitleAssignmentsRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'assignments';

    protected static string $relatedResource = WorkOrderAssignmentResource::class;

    protected static ?string $title = 'Penugasan Jabatan';
}
