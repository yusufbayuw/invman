<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G009M022ItemInstanceChecklistResource\Pages;
use App\Filament\Resources\G009M022ItemInstanceChecklistResource\RelationManagers;
use App\Models\G009M022ItemInstanceChecklist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G009M022ItemInstanceChecklistResource extends Resource
{
    protected static ?string $model = G009M022ItemInstanceChecklist::class;

    protected static ?string $navigationGroup = 'Monitoring';
    protected static ?string $navigationIcon = 'heroicon-o-check-circle';
    protected static ?string $slug = 'item-instance-checklist';
    protected static ?string $modelLabel = 'Daftar Periksa Instans Item';
    protected static ?string $navigationLabel = 'Daftar Periksa Instans Item';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('g002_m015_item_instance_id')
                    ->relationship('item_instance', 'name'),
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name'),
                Forms\Components\DatePicker::make('date'),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('photo'),
                Forms\Components\DateTimePicker::make('checklist_date'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('item_instance.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('photo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('checklist_date')
                    ->dateTime()
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
            'index' => Pages\ListG009M022ItemInstanceChecklists::route('/'),
            'create' => Pages\CreateG009M022ItemInstanceChecklist::route('/create'),
            'view' => Pages\ViewG009M022ItemInstanceChecklist::route('/{record}'),
            'edit' => Pages\EditG009M022ItemInstanceChecklist::route('/{record}/edit'),
        ];
    }
}
