<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketResource\Pages;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\TicketService;
use App\Services\TicketVisibility;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;
    protected static ?string $navigationGroup = 'Layanan & Ticketing';
    protected static ?string $navigationLabel = 'Tiket Layanan';
    protected static ?string $modelLabel = 'Tiket';
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $slug = 'tiket';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'number';

    public static function canViewAny(): bool
    {
        return app(TicketService::class)->canCreate(auth()->user());
    }

    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canView(Model $record): bool
    {
        return $record instanceof Ticket && app(TicketVisibility::class)->canView(auth()->user(), $record);
    }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return app(TicketVisibility::class)->query(auth()->user());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Laporan Baru')->schema([
                Forms\Components\Select::make('ticket_category_id')
                    ->label('Jenis laporan')
                    ->options(fn (): array => TicketCategory::query()->where('is_active', true)
                        ->orderBy('name')->pluck('name','id')->all())
                    ->required()->searchable(),
                Forms\Components\Select::make('asset_type')
                    ->label('Terkait aset (opsional)')
                    ->options([
                        'item' => 'Barang (jenis)',
                        'item_instance' => 'Barang satuan / kode inventaris',
                        'room' => 'Ruangan',
                        'vehicle' => 'Kendaraan',
                    ])->live(),
                Forms\Components\Select::make('asset_id')
                    ->label('Nama aset')
                    ->options(fn (Forms\Get $get): array => app(TicketService::class)
                        ->assetOptions((string) ($get('asset_type') ?? '')))
                    ->searchable()->visible(fn (Forms\Get $get): bool => filled($get('asset_type')))
                    ->required(fn (Forms\Get $get): bool => filled($get('asset_type'))),
                Forms\Components\TextInput::make('title')
                    ->label('Judul masalah / permintaan')->required()->minLength(5)->maxLength(255)->columnSpanFull(),
                Forms\Components\Textarea::make('description')
                    ->label('Jelaskan masalah, lokasi dan dampaknya')
                    ->required()->minLength(10)->maxLength(10000)->rows(5)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('number')->label('Tiket')->searchable()->copyable(),
            Tables\Columns\TextColumn::make('title')->label('Masalah')->searchable()->limit(55)->wrap(),
            Tables\Columns\TextColumn::make('category.name')->label('Kategori'),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('priority')->label('Prioritas')->badge()
                ->color(fn (string $state): string => match ($state) {
                    'critical' => 'danger', 'high' => 'warning', default => 'gray',
                }),
            Tables\Columns\TextColumn::make('reporter.name')->label('Pelapor')->toggleable(),
            Tables\Columns\TextColumn::make('management.name')->label('Pengelola')->placeholder('-')->toggleable(),
            Tables\Columns\TextColumn::make('assignee.name')->label('Petugas')->placeholder('-'),
            Tables\Columns\TextColumn::make('created_at')->label('Dilaporkan')->dateTime('d M Y H:i')->sortable(),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('status')->options([
                'open'=>'Open', 'triaged'=>'Triaged', 'in_progress'=>'In Progress',
                'waiting_requester'=>'Menunggu Pelapor', 'waiting_parts'=>'Menunggu Suku Cadang',
                'resolved'=>'Resolved', 'closed'=>'Closed', 'reopened'=>'Reopened', 'cancelled'=>'Dibatalkan',
            ]),
            Tables\Filters\SelectFilter::make('ticket_category_id')->label('Kategori')
                ->relationship('category','name'),
        ])
        ->defaultSort('created_at','desc')
        ->actions([Tables\Actions\ViewAction::make()])
        ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Tiket')->schema([
                Infolists\Components\TextEntry::make('number')->label('Nomor')->copyable(),
                Infolists\Components\TextEntry::make('status')->label('Status')->badge(),
                Infolists\Components\TextEntry::make('priority')->label('Prioritas')->badge(),
                Infolists\Components\TextEntry::make('category.name')->label('Kategori'),
                Infolists\Components\TextEntry::make('title')->label('Judul')->columnSpanFull(),
                Infolists\Components\TextEntry::make('description')->label('Laporan')->columnSpanFull(),
                Infolists\Components\TextEntry::make('reporter.name')->label('Pelapor'),
                Infolists\Components\TextEntry::make('unit.name')->label('Unit')->placeholder('-'),
                Infolists\Components\TextEntry::make('management.name')->label('Pengelola')->placeholder('-'),
                Infolists\Components\TextEntry::make('assignee.name')->label('Petugas')->placeholder('-'),
                Infolists\Components\TextEntry::make('activity.name')->label('Pengajuan terkait')->placeholder('-'),
                Infolists\Components\TextEntry::make('created_at')->dateTime('d M Y H:i')->label('Dibuat'),
            ])->columns(2),
            Infolists\Components\Section::make('Aset terkait')->schema([
                Infolists\Components\RepeatableEntry::make('assets')->hiddenLabel()->schema([
                    Infolists\Components\TextEntry::make('asset_type')->label('Jenis'),
                    Infolists\Components\TextEntry::make('asset_id')->label('ID aset'),
                ])->columns(2),
            ])->collapsible(),
            Infolists\Components\Section::make('Percakapan')->schema([
                Infolists\Components\RepeatableEntry::make('visibleComments')
                    ->label('Komentar')->schema([
                        Infolists\Components\TextEntry::make('author.name')->label('Penulis'),
                        Infolists\Components\TextEntry::make('created_at')->dateTime('d M Y H:i')->label('Waktu'),
                        Infolists\Components\TextEntry::make('body')->label('Isi')->columnSpanFull(),
                        Infolists\Components\IconEntry::make('is_internal')->label('Internal')->boolean(),
                    ])->columns(2),
            ])->collapsible(),
            Infolists\Components\Section::make('Audit perubahan')->schema([
                Infolists\Components\RepeatableEntry::make('events')->hiddenLabel()->schema([
                    Infolists\Components\TextEntry::make('action')->label('Aksi'),
                    Infolists\Components\TextEntry::make('actor.name')->label('Aktor')->placeholder('-'),
                    Infolists\Components\TextEntry::make('from_value')->label('Dari')->placeholder('-'),
                    Infolists\Components\TextEntry::make('to_value')->label('Ke')->placeholder('-'),
                    Infolists\Components\TextEntry::make('created_at')->dateTime('d M Y H:i')->label('Waktu'),
                ])->columns(3),
            ])->collapsed(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'view' => Pages\ViewTicket::route('/{record}'),
        ];
    }
}
