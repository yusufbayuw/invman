<?php

namespace App\Filament\Resources\G006M011ItemReviewResource\Pages;

use App\Filament\Resources\G006M011ItemReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditG006M011ItemReview extends EditRecord
{
    protected static string $resource = G006M011ItemReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
