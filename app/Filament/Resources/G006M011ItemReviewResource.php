<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G006M011ItemReviewResource\Pages;
use App\Filament\Resources\G006M011ItemReviewResource\RelationManagers;
use App\Models\G006M011ItemReview;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G006M011ItemReviewResource extends Resource
{
    protected static ?string $model = G006M011ItemReview::class;

    protected static ?string $navigationGroup = 'Review';
    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static ?string $slug = 'item-review';
    protected static ?string $modelLabel = 'Ulasan Barang';
    protected static ?string $navigationLabel = 'Ulasan Barang';

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Split::make([
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('g002_m015_item_instance_id')
                            ->label('ID Barang Satuan')
                            ->weight('bold')
                            ->size('md')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('user_id')
                            ->label('ID Pengguna')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('g005_m009_item_reservation_id')
                            ->label('ID Reservasi Barang'),
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
                Forms\Components\TextInput::make('g002_m015_item_instance_id')
                    ->numeric(),
                Forms\Components\TextInput::make('user_id')
                    ->numeric(),
                Forms\Components\TextInput::make('g005_m009_item_reservation_id'),
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
                Tables\Columns\TextColumn::make('g002_m015_item_instance_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('g005_m009_item_reservation_id')
                    ->searchable(),
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
            'index' => Pages\ListG006M011ItemReviews::route('/'),
            'create' => Pages\CreateG006M011ItemReview::route('/create'),
            'view' => Pages\ViewG006M011ItemReview::route('/{record}'),
            'edit' => Pages\EditG006M011ItemReview::route('/{record}/edit'),
        ];
    }
}
