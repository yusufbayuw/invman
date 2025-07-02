<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G006M012RoomReviewResource\Pages;
use App\Filament\Resources\G006M012RoomReviewResource\RelationManagers;
use App\Models\G006M012RoomReview;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G006M012RoomReviewResource extends Resource
{
    protected static ?string $model = G006M012RoomReview::class;

    protected static ?string $navigationGroup = 'Review';
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';
    protected static ?string $slug = 'room-review';
    protected static ?string $modelLabel = 'Ulasan Ruangan';
    protected static ?string $navigationLabel = 'Ulasan Ruangan';

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Split::make([
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('g005_m010_room_reservation_id')
                            ->label('ID Reservasi Ruangan')
                            ->weight('bold')
                            ->size('md'),
                        \Filament\Infolists\Components\TextEntry::make('g003_m006_room_id')
                            ->label('ID Ruangan')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('user_id')
                            ->label('ID Pengguna'),
                    ]),
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('rating')
                            ->label('Rating')
                            ->numeric(),
                        \Filament\Infolists\Components\TextEntry::make('created_at')
                            ->label('Dibuat pada')
                            ->dateTime(),
                        \Filament\Infolists\Components\TextEntry::make('updated_at')
                            ->label('Diperbarui pada')
                            ->dateTime(),
                    ])
                ])->from('md')->columnSpanFull(),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('g005_m010_room_reservation_id'),
                Forms\Components\TextInput::make('g003_m006_room_id')
                    ->numeric(),
                Forms\Components\TextInput::make('user_id')
                    ->numeric(),
                Forms\Components\TextInput::make('rating')
                    ->numeric(),
                Forms\Components\Textarea::make('review')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('g005_m010_room_reservation_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('g003_m006_room_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rating')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListG006M012RoomReviews::route('/'),
            'create' => Pages\CreateG006M012RoomReview::route('/create'),
            'view' => Pages\ViewG006M012RoomReview::route('/{record}'),
            'edit' => Pages\EditG006M012RoomReview::route('/{record}/edit'),
        ];
    }
}
