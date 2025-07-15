<?php

namespace App\Filament\Resources\G006M020VehicleReviewResource\Pages;

use App\Filament\Resources\G006M020VehicleReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG006M020VehicleReview extends EditRecord
{
    protected static string $resource = G006M020VehicleReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
