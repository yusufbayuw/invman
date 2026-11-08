<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\TicketResource;

class TicketCategoryTicketsRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'tickets';

    protected static string $relatedResource = TicketResource::class;

    protected static ?string $title = 'Laporan Kerusakan';
}
