<?php

namespace App\Filament\Resources\RelationManagers;

class WorkOrderWorkflowApprovalsRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'approvals';

    protected static ?string $title = 'Riwayat Persetujuan';
}
