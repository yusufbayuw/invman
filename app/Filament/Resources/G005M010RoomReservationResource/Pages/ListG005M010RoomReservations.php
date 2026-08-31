<?php

namespace App\Filament\Resources\G005M010RoomReservationResource\Pages;

use App\Enums\ReservationStatus;
use App\Filament\Resources\G005M010RoomReservationResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListG005M010RoomReservations extends ListRecords
{
    protected static string $resource = G005M010RoomReservationResource::class;

    public function getTabs(): array
    {
        $counts = G005M010RoomReservationResource::getEloquentQuery()
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $tabs = [
            'all' => Tab::make('Semua')->badge($counts->sum()),
        ];

        foreach (ReservationStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->label())
                ->badge((int) $counts->get($status->value, 0))
                ->badgeColor($status->color())
                ->modifyQueryUsing(
                    fn (Builder $query): Builder => $query->where('status', $status->value),
                );
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
