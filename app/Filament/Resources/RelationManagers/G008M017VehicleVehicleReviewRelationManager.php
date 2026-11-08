<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G006M020VehicleReviewResource;

class G008M017VehicleVehicleReviewRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'vehicle_review';

    protected static string $relatedResource = G006M020VehicleReviewResource::class;

    protected static ?string $title = 'Ulasan Kendaraan';
}
