<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VehicleAssistantResource\Pages;
use App\Models\VehicleAssistant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VehicleAssistantResource extends Resource
{
    protected static ?string $model = VehicleAssistant::class;
    protected static ?string $navigationGroup = 'Kendaraan';
    protected static ?string $navigationLabel = 'Kenek Bus';
    protected static ?string $modelLabel = 'Kenek Bus';
    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isFacility() ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isFacility() ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nama Kenek')->required()->maxLength(255),
            Forms\Components\TextInput::make('phone')->label('Kontak Internal')->maxLength(50),
            Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
            Forms\Components\Textarea::make('notes')->label('Catatan Internal')->maxLength(2000),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama Kenek')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->label('Kontak Internal'),
                Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVehicleAssistants::route('/'),
            'create' => Pages\CreateVehicleAssistant::route('/create'),
            'edit' => Pages\EditVehicleAssistant::route('/{record}/edit'),
        ];
    }
}
