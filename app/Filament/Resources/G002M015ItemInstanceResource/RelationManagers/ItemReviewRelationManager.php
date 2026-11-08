<?php

namespace App\Filament\Resources\G002M015ItemInstanceResource\RelationManagers;

use App\Filament\Resources\G006M011ItemReviewResource;
use App\Filament\Resources\RelationManagers\ResourceRelationManager;

class ItemReviewRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'item_review';
    protected static string $relatedResource = G006M011ItemReviewResource::class;
    protected static ?string $title = 'Ulasan Barang';
}
