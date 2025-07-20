<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G009M024VehicleChecklistResource\Pages;
use App\Filament\Resources\G009M024VehicleChecklistResource\RelationManagers;
use App\Models\G009M024VehicleChecklist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G009M024VehicleChecklistResource extends Resource
{
    protected static ?string $model = G009M024VehicleChecklist::class;

    protected static ?string $navigationGroup = 'Monitoring';
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $slug = 'vehicle-checklist';
    protected static ?string $modelLabel = 'Checklist Kendaraan';
    protected static ?string $navigationLabel = 'Checklist Kendaraan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('g008_m017_vehicle_id')
                    ->numeric(),
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name'),
                Forms\Components\DatePicker::make('date'),
                Forms\Components\Toggle::make('is_ok'),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('checklist_date'),
                Forms\Components\TextInput::make('photo'),
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
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_ok')
                    ->boolean(),
                Tables\Columns\TextColumn::make('checklist_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('photo')
                    ->searchable(),
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
            'index' => Pages\ListG009M024VehicleChecklists::route('/'),
            'create' => Pages\CreateG009M024VehicleChecklist::route('/create'),
            'edit' => Pages\EditG009M024VehicleChecklist::route('/{record}/edit'),
        ];
    }
}
