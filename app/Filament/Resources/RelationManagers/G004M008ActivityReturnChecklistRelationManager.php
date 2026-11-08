<?php

namespace App\Filament\Resources\RelationManagers;

class G004M008ActivityReturnChecklistRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'return_checklist';

    protected static ?string $title = 'Checklist Pengajuan';
}
