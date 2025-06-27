<?php

namespace App\Filament\Resources\G006M012RoomReviewResource\Pages;

use App\Filament\Resources\G006M012RoomReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG006M012RoomReviews extends ListRecords
{
    protected static string $resource = G006M012RoomReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
