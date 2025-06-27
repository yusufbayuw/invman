<?php

namespace App\Filament\Resources\G006M012RoomReviewResource\Pages;

use App\Filament\Resources\G006M012RoomReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG006M012RoomReview extends ViewRecord
{
    protected static string $resource = G006M012RoomReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
