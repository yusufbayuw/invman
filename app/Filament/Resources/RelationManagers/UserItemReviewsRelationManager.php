<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G006M011ItemReviewResource;

class UserItemReviewsRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'item_reviews';

    protected static string $relatedResource = G006M011ItemReviewResource::class;

    protected static ?string $title = 'Ulasan Barang';
}
