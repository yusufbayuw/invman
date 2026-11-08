<?php

namespace App\Filament\Resources\RelationManagers;

class ManagementUsersRelationManager extends ManagementMembershipRelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Anggota Pengelola';
}
