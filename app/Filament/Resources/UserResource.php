<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Get;
use App\Models\User;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Hash;
use App\Support\AccountPasswordPolicy;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\UserResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Spatie\Permission\Models\Role;
use App\Filament\Resources\UserResource\RelationManagers;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $slug = 'users';
    protected static ?string $modelLabel = 'Pengguna';
    protected static ?string $navigationLabel = 'Pengguna';

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Split::make([
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('name')
                            ->label('Name')
                            ->weight('bold')
                            ->size('lg'),
                        \Filament\Infolists\Components\TextEntry::make('email')
                            ->label('E-mail')
                            ->size('md'),
                        \Filament\Infolists\Components\TextEntry::make('itemManagements.name')
                            ->label('Kelompok Aset yang Dikelola')
                            ->badge()
                            ->placeholder('Tidak ada'),
                    ]),
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('created_at')
                            ->label('Created at')
                            ->dateTime(),
                        \Filament\Infolists\Components\TextEntry::make('updated_at')
                            ->label('Updated at')
                            ->dateTime(),
                    ])
                ])->from('md'),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('avatar')
                    ->label('Avatar')
                    ->avatar()
                    ->image()
                    ->imageEditor()
                    ->disk(config('chatify.storage_disk_name'))
                    ->directory(config('chatify.user_avatar.folder'))
                    ->visibility('public')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(2048)
                    ->formatStateUsing(static function (?string $state): ?string {
                        if (blank($state) || $state === config('chatify.user_avatar.default')) {
                            return null;
                        }

                        return Str::startsWith($state, config('chatify.user_avatar.folder') . '/')
                            ? $state
                            : config('chatify.user_avatar.folder') . '/' . $state;
                    })
                    ->mutateDehydratedStateUsing(static fn (?string $state): string => filled($state)
                        ? basename($state)
                        : config('chatify.user_avatar.default'))
                    ->helperText('JPG, PNG, atau WebP, maksimal 2 MB. Avatar hanya memengaruhi identitas visual; tidak mengubah kewenangan. Kosongkan untuk avatar bawaan.')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('name')
                    ->label('Nama lengkap')
                    ->required()
                    ->helperText('Nama pegawai yang tampil pada daftar pengguna, penugasan, dan riwayat tindakan. Perubahan nama tidak mengubah identitas akun.'),
                Forms\Components\TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->unique(ignoreRecord:true)
                    ->required()
                    ->helperText('Alamat email unik untuk identitas akun dan komunikasi. Mengubahnya memengaruhi alamat yang tersimpan, bukan histori aktor.'),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->helperText('Wajib saat akun dibuat. Saat edit, biarkan kosong agar password lama tidak berubah. Isi ulang akan mengganti kredensial akun.')
                    ->rules([AccountPasswordPolicy::rule()])
                    ->dehydrateStateUsing(static fn (null|string $state): null|string => filled($state) ? Hash::make($state) : null,)
                    ->dehydrated(static fn (null|string $state): bool => filled($state)),
                Forms\Components\TextInput::make('username')
                    ->label('Username')
                    ->helperText('Identitas yang digunakan saat login. Mengubah username dapat membuat pengguna perlu memakai nama login baru.'),
                // Do not reuse Chatify active_status: it represents presence, not permission.
                Forms\Components\Toggle::make('is_enabled')
                    ->label('Akun dapat login')
                    ->default(true)
                    ->live()
                    ->disabled(fn (?User $record): bool => $record?->id === auth()->id())
                    ->helperText('Jika nonaktif, akun tidak dapat masuk/melanjutkan akses. Riwayat tindakan sebelumnya tetap tersimpan.'),
                Forms\Components\Select::make('g001_m001_unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(function (Get $get): bool {
                        $sarprasRoleId = Role::query()->where('name', config('role.sarpras'))->value('id');

                        return $sarprasRoleId && in_array($sarprasRoleId, $get('roles') ?? []);
                    })
                    ->helperText('Wajib untuk akun Sarpras. Unit menentukan cakupan data dan operasi berbasis unit; kosong hanya untuk akun yang tidak memerlukannya.'),
                Forms\Components\Select::make('roles')
                    ->label('Peran Dasar (manual)')
                    ->relationship('roles', 'name', fn (Builder $query) => $query->whereIn('name', [
                        config('role.admin'),
                        config('role.fasilitas'),
                        config('role.sarpras'),
                    ]))
                    // Auto-managed roles must never be exposed as editable choices,
                    // dropped on hydration, or detached when manual roles change.
                    ->loadStateFromRelationshipsUsing(function (Forms\Components\Select $component, ?User $record): void {
                        $names = [config('role.admin'), config('role.fasilitas'), config('role.sarpras')];
                        $component->state($record?->roles()->whereIn('name', $names)
                            ->pluck('roles.id')->map(fn ($id): string => (string) $id)->all() ?? []);
                    })
                    ->saveRelationshipsUsing(function (User $record, ?array $state): void {
                        $manualNames = [config('role.admin'), config('role.fasilitas'), config('role.sarpras')];
                        $selected = Role::query()->where('guard_name', 'web')
                            ->whereIn('name', $manualNames)
                            ->whereIn('id', array_map('intval', (array) $state))
                            ->pluck('name')->all();
                        $protected = $record->roles()->whereNotIn('name', $manualNames)
                            ->pluck('roles.name')->all();
                        $record->syncRoles(array_values(array_unique(array_merge($protected, $selected))));
                    })
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->live()
                    ->helperText('Pilih peran dasar saja. Role pengelola_barang otomatis mengikuti Kelompok Aset yang Dikelola, tidak perlu dipilih manual. Penugasan Jabatan Work Order tetap terpisah.'),
                Forms\Components\Select::make('itemManagements')
                    ->label('Kelompok Aset yang Dikelola')
                    ->relationship('itemManagements', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->live()
                    ->helperText('Pilih IT, Perpustakaan, atau kelompok lain. Keanggotaan otomatis memberi/mencabut role pengelola_barang; hak melihat/mengubah Barang tetap mengikuti izin Shield dan kelompok yang dipilih. Tidak membuat jabatan atau kewenangan approval Work Order.')
                    ->columnSpanFull(),
                Forms\Components\Placeholder::make('automatic_asset_manager_role')
                    ->label('Status role Pengelola Barang (otomatis)')
                    ->content(fn (Get $get): string => count(array_filter((array) $get('itemManagements'))) > 0
                        ? 'Aktif otomatis setelah disimpan karena pegawai mempunyai kelompok aset. Role ini mengatur izin fitur, bukan jabatan Work Order.'
                        : 'Tidak aktif setelah disimpan jika tidak memiliki kelompok aset. Peran dasar lain tidak berubah.')
                    ->columnSpanFull(),
                Forms\Components\Placeholder::make('user_access_impact')
                    ->label('Dampak konfigurasi pengguna')
                    ->content(function (Get $get): string {
                        if ($get('is_enabled') === false) {
                            return 'Akun dinonaktifkan: pengguna tidak dapat melanjutkan akses meskipun masih memiliki role atau penugasan aktif. Riwayat tidak dihapus.';
                        }

                        $selected = Role::query()->whereIn('id', array_filter((array) $get('roles')))
                            ->pluck('name')->all();

                        if (in_array(config('role.admin'), $selected, true)) {
                            return 'PERHATIAN: role Admin memiliki kewenangan global/super-admin. Berikan hanya kepada pengelola sistem yang dipercaya.';
                        }

                        if (in_array(config('role.sarpras'), $selected, true) && ! $get('g001_m001_unit_id')) {
                            return 'Role Sarpras membutuhkan unit. Tanpa unit yang sesuai, pengguna tidak dapat menjalankan alur Sarpras unit secara benar.';
                        }

                        $scope = $get('g001_m001_unit_id')
                            ? 'Akun terkait dengan satu unit.'
                            : 'Akun belum terkait unit; hanya sesuai untuk peran yang tidak mensyaratkan unit.';
                        $management = count(array_filter((array) $get('itemManagements')))
                            ? ' Kelompok aset dipilih; role pengelola_barang otomatis aktif, dengan batas data sesuai keanggotaan dan permission Shield.'
                            : ' Tidak ada kelompok aset; role pengelola_barang otomatis nonaktif.';

                        return $scope.$management.' Jabatan Work Order dan keanggotaan tim diatur terpisah.';
                    })
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $table->headerActions(\App\Filament\Actions\ResourceDataActions::make(static::class));

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('username')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_enabled')
                    ->label('Akun aktif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Unit')
                    ->placeholder('Akses global')
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge(),
                Tables\Columns\TextColumn::make('itemManagements.name')
                    ->label('Kelompok Aset yang Dikelola')
                    ->badge()
                    ->placeholder('Tidak ada'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()->url(fn (User $record): string => static::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageUsers::route('/'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\RelationManagers\UserManagementsRelationManager::class,
            \App\Filament\Resources\RelationManagers\UserVehicleChecklistsRelationManager::class,
            \App\Filament\Resources\RelationManagers\UserRoomChecklistsRelationManager::class,
            \App\Filament\Resources\RelationManagers\UserItemInstanceChecklistRelationManager::class,
            \App\Filament\Resources\RelationManagers\UserVehicleReviewRelationManager::class,
            \App\Filament\Resources\RelationManagers\UserRoomReviewsRelationManager::class,
            \App\Filament\Resources\RelationManagers\UserItemReviewsRelationManager::class,
            \App\Filament\Resources\RelationManagers\UserVehicleHistoryRelationManager::class,
            \App\Filament\Resources\RelationManagers\UserRoomHistoryRelationManager::class,
            \App\Filament\Resources\RelationManagers\UserItemHistoryRelationManager::class,
            \App\Filament\Resources\RelationManagers\UserReportedTicketsRelationManager::class,
            \App\Filament\Resources\RelationManagers\UserTicketTeamMemberRelationManager::class,
            \App\Filament\Resources\RelationManagers\UserWorkOrderAssignmentsRelationManager::class,\App\Filament\Resources\RelationManagers\UserActivityRelationManager::class];
    }
}
