<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketCategoryResource\Pages;
use App\Models\TicketCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TicketCategoryResource extends Resource
{
    protected static ?string $model = TicketCategory::class;
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?string $navigationLabel = 'Kategori Laporan';
    protected static ?string $modelLabel = 'Kategori Laporan';
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?int $navigationSort = 7;
    protected static ?string $slug = 'kategori-laporan';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit(Model $record): bool { return static::canViewAny(); }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Kategori Laporan')->schema([
                Forms\Components\TextInput::make('name')->label('Nama kategori')
                    ->required()->maxLength(255)->live(onBlur: true)
                    ->helperText('Nama pilihan yang dilihat pelapor; perubahan label memengaruhi tampilan kategori, tidak menghapus tiket lama.')
                    ->afterStateUpdated(fn (Forms\Set $set, ?string $state) =>
                        $set('slug', 'maintenance_'.Str::slug((string) $state, '_'))),
                Forms\Components\TextInput::make('slug')->label('Kode unik')
                    ->helperText('Dibuat otomatis saat menambah kategori; kode kategori lama tidak boleh berubah.')
                    ->required()->maxLength(255)->regex('/^[a-z0-9_]+$/')
                    ->unique(ignoreRecord: true)->disabledOn('edit')
                    ->helperText('Identitas teknis yang dibuat otomatis saat tambah kategori. Pada kategori lama kode dikunci agar riwayat dan otomasi tidak rusak.'),
                Forms\Components\Toggle::make('is_active')->label('Kategori aktif')->default(true)->live()
                    ->helperText('Nonaktifkan kategori yang tidak digunakan lagi tanpa menghapus tiket historis.'),
                Forms\Components\Toggle::make('reporting_enabled')
                    ->label('Tampilkan sebagai pilihan di form laporan')->default(true)->live()
                    ->helperText('Jika mati, kategori tidak ditawarkan pada form laporan baru; tiket lama tetap mempertahankan kategorinya.'),
                Forms\Components\Placeholder::make('category_impact')
                    ->label('Dampak pilihan')
                    ->content(fn (Forms\Get $get): string => ! $get('is_active')
                        ? 'Kategori nonaktif: tidak dipakai untuk laporan baru. Referensi pada tiket lama tidak dihapus.'
                        : ($get('reporting_enabled')
                            ? 'Kategori aktif dan ditampilkan sebagai pilihan pada form laporan baru.'
                            : 'Kategori tetap aktif untuk data existing, tetapi disembunyikan dari pilihan form laporan baru.')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $table->headerActions(\App\Filament\Actions\ResourceDataActions::make(static::class));

        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('slug')->label('Kode')->searchable(),
            Tables\Columns\IconColumn::make('is_active')->label('Aktif')->boolean(),
            Tables\Columns\IconColumn::make('reporting_enabled')->label('Form laporan')->boolean(),
            Tables\Columns\TextColumn::make('tickets_count')->counts('tickets')->label('Jumlah Tiket'),
        ])->actions([Tables\Actions\EditAction::make()])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTicketCategories::route('/'),
            'create' => Pages\CreateTicketCategory::route('/create'),
            'edit' => Pages\EditTicketCategory::route('/{record}/edit'),
        ];
    }
    public static function getRelations(): array
    {
        return [\App\Filament\Resources\RelationManagers\TicketCategoryTicketsRelationManager::class];
    }
}
