<?php

namespace App\Filament\Resources\TicketTeamMemberResource\Pages;

use App\Filament\Resources\TicketTeamMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTicketTeamMembers extends ListRecords
{
    protected static string $resource = TicketTeamMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Tambah Anggota Tim')];
    }
}
