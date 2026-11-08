<?php

namespace App\Filament\Resources\RelationManagers;

class G004M008ActivityReviewRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'review';

    protected static ?string $title = 'Ulasan Pengajuan';
}
