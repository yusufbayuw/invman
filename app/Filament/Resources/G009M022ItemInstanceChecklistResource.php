<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G009M022ItemInstanceChecklistResource\Pages;
use App\Filament\Resources\G009M022ItemInstanceChecklistResource\RelationManagers;
use App\Models\G009M022ItemInstanceChecklist;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class G009M022ItemInstanceChecklistResource extends Resource
{
    protected static ?string $model = G009M022ItemInstanceChecklist::class;

    protected static ?string $navigationGroup = 'Monitoring';
    protected static ?string $navigationIcon = 'heroicon-o-check-circle';
    protected static ?string $slug = 'item-instance-checklist';
    protected static ?string $modelLabel = 'Checklist Barang';
    protected static ?string $navigationLabel = 'Checklist Barang';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('g002_m015_item_instance_id')
                    ->relationship('item_instance', 'name')
                    ->disabledOn('edit')
                    ->label('Nama Barang'),
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->disabled(!auth()->user()->hasRole(['super_admin']))
                    ->hidden(fn($state) => $state ? False : True)
                    ->label('Diperiksa Oleh'),
                Forms\Components\MarkdownEditor::make('notes')
                    ->label('Catatan')
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('photo')
                    ->label('Foto Barang')
                    ->image(),
                Forms\Components\ToggleButtons::make('is_ok')
                    ->options([
                        True => 'Baik',
                        False => 'Trouble'
                    ])
                    ->colors([
                        True => 'success',
                        False => 'danger'
                    ])
                    ->icons([
                        True => 'heroicon-o-check',
                        False => 'heroicon-o-x-mark'
                    ])
                    ->inline()
                    ->label('Kondisi'),
                Forms\Components\TextInput::make('date')
                    ->label('Bulan Laporan')
                    ->formatStateUsing(fn($state) => $state ? \Carbon\Carbon::parse($state)->locale('id')->format('F Y') : null)
                    ->disabled(),
                Forms\Components\TextInput::make('checklist_date')
                    ->disabled()
                    ->formatStateUsing(fn($state) => $state ? \Carbon\Carbon::parse($state)->locale('id')->format('d F Y H:i:s') : null)
                    ->default(now()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('item_instance.name')
                    ->searchable()
                    ->sortable()
                    ->label('Barang'),
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable()
                    ->label('Diperiksa'),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->label('Tanggal Laporan')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('checklist_date')
                    ->dateTime()
                    ->sortable()
                    ->label('Tanggal Checklist')
                    ->toggleable(),
                Tables\Columns\ImageColumn::make('photo')
                    ->label('Foto Barang')
                    ->simpleLightbox(),
                Tables\Columns\IconColumn::make('is_ok')
                    ->boolean()
                    ->colors([
                        True => 'success',
                        False => 'danger'
                    ])
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->label('Kondisi')
                    ->action(function ($record, $column) {
                        $name = $column->getName();
                        $record->update([
                            $name => !$record->$name,
                        ]);
                    }),
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
                        return $stateText ? Indicator::make('Bulan Laporan: ' . $stateText)->removable(false) : null;
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
                        return $stateText ? Indicator::make('Unit: ' . \App\Models\G001M001Unit::find($stateText)->name )->removable(false) : null;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('photoUploadAction')
                    ->label(fn(G009M022ItemInstanceChecklist $record) => 'Foto: ' . $record->item_instance->name)
                    ->icon('heroicon-o-camera')
                    ->iconButton()
                    ->form([
                        Forms\Components\FileUpload::make('photoUpload')
                            ->label('Foto Barang')
                            ->default(fn($record) => $record->photo)
                            ->image(),
                    ])
                    ->action(function (array $data, G009M022ItemInstanceChecklist $record): void {
                        $record->photo = $data['photoUpload'];
                        $record->save();
                    }),
                Tables\Actions\Action::make('noteAction')
                    ->label(fn(G009M022ItemInstanceChecklist $record) => 'Catatan: ' . $record->item_instance->name)
                    ->icon('heroicon-o-document-text')
                    ->iconButton()
                    ->form([
                        Forms\Components\MarkdownEditor::make('noteUpload')
                            ->default(fn($record) => $record->notes)
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
