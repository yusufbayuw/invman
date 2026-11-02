<?php

namespace App\Filament\Resources;

use App\Models\WorkOrderJobTitle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WorkOrderJobTitleResource extends Resource
{
    protected static ?string $model = WorkOrderJobTitle::class;
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?string $navigationLabel = 'Jabatan Work Order';
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $slug = 'work-order-job-titles';

    public static function canViewAny(): bool { return auth()->user()?->isAdmin() ?? false; }
    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return static::canViewAny(); }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nama jabatan')->required()->maxLength(100)->unique(ignoreRecord: true),
            Forms\Components\Textarea::make('description')->label('Keterangan')->maxLength(255),
            Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Jabatan')->searchable()->sortable(),
            Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => WorkOrderJobTitleResource\Pages\ListJobTitles::route('/'),
            'create' => WorkOrderJobTitleResource\Pages\CreateJobTitle::route('/create'),
            'edit' => WorkOrderJobTitleResource\Pages\EditJobTitle::route('/{record}/edit'),
        ];
    }
}
