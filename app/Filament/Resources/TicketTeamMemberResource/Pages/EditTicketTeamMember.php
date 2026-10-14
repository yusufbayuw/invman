<?php

namespace App\Filament\Resources\TicketTeamMemberResource\Pages;

use App\Filament\Resources\TicketTeamMemberResource;
use Filament\Resources\Pages\EditRecord;

class EditTicketTeamMember extends EditRecord
{
    protected static string $resource = TicketTeamMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
