<?php

namespace App\Filament\Resources\G004M008ActivityResource\Pages;

use App\Filament\Pages\AjukanPeminjaman;
use App\Filament\Resources\G004M008ActivityResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG004M008Activity extends ViewRecord
{
    protected static string $resource = G004M008ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->url(fn (): string => AjukanPeminjaman::getUrl(['record' => $this->record->id]))
                ->visible(fn () => auth()->user()?->can('update', $this->record)),
        ];
    }
}
