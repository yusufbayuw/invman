<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoanEventResource\Pages;
use App\Models\LoanEvent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LoanEventResource extends Resource
{
    protected static ?string $model = LoanEvent::class;
    protected static ?string $navigationGroup = 'Peminjaman';
    protected static ?string $navigationLabel = 'Kegiatan Master';
    protected static ?string $modelLabel = 'Kegiatan Master';
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?int $navigationSort = -5;
    protected static ?string $slug = 'kegiatan-master';

    public static function canViewAny(): bool
    {
        return auth()->check()
            && (auth()->user()->isSarpras() || auth()->user()->isFacility())
            && filled(auth()->user()->g001_m001_unit_id);
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny()
            && (string) $record->g001_m001_unit_id === (string) auth()->user()->g001_m001_unit_id;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canView($record) && ! $record->requests()->exists();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('g001_m001_unit_id', auth()->user()?->g001_m001_unit_id)
            ->withCount('requests');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nama Kegiatan')->required()->maxLength(255),
            Forms\Components\Textarea::make('description')
                ->label('Deskripsi')->rows(3),
            Forms\Components\DateTimePicker::make('start_time')
                ->label('Jadwal Mulai (opsional)')->seconds(false)->native(false),
            Forms\Components\DateTimePicker::make('end_time')
                ->label('Jadwal Selesai (opsional)')->seconds(false)->native(false)
                ->after('start_time'),
        ])->columns(2);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Identitas Kegiatan')
                ->schema([
                    Infolists\Components\TextEntry::make('name')->label('Nama'),
                    Infolists\Components\TextEntry::make('description')->label('Deskripsi')->placeholder('-'),
                    Infolists\Components\TextEntry::make('unit.name')->label('Unit'),
                    Infolists\Components\TextEntry::make('start_time')->label('Mulai')->dateTime()->placeholder('-'),
                    Infolists\Components\TextEntry::make('end_time')->label('Selesai')->dateTime()->placeholder('-'),
                    Infolists\Components\TextEntry::make('requests_count')
                        ->label('Jumlah Pengajuan')
                        ->state(fn (LoanEvent $record): int => $record->requests()->count()),
                ])->columns(2),
            Infolists\Components\Section::make('Pengajuan terkait')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('requests')
                        ->hiddenLabel()
                        ->schema([
                            Infolists\Components\TextEntry::make('name')
                                ->label('Pengajuan')
                                ->url(fn (\App\Models\G004M008Activity $record): string =>
                                    G004M008ActivityResource::getUrl('view', ['record' => $record])),
                            Infolists\Components\TextEntry::make('status')
                                ->label('Status')->badge(),
                            Infolists\Components\TextEntry::make('start_time')
                                ->label('Mulai')->dateTime(),
                        ])->columns(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Kegiatan')->searchable()->sortable()->wrap(),
            Tables\Columns\TextColumn::make('unit.name')->label('Unit'),
            Tables\Columns\TextColumn::make('start_time')->label('Mulai')->dateTime('d M Y H:i')->placeholder('-'),
            Tables\Columns\TextColumn::make('requests_count')->label('Pengajuan')->badge(),
            Tables\Columns\TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y')->sortable(),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make()
                ->visible(fn (LoanEvent $record): bool => static::canEdit($record)),
        ])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoanEvents::route('/'),
            'create' => Pages\CreateLoanEvent::route('/create'),
            'view' => Pages\ViewLoanEvent::route('/{record}'),
            'edit' => Pages\EditLoanEvent::route('/{record}/edit'),
        ];
    }
}
