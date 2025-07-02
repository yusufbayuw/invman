<?php

namespace App\Filament\Resources\G004M008ActivityResource\RelationManagers;

use App\Models\G003M006Room;
use App\Models\G005M010RoomReservation;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Resources\RelationManagers\RelationManager;

class RoomReservationRelationManager extends RelationManager
{
    protected static string $relationship = 'room_reservation';
    protected static ?string $modelLabel = 'Reservasi Ruangan';
    protected static ?string $title = 'Reservasi Ruangan';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('g004_m008_activity_id')
                    ->default($this->ownerRecord->id ?? null),
                Forms\Components\Select::make('g003_m006_room_id')
                    ->relationship('room', 'name', function (Builder $query) {
                        $query->where('is_borrowable', true);
                    })
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        return "{$record->name} - {$record->floor->name} - {$record->floor->building->name}";
                    })
                    ->searchable()
                    ->reactive()
                    ->hint(function ($state , Get $get) {
                        $roomId = $state;
                        
                        static $itemOverlappingCache = [];

                        $startTime = $get('start_time');
                        $endTime = $get('end_time');

                        if (!$roomId) {
                            return '';
                        }

                        // Cache the item lookup to avoid multiple queries in a single request
                        if (!isset($itemOverlappingCache[$roomId])) {
                            
                            $itemOverlappingCache[$roomId] = G005M010RoomReservation::where('g003_m006_room_id', $roomId)
                                    ->where('status', '<>', 'dikembalikan')
                                    ->where(function ($query) use ($startTime, $endTime) {
                                        $query->where(function ($q) use ($startTime, $endTime) {
                                            $q->where('start_time', '<=', $endTime)
                                              ->where('end_time', '>=', $startTime);
                                        });
                                    })
                                    ->exists();
                        }

                        $notAvailable = $itemOverlappingCache[$roomId] ?? 0;
                        
                        if ($notAvailable) {
                            return "Ruangan ini tidak tersedia pada waktu yang dipilih.";
                        } else {
                            return 'Ruangan ini tersedia.';
                        }
                    })
                    ->rules([
                        function (Get $get) {
                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                if (!$value) {
                                    return;
                                }

                                $startTime = $get('start_time');
                                $endTime = $get('end_time');

                                if (!$startTime || !$endTime) {
                                    return;
                                }

                                $overlap = G005M010RoomReservation::where('g003_m006_room_id', $value)
                                    ->where('status', '<>', 'dikembalikan')
                                    ->where(function ($query) use ($startTime, $endTime) {
                                        $query->where(function ($q) use ($startTime, $endTime) {
                                            $q->where('start_time', '<=', $endTime)
                                              ->where('end_time', '>=', $startTime);
                                        });
                                    })
                                    ->exists();

                                if ($overlap) {
                                    $fail('Ruangan ini tidak tersedia pada waktu yang dipilih.');
                                }
                            };
                        }
                    ])
                    ->preload()
                    ->label('Ruangan')
                    ->required(),
                Forms\Components\DateTimePicker::make('start_time')
                    ->label('Waktu Mulai')
                    ->reactive()
                    ->native(false)
                    ->beforeOrEqual('end_time')
                    ->minDate($this->ownerRecord->start_time ?? now())
                    ->maxDate(fn (Get $get) => $get('end_time') ?? now())
                    ->default($this->ownerRecord->start_time ?? now()),
                Forms\Components\DateTimePicker::make('end_time')
                    ->label('Waktu Selesai')
                    ->native(false)
                    ->reactive()
                    ->afterOrEqual('start_time')
                    ->minDate(fn (Get $get) => $get('start_time') ?? now())
                    ->maxDate($this->ownerRecord->end_time ?? now()->addHour())
                    ->default($this->ownerRecord->end_time ?? now()->addHour()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('room.name')
                    ->searchable()
                    ->label('Ruangan')
                    ->sortable(),
                Tables\Columns\TextColumn::make('room.floor.name')
                    ->searchable()
                    ->label('Lantai')
                    ->sortable(),
                Tables\Columns\TextColumn::make('room.floor.building.name')
                    ->searchable()
                    ->label('Gedung')
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
