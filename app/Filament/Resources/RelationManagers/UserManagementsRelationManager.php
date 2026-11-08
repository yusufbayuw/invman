<?php

namespace App\Filament\Resources\RelationManagers;

class UserManagementsRelationManager extends ManagementMembershipRelationManager
{
    protected static string $relationship = 'itemManagements';

    protected static ?string $title = 'Kelompok Aset yang Dikelola';

    protected static bool $userOwner = true;
}
