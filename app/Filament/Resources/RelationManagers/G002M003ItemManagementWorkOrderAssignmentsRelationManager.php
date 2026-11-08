<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\WorkOrderAssignmentResource;

class G002M003ItemManagementWorkOrderAssignmentsRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'workOrderAssignments';

    protected static string $relatedResource = WorkOrderAssignmentResource::class;

    protected static ?string $title = 'Penugasan Jabatan';
}
