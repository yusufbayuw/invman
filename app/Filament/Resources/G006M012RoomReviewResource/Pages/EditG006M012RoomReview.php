<?php

namespace App\Filament\Resources\G006M012RoomReviewResource\Pages;

use App\Filament\Resources\G006M012RoomReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG006M012RoomReview extends EditRecord
{
    protected static string $resource = G006M012RoomReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
