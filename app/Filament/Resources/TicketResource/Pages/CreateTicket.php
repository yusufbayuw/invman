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
        $actor = auth()->user();
        $reporterId = $data['on_behalf_of'] ?? null;
        $ticket = $actor->isFacility() && filled($reporterId)
            ? app(TicketService::class)->openOnBehalf(
                $actor, \App\Models\User::query()->findOrFail((int) $reporterId),
                $data, (string) ($data['override_reason'] ?? ''),
            )
            : app(TicketService::class)->open($actor, $data);
        if (filled($data['files'] ?? [])) {
            app(TicketService::class)->attach($ticket, auth()->user(), array_values($data['files']));
        }
        return $ticket;
    }
}
