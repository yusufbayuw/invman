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
    protected static ?int $navigationSort = 8;
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
                ->relationship('unit', 'name')->searchable()->preload()->live()
                ->helperText('Kosong = template untuk seluruh unit. Jika ada workflow published khusus unit, aturan unit tersebut diprioritaskan. Cakupan tidak dapat diubah setelah dibuat.')
                ->disabled(fn (?WorkOrderWorkflow $record): bool => $record?->exists ?? false),
            Forms\Components\Placeholder::make('workflow_scope_impact')
                ->label('Dampak cakupan')
                ->content(fn (Forms\Get $get): string => $get('g001_m001_unit_id')
                    ? 'Workflow ini hanya berlaku untuk tiket baru dari unit terpilih, menggantikan template global bagi unit tersebut setelah dipublikasikan.'
                    : 'Workflow ini dapat berlaku untuk tiket baru seluruh unit yang tidak memiliki workflow khusus. Periksa kecocokan seluruh organisasi sebelum publikasi.'),
            Forms\Components\Placeholder::make('version_info')
                ->label('Versi')
                ->content(fn (?WorkOrderWorkflow $record): string => $record ? 'Versi '.$record->version.' · '.$record->status : 'Draft versi baru (belum berlaku)'),
            Forms\Components\Select::make('assign_job_title_id')->label('Jabatan untuk persetujuan & penugasan teknisi')
                ->relationship('assignTitle', 'name', fn ($query) => $query->where('is_active', true))->searchable()->preload()
                ->helperText('Pejabat yang boleh menyetujui dan menugaskan teknisi. Jika kosong, gunakan kewenangan Fasilitas existing. Pemegang jabatan harus aktif dan sesuai cakupan.')->live(),
            Forms\Components\Select::make('close_job_title_id')->label('Jabatan pengesahan akhir')
                ->relationship('closeTitle', 'name', fn ($query) => $query->where('is_active', true))->searchable()->preload()
                ->helperText('Jabatan untuk pengesahan penyelesaian. Kosong mempertahankan kewenangan Fasilitas existing; tidak membuat pekerjaan otomatis disetujui.')->live(),
            Forms\Components\Select::make('dispatch_job_title_id')->label('Jabatan teknisi lintas unit untuk laporan tanpa aset')
                ->relationship('dispatchTitle', 'name', fn ($query) => $query->where('is_active', true))->searchable()->preload()
                ->helperText('Khusus laporan tanpa aset: mengizinkan teknisi lintas unit bila masih anggota tim aktif dan mempunyai jabatan yang sesuai. Kosong = batas unit existing.')->live(),
            Forms\Components\Placeholder::make('workflow_authority_impact')
                ->label('Dampak pembagian kewenangan')
                ->content(fn (Forms\Get $get): string =>
                    ($get('assign_job_title_id') ? 'Penugasan teknisi mensyaratkan jabatan yang dipilih. ' : 'Penugasan teknisi mengikuti hak Fasilitas existing. ')
                    .($get('close_job_title_id') ? 'Pengesahan akhir mensyaratkan jabatan khusus. ' : 'Pengesahan akhir mengikuti hak Fasilitas existing. ')
                    .($get('dispatch_job_title_id') ? 'Dispatch teknisi lintas unit untuk laporan tanpa aset dapat diizinkan hanya untuk pemegang jabatan yang sesuai.' : 'Tidak ada kewenangan dispatch lintas unit tambahan.')),
            Forms\Components\Repeater::make('steps')->label('Tahapan persetujuan')
                ->schema([
                    Forms\Components\TextInput::make('key')->label('Kode unik (huruf kecil)')->required()->regex('/^[a-z][a-z0-9_]{0,59}$/')
                        ->helperText('Identitas permanen tahap pada workflow. Harus unik, huruf kecil/angka/garis bawah, dan tidak boleh sama dengan tahap lain.'),
                    Forms\Components\TextInput::make('label')->label('Nama tahap')->required()->maxLength(150)
                        ->helperText('Label keputusan yang dibaca approver dan dicatat pada riwayat tiket.'),
                    Forms\Components\Toggle::make('requires_estimate')->label('Menyetujui vendor & nominal estimasi')->live()
                        ->helperText('Hanya untuk tahap sebelum penugasan. Jika aktif, approver menyetujui estimasi vendor/biaya terkini; pencatat tidak boleh menyetujui sendiri.'),
                    Forms\Components\Select::make('checkpoint')->label('Waktu persetujuan')->options([
                        'start' => 'Sebelum teknisi ditugaskan', 'final' => 'Sebelum pengesahan akhir',
                    ])->required()->live()->helperText('Tahap start menghalangi penugasan; final harus diselesaikan sebelum pengesahan akhir. Semua start wajib mendahului final.'),
                    Forms\Components\Select::make('resolver')->label('Siapa yang menyetujui?')->options([
                        'reporter_supervisor' => 'Atasan pelapor', 'job_title' => 'Pemegang jabatan tertentu',
                    ])->required()->live()->helperText('Atasan pelapor ditentukan dari Penugasan Jabatan; pemegang jabatan dipilih dari penugasan aktif sesuai unit/pengelola.'),
                    Forms\Components\Select::make('job_title_id')->label('Jabatan approver')
                        ->options(fn (): array => \App\Models\WorkOrderJobTitle::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()->live()
                        ->required(fn (Forms\Get $get): bool => $get('resolver') === 'job_title')
                        ->visible(fn (Forms\Get $get): bool => $get('resolver') === 'job_title')
                        ->helperText('Hanya pegawai dengan jabatan dan penugasan aktif serta cakupan sesuai yang memenuhi syarat menyetujui.'),
                    Forms\Components\Placeholder::make('workflow_step_impact')
                        ->label('Dampak tahap')
                        ->content(fn (Forms\Get $get): string =>
                            ($get('checkpoint') === 'final'
                                ? 'Tahap akhir: keputusan ini diperlukan sebelum pengesahan penyelesaian. '
                                : 'Tahap awal: keputusan ini diperlukan sebelum teknisi ditugaskan. ')
                            .($get('resolver') === 'job_title'
                                ? 'Approver dipilih dari pemegang jabatan yang ditentukan sesuai cakupan.'
                                : 'Approver ditentukan dari atasan aktif pelapor.')
                            .($get('requires_estimate') ? ' Versi estimasi vendor/biaya terbaru juga wajib disetujui.' : ''))
                        ->columnSpanFull(),
                ])->columns(2)->minItems(1)->maxItems(12)->reorderable()
                ->helperText('Urutan tahap menentukan urutan keputusan. Semua tahap wajib disetujui sesuai checkpoint; perubahan baru berlaku setelah versi draft dipublikasikan.'),
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
                ->modalHeading('Publikasikan aturan Work Order?')
                ->modalDescription(fn (WorkOrderWorkflow $record): string =>
                    'Unit: '.($record->unit?->name ?? 'SEMUA UNIT (template global)').'. '
                    .'Versi: '.$record->version.'. '
                    .'Jumlah tahap: '.count($record->steps ?? []).'. '
                    .'Versi ini menjadi aturan untuk tiket BARU dan tidak dapat diedit atau dihapus. '
                    .'Tiket lama tetap menggunakan snapshot workflow sebelumnya. '
                    .'Pastikan jabatan, cakupan, dan approver aktif sudah diperiksa.')
                ->modalSubmitActionLabel('Ya, publikasikan')
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
