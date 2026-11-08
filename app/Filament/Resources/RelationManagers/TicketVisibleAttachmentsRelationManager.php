<?php

namespace App\Filament\Resources\RelationManagers;

use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Table;

class TicketVisibleAttachmentsRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'visibleAttachments';

    protected static ?string $title = 'Lampiran';

    public function table(Table $table): Table
    {
        return parent::table($table)->actions([
            ViewAction::make()->label('Rincian'),
            Action::make('download')->label('Unduh Berkas')->icon('heroicon-o-arrow-down-tray')
                ->url(fn ($record) => route('tickets.attachment', ['attachment' => $record->id, 'download' => 1])),
        ]);
    }
}
