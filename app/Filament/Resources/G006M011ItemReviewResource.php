<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G006M011ItemReviewResource\Pages;
use App\Models\G006M011ItemReview;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class G006M011ItemReviewResource extends Resource
{
    protected static ?string $model = G006M011ItemReview::class;

    protected static ?string $navigationGroup = 'Ulasan';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $slug = 'item-review';

    protected static ?string $modelLabel = 'Ulasan Barang';

    protected static ?string $navigationLabel = 'Ulasan Barang';

    protected static ?int $navigationSort = 10;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Detail Barang')
                    ->icon('heroicon-o-cube')
                    ->schema([
                        Infolists\Components\TextEntry::make('item_instance.name')
                            ->label('Barang Satuan')
                            ->weight('bold')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('item_instance.code')
                            ->label('Kode Barang')
                            ->badge()
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Pemberi Ulasan')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('item_reservation.activity.name')
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
                Forms\Components\Select::make('g002_m015_item_instance_id')
                    ->label('Barang Satuan')
                    ->relationship('item_instance', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => trim("{$record->name} · {$record->code}", ' ·'))
                    ->searchable(['name', 'code'])
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
                Forms\Components\Select::make('g005_m009_item_reservation_id')
                    ->label('Reservasi Barang')
                    ->relationship('item_reservation', 'id')
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
                    ->options([1 => '1 · Sangat Buruk', 2 => '2 · Buruk', 3 => '3 · Cukup', 4 => '4 · Baik', 5 => '5 · Sangat Baik'])
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
                Tables\Columns\TextColumn::make('item_instance.name')
                    ->label('Barang Satuan')
                    ->description(fn ($record): ?string => $record->item_instance?->code)
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
            'index' => Pages\ListG006M011ItemReviews::route('/'),
            'create' => Pages\CreateG006M011ItemReview::route('/create'),
            'view' => Pages\ViewG006M011ItemReview::route('/{record}'),
            'edit' => Pages\EditG006M011ItemReview::route('/{record}/edit'),
        ];
    }
}
