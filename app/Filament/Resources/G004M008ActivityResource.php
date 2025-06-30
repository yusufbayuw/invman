<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G004M008ActivityResource\Pages;
use App\Filament\Resources\G004M008ActivityResource\RelationManagers;
use App\Filament\Resources\G004M008ActivityResource\RelationManagers\ItemReservationRelationManager;
use App\Filament\Resources\G004M008ActivityResource\RelationManagers\RoomReservationRelationManager;
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
                    ->default(auth()?->user()?->id ?? null)
                    ->searchable()
                    ->preload()
                    ->label('Diajukan Oleh')
                    ->required(),
                Forms\Components\Select::make('g001_m001_unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->default(auth()?->user()?->g001_m001_unit_id ?? null)
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('name'),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('start_time')
                    ->default(now())
                    ->label('Tanggal dan Waktu Mulai')
                    ->required()
                    ->seconds(false)
                    ->locale('id')
                    ->native()
                    ->reactive(false),
                Forms\Components\DateTimePicker::make('end_time')
                    ->minDate(fn (callable $get) => $get('start_time') ?? now())
                    ->label('Tanggal dan Waktu Selesai')
                    ->required()
                    ->seconds(false)
                    ->native(true)
                    ->after('start_time')
                    ->rule('after:start_time')
                    ->locale('id'),
                Forms\Components\TextInput::make('attachment'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Diajukan Oleh')
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Unit')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Kegiatan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Tanggal dan Waktu Mulai')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_time')
                    ->label('Tanggal dan Waktu Selesai')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\ImageColumn::make('attachment')
                    ->searchable()
                    ->simpleLightbox(),
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
            ItemReservationRelationManager::class,
            RoomReservationRelationManager::class,
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
