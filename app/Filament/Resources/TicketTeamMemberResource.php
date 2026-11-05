<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketTeamMemberResource\Pages;
use App\Models\G002M003ItemManagement;
use App\Models\TicketTeamMember;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TicketTeamMemberResource extends Resource
{
    protected static ?string $model = TicketTeamMember::class;
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Tim & Hierarki Petugas';
    protected static ?string $modelLabel = 'Anggota Tim';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $slug = 'tim-teknis';

    public static function canViewAny(): bool { return auth()->user()?->isFacility() ?? false; }
    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit(Model $record): bool { return static::canViewAny(); }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Keanggotaan tim')
                ->description('Satu pengguna memiliki satu posisi dalam hierarki ticketing. Penugasan tidak mengubah role atau hak akses inventaris.')
                ->schema([
                    Forms\Components\Select::make('user_id')->label('Petugas / akun')
                        ->searchable()->required()->disabledOn('edit')
                        ->getSearchResultsUsing(fn (string $search): array => User::query()
                            ->where('is_enabled', true)
                            ->where(function ($query) use ($search): void {
                                $query->where('name', 'like', '%'.$search.'%')
                                    ->orWhere('email', 'like', '%'.$search.'%');
                            })->limit(30)->pluck('name', 'id')->all())
                        ->getOptionLabelUsing(fn ($value): ?string => User::query()->find($value)?->name)
                        ->unique(table: 'ticket_team_members', column: 'user_id', ignoreRecord: true)
                        ->helperText('Satu akun hanya boleh terdaftar sebagai satu anggota tim ticketing. Ini tidak mengubah role akun.'),
                    Forms\Components\TextInput::make('team_name')->label('Nama tim')
                        ->placeholder('Contoh: Teknisi Lapangan Kampus')
                        ->helperText('Anggota dan koordinator harus memakai nama tim yang sama.')
                        ->required()->maxLength(100)->live(onBlur: true)
                        ->helperText('Nama tim harus sama persis antara koordinator dan anggota; mengganti nama dapat memutus hubungan hierarki bila masih memiliki bawahan.'),
                    Forms\Components\Select::make('team_type')->label('Jenis tim')->required()
                        ->options(['field' => 'Tim Teknis Lapangan', 'support' => 'Tim Teknis Pendukung'])
                        ->default('field')->native(false)->live()
                        ->helperText('Lapangan dan Pendukung adalah hierarki terpisah. Petugas hanya dapat memakai koordinator dari jenis tim yang sama.'),
                    Forms\Components\Select::make('g002_m003_item_management_id')->label('Pengelola aset')
                        ->helperText('Kosong = tim fasilitas umum. Jika dipilih, tim dan atasannya dibatasi ke kelompok pengelola aset ini.')
                        ->options(fn (): array => G002M003ItemManagement::query()->orderBy('name')->pluck('name','id')->all())
                        ->searchable()->nullable()->live(),
                    Forms\Components\Select::make('position')->label('Jabatan')->required()
                        ->options(['lead' => 'Koordinator / Atasan', 'technician' => 'Petugas / Teknisi'])
                        ->default('technician')->native(false)->live()
                        ->helperText('Koordinator dapat mendelegasikan tiket yang telah disetujui. Teknisi wajib memiliki koordinator aktif pada tim yang sama.'),
                    Forms\Components\Select::make('supervisor_user_id')->label('Atasan langsung')
                        ->helperText('Teknisi wajib memilih koordinator aktif di tim dan pengelola yang sama. Koordinator utama boleh tanpa atasan; hierarki melingkar ditolak.')
                        ->options(fn (Forms\Get $get): array => TicketTeamMember::query()
                            ->where('is_active', true)->where('position', 'lead')
                            ->where('team_name', $get('team_name'))
                            ->where('team_type', $get('team_type'))
                            ->where('g002_m003_item_management_id', $get('g002_m003_item_management_id'))
                            ->where('user_id', '!=', $get('user_id') ?: 0)
                            ->with('user')->get()
                            ->filter(fn ($member) => $member->user?->is_enabled)
                            ->mapWithKeys(fn ($member): array => [$member->user_id => $member->user->name])->all())
                        ->searchable()->nullable()->live(),
                    Forms\Components\Toggle::make('is_active')->label('Aktif menerima tugas')
                        ->helperText('Nonaktifkan anggota tanpa menghapus riwayat tugas.')
                        ->default(true)->live(),
                    Forms\Components\Placeholder::make('team_member_impact')
                        ->label('Dampak posisi dan keaktifan')
                        ->content(fn (Forms\Get $get): string => ! $get('is_active')
                            ? 'Petugas nonaktif tidak dapat menerima atau melanjutkan pekerjaan baru, tetapi histori tugas tetap ada. Pindahkan bawahan aktif sebelum menonaktifkan koordinator.'
                            : ($get('position') === 'lead'
                                ? 'Koordinator aktif dapat menerima delegasi/menugaskan bawahan yang sesuai. Jabatan koordinator tidak otomatis memberikan persetujuan akhir.'
                                : 'Teknisi aktif hanya dapat ditugaskan pada tiket yang sesuai cakupan. Atasan koordinator aktif wajib dipilih.')
                                .($get('g002_m003_item_management_id')
                                    ? ' Tim dibatasi kelompok pengelola aset terpilih.'
                                    : ' Tanpa pengelola aset, tim mengikuti batas unit dan kewenangan fasilitas umum.')),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Petugas')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('team_name')->label('Tim')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('team_type')->label('Jenis')
                    ->formatStateUsing(fn (string $state): string => $state === 'field' ? 'Lapangan' : 'Pendukung')
                    ->badge(),
                Tables\Columns\TextColumn::make('position')->label('Jabatan')
                    ->formatStateUsing(fn (string $state): string => $state === 'lead' ? 'Koordinator' : 'Teknisi')
                    ->badge(),
                Tables\Columns\TextColumn::make('supervisor.name')->label('Atasan')->placeholder('Koordinator utama'),
                Tables\Columns\TextColumn::make('management.name')->label('Pengelola')->placeholder('Fasilitas umum')->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('team_type')->label('Jenis tim')
                    ->options(['field'=>'Lapangan','support'=>'Pendukung']),
                Tables\Filters\TernaryFilter::make('is_active')->label('Aktif'),
            ])
            ->defaultSort('team_name')
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([])
            ->emptyStateHeading('Belum ada tim teknis')
            ->emptyStateDescription('Tambahkan koordinator terlebih dahulu, lalu anggota tim di bawahnya.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTicketTeamMembers::route('/'),
            'create' => Pages\CreateTicketTeamMember::route('/create'),
            'edit' => Pages\EditTicketTeamMember::route('/{record}/edit'),
        ];
    }
}
