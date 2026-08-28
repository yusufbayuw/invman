<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G009M024VehicleChecklistResource\Pages;
use App\Models\G009M024VehicleChecklist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class G009M024VehicleChecklistResource extends Resource
{
    protected static ?string $model = G009M024VehicleChecklist::class;

    protected static ?string $navigationGroup = 'Monitoring';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'vehicle-checklist';

    protected static ?string $modelLabel = 'Checklist Kendaraan';

    protected static ?string $pluralModelLabel = 'Checklist Kendaraan';

    protected static ?string $navigationLabel = 'Checklist Kendaraan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pemeriksaan')
                    ->description('Pilih kendaraan dan petugas yang melakukan pemeriksaan.')
                    ->schema([
                        Forms\Components\Select::make('g008_m017_vehicle_id')
                            ->label('Kendaraan')
                            ->relationship('vehicle', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->name} · {$record->license_plate}")
                            ->searchable(['name', 'license_plate'])
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
                    ->description('Catat kondisi kendaraan dan lampirkan foto bila diperlukan.')
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
                            ->directory('vehicle-checklists'),
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
                Tables\Columns\TextColumn::make('vehicle.name')
                    ->label('Kendaraan')
                    ->description(fn ($record): ?string => $record->vehicle?->license_plate)
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
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('g008_m017_vehicle_id')
                    ->label('Kendaraan')
                    ->relationship('vehicle', 'name')
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
            ->emptyStateIcon('heroicon-o-truck')
            ->emptyStateHeading('Belum ada checklist kendaraan')
            ->emptyStateDescription('Tambahkan pemeriksaan kendaraan untuk mulai memantau kondisinya.')
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListG009M024VehicleChecklists::route('/'),
            'create' => Pages\CreateG009M024VehicleChecklist::route('/create'),
            'edit' => Pages\EditG009M024VehicleChecklist::route('/{record}/edit'),
        ];
    }
}
