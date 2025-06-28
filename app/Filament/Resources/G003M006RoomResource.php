<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G003M006RoomResource\Pages;
use App\Filament\Resources\G003M006RoomResource\RelationManagers;
use App\Models\G003M006Room;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G003M006RoomResource extends Resource
{
    protected static ?string $model = G003M006Room::class;

    protected static ?string $navigationGroup = 'Ruangan';
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $slug = 'room';
    protected static ?string $modelLabel = 'Ruangan';
    protected static ?string $navigationLabel = 'Ruangan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('g003_m005_floor_id')
                    ->label('Gedung & Lantai')
                    ->searchable()
                    ->options(function () {
                        // Ambil data lantai beserta relasi gedung
                        return \App\Models\G003M005Floor::with('building')->get()->groupBy(function ($floor) {
                            // Group berdasarkan nama gedung
                            return $floor->building->name ?? 'Tanpa Gedung';
                        })->mapWithKeys(function ($floors, $buildingName) {
                            // Setiap group gedung, mapping lantai
                            return [
                                $buildingName => $floors->pluck('name', 'id')->toArray(),
                            ];
                        })->toArray();
                    }),
                Forms\Components\Select::make('g001_m001_unit_id')
                    ->relationship('unit', 'name'),
                Forms\Components\TextInput::make('name')
                    ->label('Nama Ruangan'),
                Forms\Components\Toggle::make('is_borrowable')
                    ->label('Dapat Dipinjam')
                    ->inlineLabel(),
                Forms\Components\TextInput::make('capacity')
                    ->label('Kapasitas')
                    ->numeric(),
                Forms\Components\Select::make('status')
                    ->label('Status Ruangan')
                    ->options([
                        'Tersedia' => 'Tersedia',
                        'Tidak Tersedia' => 'Tidak Tersedia',
                        'Dalam Perbaikan' => 'Dalam Perbaikan',
                    ])
                    ->default('Tersedia'),
                Forms\Components\FileUpload::make('photo')
                    ->label('Foto Ruangan')
                    ->image()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('qrcode'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('floor.building.name')
                    ->label('Gedung')
                    ->sortable(),
                Tables\Columns\TextColumn::make('floor.name')
                    ->label('Lantai')
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Unit')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->label('Nama Ruangan'),
                Tables\Columns\IconColumn::make('is_borrowable')
                    ->label('Dapat Dipinjam')
                    ->boolean(),
                Tables\Columns\TextColumn::make('capacity')
                    ->label('Kapasitas')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('photo')
                    ->label('Foto Ruangan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('qrcode')
                    ->label('QR Code')
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
            'index' => Pages\ListG003M006Rooms::route('/'),
            'create' => Pages\CreateG003M006Room::route('/create'),
            'view' => Pages\ViewG003M006Room::route('/{record}'),
            'edit' => Pages\EditG003M006Room::route('/{record}/edit'),
        ];
    }
}
