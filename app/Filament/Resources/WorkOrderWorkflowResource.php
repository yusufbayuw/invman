<?php

namespace App\Filament\Resources;

use App\Models\WorkOrderWorkflow;
use App\Services\WorkOrderWorkflowService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WorkOrderWorkflowResource extends Resource
{
    protected static ?string $model = WorkOrderWorkflow::class;
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?string $navigationLabel = 'Alur Persetujuan Work Order';
    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $slug = 'work-order-workflows';

    public static function canViewAny(): bool { return auth()->user()?->isAdmin() ?? false; }
    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit(Model $record): bool { return static::canViewAny() && $record->status === 'draft'; }
    public static function canDelete(Model $record): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('g001_m001_unit_id')
                ->label('Unit (kosong = template semua unit)')
                ->relationship('unit', 'name')->searchable()->preload()
                ->disabled(fn (?WorkOrderWorkflow $record): bool => $record?->exists ?? false),
            Forms\Components\Placeholder::make('version_info')
                ->label('Versi')
                ->content(fn (?WorkOrderWorkflow $record): string => $record ? 'Versi '.$record->version.' · '.$record->status : 'Draft versi baru'),
            Forms\Components\Select::make('assign_job_title_id')->label('Jabatan untuk persetujuan & penugasan teknisi')
                ->relationship('assignTitle', 'name', fn ($query) => $query->where('is_active', true))->searchable()->preload()
                ->helperText('Opsional. Kosong = kewenangan Fasilitas existing untuk tindakan penugasan.'),
            Forms\Components\Select::make('close_job_title_id')->label('Jabatan pengesahan akhir')
                ->relationship('closeTitle', 'name', fn ($query) => $query->where('is_active', true))->searchable()->preload()
                ->helperText('Isi jabatan Manager Fasilitas untuk membatasi pengesahan akhir. Kosong = perilaku Fasilitas existing.'),
            Forms\Components\Select::make('dispatch_job_title_id')->label('Jabatan teknisi lintas unit untuk laporan tanpa aset')
                ->relationship('dispatchTitle', 'name', fn ($query) => $query->where('is_active', true))->searchable()->preload()
                ->helperText('Opsional. Teknisi tetap harus anggota tim aktif dan memegang jabatan ini sesuai cakupan unit. Kosong mempertahankan batas unit existing.'),
            Forms\Components\Repeater::make('steps')->label('Tahapan persetujuan')
                ->schema([
                    Forms\Components\TextInput::make('key')->label('Kode unik (huruf kecil)')->required()->regex('/^[a-z][a-z0-9_]{0,59}$/'),
                    Forms\Components\TextInput::make('label')->label('Nama tahap')->required()->maxLength(150),
                    Forms\Components\Toggle::make('requires_estimate')->label('Menyetujui vendor & nominal estimasi')
                        ->helperText('Khusus tahap sebelum penugasan. Pencatat estimasi tidak boleh menyetujui nominalnya sendiri.'),
                    Forms\Components\Select::make('checkpoint')->label('Waktu persetujuan')->options([
                        'start' => 'Sebelum teknisi ditugaskan', 'final' => 'Sebelum pengesahan akhir',
                    ])->required(),
                    Forms\Components\Select::make('resolver')->label('Siapa yang menyetujui?')->options([
                        'reporter_supervisor' => 'Atasan pelapor', 'job_title' => 'Pemegang jabatan tertentu',
                    ])->required()->live(),
                    Forms\Components\Select::make('job_title_id')->label('Jabatan approver')
                        ->options(fn (): array => \App\Models\WorkOrderJobTitle::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()->required(fn (Forms\Get $get): bool => $get('resolver') === 'job_title')
                        ->visible(fn (Forms\Get $get): bool => $get('resolver') === 'job_title'),
                ])->columns(2)->minItems(1)->maxItems(12)->reorderable()
                ->helperText('Urutan sesuai posisi. Tidak menggunakan ekspresi atau kode bebas. Semua tahap wajib disetujui sebelum checkpoint.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('unit.name')->label('Unit')->placeholder('Semua unit'),
            Tables\Columns\TextColumn::make('version')->label('Versi')->sortable(),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('published_at')->label('Terbit')->dateTime(),
        ])->actions([
            Tables\Actions\EditAction::make()->visible(fn (WorkOrderWorkflow $record): bool => $record->status === 'draft'),
            Tables\Actions\Action::make('publish')->label('Publikasikan')->icon('heroicon-o-check-circle')
                ->color('success')->requiresConfirmation()
                ->visible(fn (WorkOrderWorkflow $record): bool => $record->status === 'draft')
                ->action(fn (WorkOrderWorkflow $record) => app(WorkOrderWorkflowService::class)->publish($record, auth()->user())),
        ])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => WorkOrderWorkflowResource\Pages\ListWorkflows::route('/'),
            'create' => WorkOrderWorkflowResource\Pages\CreateWorkflow::route('/create'),
            'edit' => WorkOrderWorkflowResource\Pages\EditWorkflow::route('/{record}/edit'),
        ];
    }
}
