<?php

namespace App\Filament\Support;

use App\Models\G005M019VehicleReservation;
use App\Models\G008M018Driver;
use App\Models\VehicleAssistant;
use Filament\Forms;

class VehicleAssignmentForm
{
    /** @return array<int, Forms\Components\Component> */
    public static function fields(G005M019VehicleReservation $reservation, bool $changing = false): array
    {
        $requiresAssistant = (bool) $reservation->vehicle?->requires_assistant;

        $fields = [
            Forms\Components\Select::make('driver_id')
                ->label('Pengemudi yang ditugaskan')
                ->options(fn (): array => G008M018Driver::query()
                    ->whereHas('user')
                    ->with('user')
                    ->get()
                    ->mapWithKeys(fn (G008M018Driver $driver): array => [
                        $driver->id => $driver->user?->name ?? 'Pengemudi #'.$driver->id,
                    ])->all())
                ->default($reservation->g008_m018_driver_id ?: $reservation->vehicle?->default_driver_id)
                ->searchable()
                ->required()
                ->helperText('Pengemudi default disarankan otomatis, tetapi dapat diganti untuk perjalanan ini.'),
        ];

        if ($requiresAssistant) {
            $fields[] = Forms\Components\Select::make('assistant_id')
                ->label('Kenek bus (internal)')
                ->options(fn (): array => VehicleAssistant::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->default($reservation->vehicle_assistant_id)
                ->searchable()
                ->required();
        }

        if ($changing) {
            $fields[] = Forms\Components\Textarea::make('reason')
                ->label('Alasan pergantian personel')
                ->required()
                ->maxLength(2000);
        }

        return $fields;
    }
}
