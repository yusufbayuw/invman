<?php

namespace App\Filament\Resources\RelationManagers;

class TicketAssetsRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'assets';

    protected static ?string $title = 'Aset Terkait';
}
