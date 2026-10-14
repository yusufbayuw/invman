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
    protected static ?string $navigationGroup = 'Layanan & Ticketing';
    protected static ?string $navigationLabel = 'Tim & Hierarki Petugas';
    protected static ?string $modelLabel = 'Anggota Tim';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $slug = 'tim-teknis';
    protected static ?int $navigationSort = 3;

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
                        ->unique(table: 'ticket_team_members', column: 'user_id', ignoreRecord: true),
                    Forms\Components\TextInput::make('team_name')->label('Nama tim')
                        ->placeholder('Contoh: Teknisi Lapangan Kampus')
                        ->helperText('Anggota dan koordinator harus memakai nama tim yang sama.')
                        ->required()->maxLength(100)->live(onBlur: true),
                    Forms\Components\Select::make('team_type')->label('Jenis tim')->required()
                        ->options(['field' => 'Tim Teknis Lapangan', 'support' => 'Tim Teknis Pendukung'])
                        ->default('field')->native(false)->live(),
                    Forms\Components\Select::make('g002_m003_item_management_id')->label('Pengelola aset')
                        ->helperText('Opsional untuk tim fasilitas umum; isi agar penugasan dibatasi pada pengelola aset tersebut.')
                        ->options(fn (): array => G002M003ItemManagement::query()->orderBy('name')->pluck('name','id')->all())
                        ->searchable()->nullable()->live(),
                    Forms\Components\Select::make('position')->label('Jabatan')->required()
                        ->options(['lead' => 'Koordinator / Atasan', 'technician' => 'Petugas / Teknisi'])
                        ->default('technician')->native(false)->live(),
                    Forms\Components\Select::make('supervisor_user_id')->label('Atasan langsung')
                        ->helperText('Koordinator utama tidak memerlukan atasan. Teknisi wajib memilih atasan dalam tim yang sama.')
                        ->options(fn (Forms\Get $get): array => TicketTeamMember::query()
                            ->where('is_active', true)->where('position', 'lead')
                            ->where('team_name', $get('team_name'))
                            ->where('team_type', $get('team_type'))
                            ->where('g002_m003_item_management_id', $get('g002_m003_item_management_id'))
                            ->where('user_id', '!=', $get('user_id') ?: 0)
                            ->with('user')->get()
                            ->filter(fn ($member) => $member->user?->is_enabled)
                            ->mapWithKeys(fn ($member): array => [$member->user_id => $member->user->name])->all())
                        ->searchable()->nullable(),
                    Forms\Components\Toggle::make('is_active')->label('Aktif menerima tugas')
                        ->helperText('Nonaktifkan anggota tanpa menghapus riwayat tugas.')
                        ->default(true),
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
