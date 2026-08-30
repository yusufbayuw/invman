<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G009M022ItemInstanceChecklistResource\Pages;
use App\Models\G009M022ItemInstanceChecklist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class G009M022ItemInstanceChecklistResource extends Resource
{
    protected static ?string $model = G009M022ItemInstanceChecklist::class;

    protected static ?string $navigationGroup = 'Monitoring';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'item-instance-checklist';

    protected static ?string $modelLabel = 'Checklist Barang';

    protected static ?string $pluralModelLabel = 'Checklist Barang';

    protected static ?string $navigationLabel = 'Checklist Barang';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pemeriksaan')
                    ->description('Data barang dan periode checklist.')
                    ->schema([
                        Forms\Components\Select::make('g002_m015_item_instance_id')
                            ->label('Barang')
                            ->relationship('item_instance', 'name')
                            ->searchable()
                            ->preload()
                            ->disabledOn('edit')
                            ->required(),
                        Forms\Components\Select::make('user_id')
                            ->label('Pemeriksa')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn (): ?int => auth()->id())
                            ->disabled(fn (): bool => ! auth()->user()->isAdmin())
                            ->dehydrated(fn (): bool => auth()->user()->isAdmin()),
                        Forms\Components\TextInput::make('date')
                            ->label('Bulan Laporan')
                            ->formatStateUsing(fn ($state): ?string => $state ? \Carbon\Carbon::parse($state)->locale('id')->translatedFormat('F Y') : null)
                            ->disabled(),
                        Forms\Components\TextInput::make('checklist_date')
                            ->label('Waktu Checklist')
                            ->formatStateUsing(fn ($state): ?string => $state ? \Carbon\Carbon::parse($state)->locale('id')->translatedFormat('d F Y H:i') : null)
                            ->disabled(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Hasil Pemeriksaan')
                    ->description('Tentukan kondisi barang dan tambahkan bukti pemeriksaan.')
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
                            ->directory('item-checklists'),
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
                Tables\Columns\TextColumn::make('item_instance.name')
                    ->description(fn (G009M022ItemInstanceChecklist $record): ?string => $record->item_instance?->code)
                    ->weight('medium')
                    ->searchable()
                    ->sortable()
                    ->label('Barang'),
                Tables\Columns\TextColumn::make('user.name')
                    ->placeholder('Belum diperiksa')
                    ->sortable()
                    ->searchable()
                    ->label('Pemeriksa'),
                Tables\Columns\TextColumn::make('date')
                    ->date('M Y')
                    ->label('Bulan Laporan')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('checklist_date')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('Belum diperiksa')
                    ->label('Waktu Checklist')
                    ->toggleable(),
                Tables\Columns\ImageColumn::make('photo')
                    ->label('Foto')
                    ->square()
                    ->simpleLightbox(),
                Tables\Columns\TextColumn::make('is_ok')
                    ->label('Kondisi')
                    ->badge()
                    ->formatStateUsing(fn (?bool $state): string => match ($state) {
                        true => 'Baik',
                        false => 'Perlu Tindak Lanjut',
                        null => 'Belum Diperiksa',
                    })
                    ->color(fn (?bool $state): string => match ($state) {
                        true => 'success',
                        false => 'danger',
                        null => 'gray',
                    })
                    ->icon(fn (?bool $state): string => match ($state) {
                        true => 'heroicon-o-check-badge',
                        false => 'heroicon-o-exclamation-triangle',
                        null => 'heroicon-o-clock',
                    })
                    ->action(function ($record, $column) {
                        $name = $column->getName();
                        $record->update([
                            $name => ! $record->$name,
                        ]);
                    }),
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
                // Filter berdasarkan bulan dan tahun pada field 'date'
                Tables\Filters\SelectFilter::make('month_year')
                    ->label('Bulan Laporan')
                    ->options(
                        \App\Models\G009M022ItemInstanceChecklist::all()
                            ->unique('date')
                            ->pluck('date')
                            ->mapWithKeys(function ($date) {
                                $formatted = \Carbon\Carbon::parse($date)->format('M Y');

                                return [$date => $formatted];
                            })
                            ->toArray()
                    )
                    ->default(
                        \App\Models\G009M022ItemInstanceChecklist::orderByDesc('date')->value('date')
                    )
                    ->attribute('date')
                    ->indicateUsing(function ($state) {
                        if (is_array($state)) {
                            $stateText = implode(', ', $state);
                        } else {
                            $stateText = $state;
                        }

                        return $stateText ? Indicator::make('Bulan Laporan: '.\Carbon\Carbon::parse($stateText)->locale('id')->translatedFormat('F Y'))->removable(false) : null;
                    }),

                // Filter berdasarkan unit barang (relasi item_instance->item->unit->name)
                Tables\Filters\SelectFilter::make('unit_id')
                    ->label('Unit Barang')
                    ->searchable()
                    ->options(
                        \App\Models\G001M001Unit::all()
                            ->pluck('name', 'id')
                            ->toArray()
                    )
                    ->default(auth()->user()->g001_m001_unit_id)
                    ->query(function (Builder $query, $state) {
                        if ($state) {
                            $query->whereHas('item_instance.item.unit', function ($q) use ($state) {
                                $q->where('id', $state);
                            });
                        }
                    })
                    ->indicateUsing(function ($state) {
                        if (is_array($state)) {
                            $stateText = implode(', ', $state);
                        } else {
                            $stateText = $state;
                        }
                        $unitName = \App\Models\G001M001Unit::find($stateText)?->name;

                        return $unitName ? Indicator::make('Unit: '.$unitName)->removable(false) : null;
                    }),
            ])
            ->defaultSort('date', 'desc')
            ->striped()
            ->emptyStateIcon('heroicon-o-cube')
            ->emptyStateHeading('Belum ada checklist barang')
            ->emptyStateDescription('Buat checklist bulanan untuk mulai memantau kondisi barang.')
            ->actions([
                Tables\Actions\Action::make('photoUploadAction')
                    ->label(fn (G009M022ItemInstanceChecklist $record) => 'Foto: '.$record->item_instance->name)
                    ->icon('heroicon-o-camera')
                    ->iconButton()
                    ->form([
                        Forms\Components\FileUpload::make('photoUpload')
                            ->label('Foto Barang')
                            ->default(fn ($record) => $record->photo)
                            ->image(),
                    ])
                    ->action(function (array $data, G009M022ItemInstanceChecklist $record): void {
                        $record->photo = $data['photoUpload'];
                        $record->save();
                    }),
                Tables\Actions\Action::make('noteAction')
                    ->label(fn (G009M022ItemInstanceChecklist $record) => 'Catatan: '.$record->item_instance->name)
                    ->icon('heroicon-o-document-text')
                    ->iconButton()
                    ->form([
                        Forms\Components\MarkdownEditor::make('noteUpload')
                            ->default(fn ($record) => $record->notes)
                            ->label('Catatan'),
                    ])
                    ->action(function (array $data, G009M022ItemInstanceChecklist $record): void {
                        $record->notes = $data['noteUpload'];
                        $record->save();
                    }),
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
