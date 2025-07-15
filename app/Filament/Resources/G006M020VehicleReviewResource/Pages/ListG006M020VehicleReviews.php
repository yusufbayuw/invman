<?php

namespace App\Filament\Resources\G006M020VehicleReviewResource\Pages;

use App\Filament\Resources\G006M020VehicleReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG006M020VehicleReviews extends ListRecords
{
    protected static string $resource = G006M020VehicleReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
