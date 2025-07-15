<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G006M020VehicleReviewResource\Pages;
use App\Filament\Resources\G006M020VehicleReviewResource\RelationManagers;
use App\Models\G006M020VehicleReview;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G006M020VehicleReviewResource extends Resource
{
    protected static ?string $model = G006M020VehicleReview::class;

    protected static ?string $navigationGroup = 'Review';
    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static ?string $slug = 'vehicle-review';
    protected static ?string $modelLabel = 'Ulasan Kendaraan';
    protected static ?string $navigationLabel = 'Ulasan Kendaraan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('g008_m017_vehicle_id')
                    ->numeric(),
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name'),
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
                Tables\Columns\TextColumn::make('g008_m017_vehicle_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
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
            'index' => Pages\ListG006M020VehicleReviews::route('/'),
            'create' => Pages\CreateG006M020VehicleReview::route('/create'),
            'view' => Pages\ViewG006M020VehicleReview::route('/{record}'),
            'edit' => Pages\EditG006M020VehicleReview::route('/{record}/edit'),
        ];
    }
}
