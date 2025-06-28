<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G004M008ActivityResource\Pages;
use App\Filament\Resources\G004M008ActivityResource\RelationManagers;
use App\Models\G004M008Activity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G004M008ActivityResource extends Resource
{
    protected static ?string $model = G004M008Activity::class;

    protected static ?string $navigationGroup = 'Kegiatan';
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $slug = 'activity';
    protected static ?string $modelLabel = 'Kegiatan';
    protected static ?string $navigationLabel = 'Kegiatan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->label('Diajukan Oleh'),
                Forms\Components\Select::make('g001_m001_unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'name', function (Builder $query) {
                        $query->where('is_active', true)->where('quantity', '>', 0);
                    }),
                Forms\Components\TextInput::make('name'),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('start_time'),
                Forms\Components\DateTimePicker::make('end_time'),
                Forms\Components\TextInput::make('attachment'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('g001_m001_unit_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('attachment')
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
            'index' => Pages\ListG004M008Activities::route('/'),
            'create' => Pages\CreateG004M008Activity::route('/create'),
            'view' => Pages\ViewG004M008Activity::route('/{record}'),
            'edit' => Pages\EditG004M008Activity::route('/{record}/edit'),
        ];
    }
}
