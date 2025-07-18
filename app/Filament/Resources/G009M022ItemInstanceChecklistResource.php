<?php

namespace App\Filament\Resources;

use App\Filament\Resources\G009M022ItemInstanceChecklistResource\Pages;
use App\Filament\Resources\G009M022ItemInstanceChecklistResource\RelationManagers;
use App\Models\G009M022ItemInstanceChecklist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
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
    protected static ?string $modelLabel = 'Checklist Rutin Barang';
    protected static ?string $navigationLabel = 'Checklist Rutin Barang';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('g002_m015_item_instance_id')
                    ->relationship('item_instance', 'name')
                    ->label('Nama Barang'),
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->disabled(!auth()->user()->hasRole(['super_admin']))
                    ->hidden(fn($state) => $state ? False : True)
                    ->label('Diperiksa Oleh'),
                Forms\Components\Textarea::make('notes')
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
                        False => 'warning'
                    ])
                    ->icons([
                        True => 'heroicon-o-check',
                        False => 'heroicon-o-x-mark'
                    ])
                    ->inline()
                    ->label('Kondisi'),
                Forms\Components\TextInput::make('date')
                    ->label('Bulan Laporan')
                    ->formatStateUsing(fn($state) => $state ? \Carbon\Carbon::parse($state)->format('M Y') : null)
                    ->disabled(),
                Forms\Components\TextInput::make('checklist_date')
                    ->disabled()
                    ->formatStateUsing(fn($state) => $state ? \Carbon\Carbon::parse($state)->format('d M Y H:i:s') : null)
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
                    ->action(function ($record) {
                        return \Filament\Forms\Components\FileUpload::make('photo')
                            ->label('Ubah Foto Barang')
                            ->image()
                            ->saveUploadedFileUsing(function ($file, $record) {
                                $record->update(['photo' => $file->store('photos', 'public')]);
                            });
                    }),
                Tables\Columns\IconColumn::make('is_ok')
                    ->boolean()
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
                    ->attribute('date'),


                // Filter berdasarkan unit barang (relasi item_instance->item->unit->name)
                Tables\Filters\SelectFilter::make('unit')
                    ->label('Unit Barang')
                    ->searchable()
                    ->options(
                        \App\Models\G001M001Unit::all()
                            ->pluck('name', 'id')
                            ->toArray()
                    ),
            ])
            ->actions([
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
