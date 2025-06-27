<?php

namespace App\Filament\Resources\G006M011ItemReviewResource\Pages;

use App\Filament\Resources\G006M011ItemReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG006M011ItemReview extends ViewRecord
{
    protected static string $resource = G006M011ItemReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
