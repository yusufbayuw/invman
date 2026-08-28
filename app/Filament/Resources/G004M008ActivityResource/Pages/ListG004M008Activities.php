<?php

namespace App\Filament\Resources\G004M008ActivityResource\Pages;

use App\Enums\ReservationStatus;
use App\Filament\Pages\AjukanPeminjaman;
use App\Filament\Resources\G004M008ActivityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListG004M008Activities extends ListRecords
{
    protected static string $resource = G004M008ActivityResource::class;

    public function getTabs(): array
    {
        $query = G004M008ActivityResource::getEloquentQuery();
        $counts = (clone $query)
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $tabs = [
            'all' => Tab::make('Semua')
                ->badge($counts->sum()),
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
        return [
            Actions\Action::make('ajukan_peminjaman')
                ->label('Ajukan Peminjaman')
                ->icon('heroicon-o-plus-circle')
                ->url(AjukanPeminjaman::getUrl())
                ->visible(fn () => AjukanPeminjaman::canAccess()),
        ];
    }
}
