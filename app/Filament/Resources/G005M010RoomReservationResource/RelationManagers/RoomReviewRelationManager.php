<?php

namespace App\Filament\Resources\G005M010RoomReservationResource\RelationManagers;

use App\Filament\Resources\G006M012RoomReviewResource;
use App\Filament\Resources\RelationManagers\ResourceRelationManager;

class RoomReviewRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'room_review';
    protected static string $relatedResource = G006M012RoomReviewResource::class;
    protected static ?string $title = 'Ulasan Ruangan';
    protected static bool $allowMutations = false;
}
