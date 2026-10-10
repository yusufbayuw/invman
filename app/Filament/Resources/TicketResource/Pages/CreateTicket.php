<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use App\Services\TicketService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $ticket = app(TicketService::class)->open(auth()->user(), $data);
        if (filled($data['files'] ?? [])) {
            app(TicketService::class)->attach($ticket, auth()->user(), array_values($data['files']));
        }
        return $ticket;
    }
}
