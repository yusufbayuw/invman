<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\G004M008Activity;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\G004M008ActivityResource\Pages;
use App\Filament\Resources\G004M008ActivityResource\RelationManagers;
use App\Filament\Resources\G004M008ActivityResource\RelationManagers\ItemReservationRelationManager;
use App\Filament\Resources\G004M008ActivityResource\RelationManagers\RoomReservationRelationManager;
use App\Filament\Resources\G004M008ActivityResource\RelationManagers\VehicleReservationRelationManager;
use Coolsam\Flatpickr\Forms\Components\Flatpickr;
use Filament\Forms\Set;

class G004M008ActivityResource extends Resource
{
    protected static ?string $model = G004M008Activity::class;

    protected static ?string $navigationGroup = 'Kegiatan';
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $slug = 'activity';
    protected static ?string $modelLabel = 'Kegiatan';
    protected static ?string $navigationLabel = 'Kegiatan';

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Split::make([
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('name')
                            ->label('Nama Kegiatan')
                            ->weight('bold')
                            ->size('lg'),
                        \Filament\Infolists\Components\TextEntry::make('description')
                            ->label('Deskripsi')
                            ->size('md'),
                        \Filament\Infolists\Components\TextEntry::make('user.name')
                            ->label('Diajukan Oleh')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('unit.name')
                            ->label('Unit')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('start_time')
                            ->label('Tanggal dan Waktu Mulai')
                            ->dateTime()
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('end_time')
                            ->label('Tanggal dan Waktu Selesai')
                            ->dateTime()
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('attachment')
                            ->label('Lampiran')
                            ->inlineLabel()
                    ]),
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('created_at')
                            ->label('Dibuat pada')
                            ->dateTime(),
                        \Filament\Infolists\Components\TextEntry::make('updated_at')
                            ->label('Diperbarui pada')
                            ->dateTime(),
                    ]),
                ])->from('md')->columnSpanFull(),
            ]);
    }

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
                Flatpickr::make('start_time')
                    ->label('Tanggal dan Waktu Mulai')
                    ->time(true)
                    ->seconds(false)
                    ->live()
                    ->time24hr(true)
                    ->beforeOrEqual('end_time')
                    ->reactive()
                    ->afterStateUpdated(function ($state, Set $set) {
                        if ($state) {
                            $set('end_time', \Carbon\Carbon::parse($state)->addHour());
                        }
                    }),
                Flatpickr::make('end_time')
                    ->label('Tanggal dan Waktu Selesai')
                    ->time(true)
                    ->seconds(false)
                    ->reactive()
                    ->time24hr(true)
                    ->afterOrEqual('start_time')
                    ->minDate(fn (Get $get) => $get('start_time') ? \Carbon\Carbon::parse($get('start_time'))->addMinute() : now()),
                Forms\Components\TextInput::make('attachment'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Kegiatan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Mulai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_time')
                    ->label('Selesai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Unit')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Diajukan Oleh')
                    ->badge()
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
            VehicleReservationRelationManager::class,
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
