<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\TicketTeamMemberResource;

class TicketTeamMemberSubordinatesRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'subordinates';

    protected static string $relatedResource = TicketTeamMemberResource::class;

    protected static ?string $title = 'Bawahan Langsung';
}
