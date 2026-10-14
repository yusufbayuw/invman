<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use App\Models\TicketTeamMember;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Buat Laporan Kerusakan')
                ->visible(fn (): bool => TicketResource::canCreate()),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('Semua Tiket'),
            'mine' => Tab::make('Tugas Saya')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('assigned_to', auth()->id())),
            'progress' => Tab::make('Dalam Penanganan')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereIn('status', ['triaged', 'in_progress', 'waiting_parts', 'waiting_requester'])),
            'verification' => Tab::make('Menunggu Verifikasi')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'resolved')),
        ];

        $user = auth()->user();
        $subordinates = $user ? TicketTeamMember::descendantsOf($user) : [];
        if ($subordinates !== []) {
            $tabs['team'] = Tab::make('Tugas Tim Saya')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('assigned_to', $subordinates));
        }
        if ($user?->isFacility()) {
            $tabs['approval'] = Tab::make('Perlu Persetujuan')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'open'));
        }

        return $tabs;
    }
}
