<?php

namespace App\Filament\Resources\G001M001UnitResource\RelationManagers;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\RelationManagers\ResourceRelationManager;

class UserRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'user';
    protected static string $relatedResource = UserResource::class;
    protected static ?string $title = 'Pengguna';
}
