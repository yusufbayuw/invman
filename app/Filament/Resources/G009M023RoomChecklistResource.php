<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G009M023RoomChecklistResource\Pages;
use App\Models\G009M023RoomChecklist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class G009M023RoomChecklistResource extends Resource
{
    protected static ?string $model = G009M023RoomChecklist::class;

    protected static ?string $navigationGroup = 'Monitoring';

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'room-checklist';

    protected static ?string $modelLabel = 'Checklist Ruangan';

    protected static ?string $pluralModelLabel = 'Checklist Ruangan';

    protected static ?string $navigationLabel = 'Checklist Ruangan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pemeriksaan')
                    ->description('Pilih ruangan dan petugas yang melakukan pemeriksaan.')
                    ->schema([
                        Forms\Components\Select::make('g003_m006_room_id')
                            ->label('Ruangan')
                            ->relationship('room', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('user_id')
                            ->label('Pemeriksa')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn (): ?int => auth()->id())
                            ->required(),
                        Forms\Components\DatePicker::make('date')
                            ->label('Tanggal Laporan')
                            ->native(false)
                            ->default(now())
                            ->required(),
                        Forms\Components\DateTimePicker::make('checklist_date')
                            ->label('Waktu Checklist')
                            ->native(false)
                            ->seconds(false)
                            ->default(now())
                            ->required(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Hasil Pemeriksaan')
                    ->description('Catat kondisi ruangan dan lampirkan foto bila diperlukan.')
                    ->schema([
                        Forms\Components\ToggleButtons::make('is_ok')
                            ->label('Kondisi')
                            ->options([
                                true => 'Baik',
                                false => 'Perlu Tindak Lanjut',
                            ])
                            ->colors([
                                true => 'success',
                                false => 'danger',
                            ])
                            ->icons([
                                true => 'heroicon-o-check-circle',
                                false => 'heroicon-o-exclamation-triangle',
                            ])
                            ->inline()
                            ->required(),
                        Forms\Components\FileUpload::make('photo')
                            ->label('Foto Kondisi')
                            ->image()
                            ->imageEditor()
                            ->directory('room-checklists'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan Pemeriksaan')
                            ->placeholder('Tuliskan temuan atau tindak lanjut yang diperlukan.')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('room.name')
                    ->label('Ruangan')
                    ->description(fn (G009M023RoomChecklist $record): ?string => collect([
                        $record->room?->floor?->building?->name,
                        $record->room?->floor?->name,
                    ])->filter()->implode(' · ') ?: null)
                    ->weight('medium')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pemeriksa')
                    ->placeholder('Belum diperiksa')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->label('Tanggal Laporan')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('is_ok')
                    ->label('Kondisi')
                    ->badge()
                    ->formatStateUsing(fn (?bool $state): string => $state ? 'Baik' : 'Perlu Tindak Lanjut')
                    ->color(fn (?bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (?bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle'),
                Tables\Columns\TextColumn::make('checklist_date')
                    ->label('Waktu Checklist')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Belum diperiksa')
                    ->sortable(),
                Tables\Columns\ImageColumn::make('photo')
                    ->label('Foto')
                    ->square()
                    ->simpleLightbox(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Catatan')
                    ->limit(40)
                    ->placeholder('Tidak ada catatan')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('g003_m006_room_id')
                    ->label('Ruangan')
                    ->relationship('room', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_ok')
                    ->label('Kondisi')
                    ->trueLabel('Baik')
                    ->falseLabel('Perlu Tindak Lanjut')
                    ->placeholder('Semua kondisi'),
            ])
            ->defaultSort('date', 'desc')
            ->striped()
            ->emptyStateIcon('heroicon-o-building-office')
            ->emptyStateHeading('Belum ada checklist ruangan')
            ->emptyStateDescription('Tambahkan pemeriksaan ruangan untuk mulai memantau kondisinya.')
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Periksa'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListG009M023RoomChecklists::route('/'),
            'create' => Pages\CreateG009M023RoomChecklist::route('/create'),
            'edit' => Pages\EditG009M023RoomChecklist::route('/{record}/edit'),
        ];
    }
}
