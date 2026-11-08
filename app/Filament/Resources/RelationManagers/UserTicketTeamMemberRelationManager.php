<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\TicketTeamMemberResource;

class UserTicketTeamMemberRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'ticketTeamMember';

    protected static string $relatedResource = TicketTeamMemberResource::class;

    protected static ?string $title = 'Keanggotaan Tim';
}
