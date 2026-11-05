<?php

namespace App\Filament\Resources;

use App\Models\WorkOrderAssignment;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WorkOrderAssignmentResource extends Resource
{
    protected static ?string $model = WorkOrderAssignment::class;
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Penugasan Jabatan';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $slug = 'work-order-assignments';

    public static function canViewAny(): bool { return auth()->user()?->isAdmin() ?? false; }
    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return static::canViewAny(); }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')->label('Pegawai')->relationship('user', 'name')->searchable()->preload()->required()
                ->helperText('Akun pegawai yang memegang jabatan. Akun harus aktif agar penugasan dapat digunakan.'),
            Forms\Components\Select::make('work_order_job_title_id')->label('Jabatan')->relationship('jobTitle', 'name', fn ($query) => $query->where('is_active', true))->searchable()->preload()->required()
                ->helperText('Jabatan yang dipakai untuk memilih approver, penugasan teknisi, atau pengesahan. Jabatan saja tidak mengubah role Filament Shield.'),
            Forms\Components\Select::make('g001_m001_unit_id')->label('Cakupan unit')->placeholder('Semua unit')
                ->helperText('Pilih satu unit bila kewenangan hanya berlaku di unit tersebut. Kosong berarti seluruh unit, bukan hanya kantor pusat.')
                ->relationship('unit', 'name')->searchable()->preload()->live(),
            Forms\Components\Select::make('g002_m003_item_management_id')->label('Cakupan pengelola aset')->placeholder('Semua pengelola')
                ->helperText('Batasi jabatan pada satu kelompok pengelola, misalnya IT atau Perpustakaan. Kosong berarti semua kelompok dalam cakupan lainnya.')
                ->relationship('management', 'name')->searchable()->preload()->live(),
            Forms\Components\Select::make('supervisor_user_id')->label('Atasan langsung (opsional)')
                ->options(fn (): array => User::query()->where('is_enabled', true)->orderBy('name')->pluck('name', 'id')->all())->searchable()->live()
                ->helperText('Atasan langsung untuk penyelesaian tahap Atasan Pelapor. Tidak otomatis memberikan role global.'),
            Forms\Components\Toggle::make('is_active')->label('Penugasan aktif')->default(true)->live()
                ->helperText('Penugasan yang dinonaktifkan tidak lagi memenuhi syarat kewenangan dinamis; riwayat penugasan tidak dihapus.'),
            Forms\Components\Placeholder::make('assignment_impact')
                ->label('Dampak cakupan dan status')
                ->content(fn (Forms\Get $get): string => ! $get('is_active')
                    ? 'Penugasan nonaktif: pegawai kehilangan kewenangan dari penugasan ini, termasuk untuk keputusan tiket yang masih pending bila tak ada penugasan lain yang sah.'
                    : ($get('g001_m001_unit_id') ? 'Cakupan terbatas pada satu unit. ' : 'PERHATIAN: cakupan mencakup seluruh unit. ')
                        .($get('g002_m003_item_management_id') ? 'Dibatasi pada satu kelompok pengelola aset. ' : 'Mencakup semua kelompok pengelola dalam cakupan unit. ')
                        .($get('supervisor_user_id') ? 'Atasan dipakai pada alur persetujuan Atasan Pelapor.' : 'Belum ada atasan langsung yang ditetapkan.')
                        .' Perubahan bisa memengaruhi tiket pending; periksa antrean persetujuan sebelum menyimpan.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('user.name')->label('Pegawai')->searchable(),
            Tables\Columns\TextColumn::make('jobTitle.name')->label('Jabatan'),
            Tables\Columns\TextColumn::make('unit.name')->label('Unit')->placeholder('Semua unit'),
            Tables\Columns\TextColumn::make('management.name')->label('Pengelola')->placeholder('Semua pengelola'),
            Tables\Columns\TextColumn::make('supervisor.name')->label('Atasan')->placeholder('—'),
            Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => WorkOrderAssignmentResource\Pages\ListAssignments::route('/'),
            'create' => WorkOrderAssignmentResource\Pages\CreateAssignment::route('/create'),
            'edit' => WorkOrderAssignmentResource\Pages\EditAssignment::route('/{record}/edit'),
        ];
    }
}
