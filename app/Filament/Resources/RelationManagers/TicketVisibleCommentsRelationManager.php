<?php

namespace App\Filament\Resources\RelationManagers;

use App\Services\TicketService;
use App\Services\TicketVisibility;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;

class TicketVisibleCommentsRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'visibleComments';

    protected static ?string $title = 'Komentar';

    public function table(Table $table): Table
    {
        return parent::table($table)->headerActions([
            Action::make('comment')->label('Tambah Komentar')
                ->authorize(fn () => app(TicketVisibility::class)->canView(auth()->user(), $this->getOwnerRecord()))
                ->form([
                    Textarea::make('body')->label('Komentar')->required()->minLength(2)->maxLength(10000),
                    Toggle::make('is_internal')->label('Catatan internal')->default(false)
                        ->visible(fn () => app(TicketVisibility::class)->canManage(auth()->user(), $this->getOwnerRecord())),
                ])->action(fn (array $data) => app(TicketService::class)->comment(
                    $this->getOwnerRecord(), auth()->user(), $data['body'], (bool) ($data['is_internal'] ?? false),
                )),
        ]);
    }
}
