<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G006M011ItemReviewResource;

class G005M009ItemReservationItemReviewsRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'item_reviews';

    protected static string $relatedResource = G006M011ItemReviewResource::class;

    protected static ?string $title = 'Ulasan Barang';

    protected static bool $allowMutations = false;
}
