<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G006M012RoomReviewResource;

class G004M008ActivityRoomReviewsRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'room_reviews';

    protected static string $relatedResource = G006M012RoomReviewResource::class;

    protected static ?string $title = 'Ulasan Ruangan';

    protected static bool $allowMutations = false;
}
