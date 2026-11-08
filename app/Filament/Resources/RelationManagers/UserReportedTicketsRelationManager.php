<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\TicketResource;

class UserReportedTicketsRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'reportedTickets';

    protected static string $relatedResource = TicketResource::class;

    protected static ?string $title = 'Laporan Kerusakan';

    protected static bool $allowMutations = false;
}
