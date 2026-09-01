<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G006M020VehicleReviewResource\Pages;
use App\Models\G006M020VehicleReview;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class G006M020VehicleReviewResource extends Resource
{
    protected static ?string $model = G006M020VehicleReview::class;

    protected static ?string $navigationGroup = 'Ulasan';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $slug = 'vehicle-review';

    protected static ?string $modelLabel = 'Ulasan Kendaraan';

    protected static ?string $navigationLabel = 'Ulasan Kendaraan';

    protected static ?int $navigationSort = 30;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Detail Kendaraan')
                ->icon('heroicon-o-truck')
                ->schema([
                    Infolists\Components\TextEntry::make('vehicle.name')
                        ->label('Kendaraan')
                        ->weight('bold')
                        ->placeholder('-'),
                    Infolists\Components\TextEntry::make('vehicle.license_plate')
                        ->label('Nomor Polisi')
                        ->badge()
                        ->placeholder('-'),
                    Infolists\Components\TextEntry::make('user.name')
                        ->label('Pemberi Ulasan')
                        ->placeholder('-'),
                    Infolists\Components\TextEntry::make('vehicle_reservation.activity.name')
                        ->label('Kegiatan / Peminjaman')
                        ->placeholder('-'),
                ])
                ->columns(2),
            Infolists\Components\Section::make('Isi Ulasan')
                ->icon('heroicon-o-star')
                ->schema([
                    Infolists\Components\TextEntry::make('rating')
                        ->label('Penilaian')
                        ->formatStateUsing(fn ($state): string => filled($state) ? "{$state} / 5" : '-')
                        ->icon('heroicon-s-star')
                        ->iconColor('warning'),
                    Infolists\Components\TextEntry::make('created_at')
                        ->label('Diberikan pada')
                        ->dateTime('d M Y, H:i'),
                    Infolists\Components\TextEntry::make('review')
                        ->label('Ulasan')
                        ->placeholder('Tidak ada ulasan tertulis')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('g008_m017_vehicle_id')
                    ->label('Kendaraan')
                    ->relationship('vehicle', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->name} · {$record->license_plate}")
                    ->searchable(['name', 'license_plate'])
                    ->preload()
                    ->disabled(fn ($record): bool => filled($record?->g004_m008_activity_id))
                    ->dehydrated()
                    ->required(),
                Forms\Components\Select::make('user_id')
                    ->label('Pemberi Ulasan')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('g005_m019_vehicle_reservation_id')
                    ->label('Reservasi Kendaraan')
                    ->relationship('vehicle_reservation', 'id')
                    ->searchable()
                    ->preload()
                    ->disabled(fn ($record): bool => filled($record?->g004_m008_activity_id))
                    ->dehydrated(),
                Forms\Components\Placeholder::make('integrated_activity')
                    ->label('Kegiatan terintegrasi')
                    ->content(fn ($record): string => $record?->activity?->name ?? '-')
                    ->visible(fn ($record): bool => filled($record?->g004_m008_activity_id)),
                Forms\Components\Select::make('rating')
                    ->label('Penilaian')
                    ->options([
                        1 => '1 · Sangat Buruk',
                        2 => '2 · Buruk',
                        3 => '3 · Cukup',
                        4 => '4 · Baik',
                        5 => '5 · Sangat Baik',
                    ])
                    ->native(false)
                    ->required(),
                Forms\Components\Textarea::make('review')
                    ->label('Ulasan')
                    ->rows(4)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vehicle.name')
                    ->label('Kendaraan')
                    ->description(fn ($record): ?string => $record->vehicle?->license_plate)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pemberi Ulasan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('activity.name')
                    ->label('Kegiatan')
                    ->placeholder('Standalone')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('rating')
                    ->label('Penilaian')
                    ->formatStateUsing(fn ($state): string => "{$state} / 5")
                    ->icon('heroicon-s-star')
                    ->color('warning')
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('review')
                    ->label('Ulasan')
                    ->limit(60)
                    ->wrap()
                    ->placeholder('Tidak ada ulasan'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Diberikan pada')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui pada')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('g008_m017_vehicle_id')
                    ->label('Kendaraan')
                    ->relationship('vehicle', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('rating')
                    ->label('Penilaian')
                    ->options([1 => '1 Bintang', 2 => '2 Bintang', 3 => '3 Bintang', 4 => '4 Bintang', 5 => '5 Bintang']),
            ])
            ->defaultSort('created_at', 'desc')
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
