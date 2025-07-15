<?php

namespace App\Filament\Resources\G006M020VehicleReviewResource\Pages;

use App\Filament\Resources\G006M020VehicleReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG006M020VehicleReview extends ViewRecord
{
    protected static string $resource = G006M020VehicleReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
