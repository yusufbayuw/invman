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
    protected static ?int $navigationSort = 4;
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
            Forms\Components\TextInput::make('name')->label('Nama jabatan')->required()->maxLength(100)->unique(ignoreRecord: true)
                ->helperText('Nama posisi dalam alur Work Order, misalnya Supervisor Fasilitas. Membuat jabatan tidak otomatis memberikan kewenangan kepada akun.'),
            Forms\Components\Textarea::make('description')->label('Keterangan')->maxLength(255)
                ->helperText('Penjelasan tugas atau batas tanggung jawab jabatan; tidak mengubah hak akses secara langsung.'),
            Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true)->live()
                ->helperText('Hanya jabatan aktif yang dapat dipilih untuk penugasan dan dipakai pada keputusan Work Order.'),
            Forms\Components\Placeholder::make('job_title_impact')
                ->label('Dampak status jabatan')
                ->content(fn (Forms\Get $get): string => $get('is_active')
                    ? 'Jabatan dapat ditugaskan kepada pegawai dan dipilih dalam workflow. Tetap harus ada penugasan aktif agar seseorang berwenang.'
                    : 'Jabatan tidak aktif. Penugasan yang mengandalkan jabatan ini dapat kehilangan kewenangan persetujuan. Periksa tiket yang sedang menunggu sebelum menonaktifkannya.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        $table->headerActions(\App\Filament\Actions\ResourceDataActions::make(static::class));

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
    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\RelationManagers\WorkOrderJobTitleDispatchWorkflowsRelationManager::class,
            \App\Filament\Resources\RelationManagers\WorkOrderJobTitleClosingWorkflowsRelationManager::class,
            \App\Filament\Resources\RelationManagers\WorkOrderJobTitleAssignmentWorkflowsRelationManager::class,\App\Filament\Resources\RelationManagers\WorkOrderJobTitleAssignmentsRelationManager::class];
    }
}
