<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G006M020VehicleReviewResource;

class G005M019VehicleReservationVehicleReviewsRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'vehicle_reviews';

    protected static string $relatedResource = G006M020VehicleReviewResource::class;

    protected static ?string $title = 'Ulasan Kendaraan';

    protected static bool $allowMutations = false;
}
