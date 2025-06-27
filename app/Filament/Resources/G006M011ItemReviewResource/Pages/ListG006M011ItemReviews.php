<?php

namespace App\Filament\Resources\G006M011ItemReviewResource\Pages;

use App\Filament\Resources\G006M011ItemReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListG006M011ItemReviews extends ListRecords
{
    protected static string $resource = G006M011ItemReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
