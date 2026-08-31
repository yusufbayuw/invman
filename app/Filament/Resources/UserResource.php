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
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\UserResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Spatie\Permission\Models\Role;
use App\Filament\Resources\UserResource\RelationManagers;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'Manajemen';
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
                            ->label('Pengelolaan Barang')
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
                    ->helperText('JPG, PNG, atau WebP. Ukuran maksimal 2 MB. Kosongkan untuk menggunakan avatar bawaan.')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('name')
                    ->label('Name')
                    ->required(),
                Forms\Components\TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->unique(ignoreRecord:true)
                    ->required(),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->unique(ignoreRecord:true)
                    ->dehydrateStateUsing(static fn (null|string $state): null|string => filled($state) ? Hash::make($state) : null,)
                    ->dehydrated(static fn (null|string $state): bool => filled($state)),
                Forms\Components\TextInput::make('username'),
                Forms\Components\Select::make('g001_m001_unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload()
                    ->required(function (Get $get): bool {
                        $sarprasRoleId = Role::query()->where('name', config('role.sarpras'))->value('id');

                        return $sarprasRoleId && in_array($sarprasRoleId, $get('roles') ?? []);
                    })
                    ->helperText('Wajib untuk akun sarpras; kosongkan untuk admin dan fasilitas.'),
                Forms\Components\Select::make('roles')
                    ->relationship('roles', 'name', fn (Builder $query) => $query->whereIn('name', [
                        config('role.admin'),
                        config('role.fasilitas'),
                        config('role.sarpras'),
                    ]))
                    ->multiple()
                    ->preload()
                    ->searchable(),
                Forms\Components\Select::make('itemManagements')
                    ->label('Pengelolaan Barang')
                    ->relationship('itemManagements', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText('User memperoleh kewenangan flow hanya untuk aset kelompok yang dipilih.')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
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
                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Unit')
                    ->placeholder('Akses global')
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge(),
                Tables\Columns\TextColumn::make('itemManagements.name')
                    ->label('Pengelolaan Barang')
                    ->badge()
                    ->placeholder('Tidak ada'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageUsers::route('/'),
        ];
    }
}
