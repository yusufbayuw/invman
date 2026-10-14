<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketResource\Pages;
use App\Models\Ticket;
use App\Models\TicketTeamMember;
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
        return app(TicketService::class)->canCreate(auth()->user())
            || (auth()->user()?->ticketTeamMember()->where('is_active', true)->exists() ?? false);
    }

    public static function canCreate(): bool { return app(TicketService::class)->canCreate(auth()->user()); }
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

    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'title'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return static::getEloquentQuery();
    }

    /** Seven reporting categories; legacy ticket categories remain valid in stored history. */
    public static function reportingCategoryOptions(): array
    {
        return TicketCategory::query()->where('is_active', true)->where('reporting_enabled', true)
            ->orderBy('id')->pluck('name', 'id')->all();
    }

    public static function assetSearchFields(): array
    {
        return [
            Forms\Components\Select::make('asset_type')->label('Jenis inventaris')
                ->options(['item' => 'Jenis Barang', 'item_instance' => 'Barang Satuan', 'room' => 'Ruangan', 'vehicle' => 'Kendaraan'])
                ->default(fn () => in_array(request()->query('asset_type'), ['item','item_instance','room','vehicle'], true)
                    ? request()->query('asset_type') : null)
                ->live()->required()
                ->afterStateUpdated(fn (Forms\Set $set) => $set('asset_id', null)),
            Forms\Components\Select::make('room_filter')->label('Saring berdasarkan ruangan (opsional)')
                ->searchable()->getSearchResultsUsing(fn (string $search): array => app(TicketService::class)->searchRooms($search))
                ->getOptionLabelUsing(fn ($value): ?string => app(TicketService::class)->roomOptionLabel($value))
                ->visible(fn (Forms\Get $get): bool => in_array($get('asset_type'), ['item', 'item_instance'], true))
                ->afterStateUpdated(fn (Forms\Set $set) => $set('asset_id', null))
                ->live()->dehydrated(false),
            Forms\Components\Select::make('asset_id')->label('Cari nama atau kode inventaris')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search, Forms\Get $get): array =>
                    app(TicketService::class)->searchAssets(
                        (string) $get('asset_type'), $search, $get('room_filter') ? (int) $get('room_filter') : null,
                    ))
                ->getOptionLabelUsing(fn ($value, Forms\Get $get): ?string =>
                    app(TicketService::class)->assetOptionLabel((string) $get('asset_type'), $value))
                ->default(fn () => is_numeric(request()->query('asset_id')) ? request()->query('asset_id') : null)
                ->helperText('Hanya hasil yang diizinkan; ketik nama atau kode, bukan memuat seluruh inventaris.')
                ->required(),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Lapor Kerusakan')
                ->description('Nama, unit, tanggal dan nomor tiket terisi otomatis. Tidak perlu mengetahui kode inventaris.')
                ->schema([
                    Forms\Components\Select::make('ticket_category_id')->label('Apa yang rusak?')
                        ->options(fn (): array => static::reportingCategoryOptions())
                        ->default(fn () => request()->query('asset_type') === 'room'
                            ? TicketCategory::query()->where('slug', 'maintenance_building')->value('id') : null)
                        ->required()->native(false),
                    Forms\Components\TextInput::make('location')->label('Lokasi kerusakan')
                        ->placeholder('Contoh: Gedung B, ruang rapat lantai 2')
                        ->default(fn () => mb_substr((string) request()->query('location'), 0, 255))
                        ->required()->maxLength(255),
                    Forms\Components\Textarea::make('description')->label('Apa masalahnya?')
                        ->placeholder('Jelaskan kondisi dan dampaknya secara singkat')
                        ->required()->minLength(10)->maxLength(10000)->rows(3)->columnSpanFull(),
                    Forms\Components\Radio::make('reported_urgency')->label('Seberapa mendesak?')
                        ->options([
                            'normal' => 'Biasa — tidak mengganggu kegiatan utama',
                            'urgent' => 'Penting — perlu segera ditangani',
                            'emergency' => 'Darurat — berbahaya atau mengganggu operasional utama',
                        ])->default('normal')->required()->columnSpanFull(),
                    Forms\Components\Toggle::make('link_asset')
                        ->label('Saya mengetahui barang/ruangan yang terdaftar di inventaris')
                        ->helperText('Opsional. Jika tidak tahu, laporkan saja; pengelola dapat mengaitkannya nanti.')
                        ->default(fn () => request()->has('asset_id'))->live()->columnSpanFull(),
                    Forms\Components\Group::make(static::assetSearchFields())
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('link_asset'))
                        ->dehydrated(fn (Forms\Get $get): bool => (bool) $get('link_asset'))
                        ->columns(2)->columnSpanFull(),
                    Forms\Components\Hidden::make('title'),
                    Forms\Components\FileUpload::make('files')->label('Foto kerusakan (opsional)')
                        ->multiple()->maxFiles(3)->maxSize(5120)
                        ->acceptedFileTypes(['image/jpeg','image/png','application/pdf'])
                        ->disk('local')->directory('tickets/attachments')->visibility('private')
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('number')->label('Tiket')->searchable()->copyable(),
            Tables\Columns\TextColumn::make('title')->label('Masalah')->searchable()->limit(55)->wrap(),
            Tables\Columns\TextColumn::make('category.name')->label('Kategori'),
            Tables\Columns\TextColumn::make('location')->label('Lokasi')->limit(40)->toggleable(),
            Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            Tables\Columns\TextColumn::make('priority')->label('Prioritas')->badge()
                ->color(fn (string $state): string => match ($state) {
                    'critical' => 'danger', 'high' => 'warning', default => 'gray',
                }),
            Tables\Columns\TextColumn::make('reporter.name')->label('Pelapor')->toggleable(),
            Tables\Columns\TextColumn::make('management.name')->label('Pengelola')->placeholder('-')->toggleable(),
            Tables\Columns\TextColumn::make('assignee.name')->label('Petugas')->placeholder('-'),
            Tables\Columns\TextColumn::make('maintenance.response_due_at')->label('Respons SLA')
                ->dateTime('d M Y H:i')->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('maintenance.resolution_due_at')->label('Selesai SLA')
                ->dateTime('d M Y H:i')->toggleable(isToggledHiddenByDefault: true),
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
                Infolists\Components\TextEntry::make('location')->label('Lokasi')->placeholder('-'),
                Infolists\Components\TextEntry::make('reported_urgency')->label('Urgensi pelapor')->badge()->placeholder('-'),
                Infolists\Components\TextEntry::make('reporter.name')->label('Pelapor'),
                Infolists\Components\TextEntry::make('unit.name')->label('Unit')->placeholder('-'),
                Infolists\Components\TextEntry::make('management.name')->label('Pengelola')->placeholder('-'),
                Infolists\Components\TextEntry::make('assignee.name')->label('Petugas')->placeholder('-'),
                Infolists\Components\TextEntry::make('activity.name')->label('Pengajuan terkait')->placeholder('-'),
                Infolists\Components\TextEntry::make('created_at')->dateTime('d M Y H:i')->label('Dibuat'),
            ])->columns(2),
            Infolists\Components\Section::make('Penanganan tiket')
                ->description('Lihat petugas penanggung jawab dan tahap pekerjaan saat ini. Hanya penugasan yang disetujui yang dapat dilanjutkan.')
                ->schema([
                    Infolists\Components\TextEntry::make('assignee.name')->label('Ditugaskan kepada')->placeholder('Belum ada petugas'),
                    Infolists\Components\TextEntry::make('status')->label('Tahap saat ini')->badge(),
                    Infolists\Components\TextEntry::make('maintenance.approver.name')->label('Disetujui oleh')->placeholder('Menunggu persetujuan'),
                    Infolists\Components\TextEntry::make('maintenance.accepted_at')->label('Diterima petugas')->dateTime('d M Y H:i')->placeholder('Belum diterima'),
                ])->columns(2),
            Infolists\Components\Section::make('Perawatan / Perbaikan V3')
                ->visible(fn ($record): bool => $record->maintenance !== null)
                ->schema([
                    Infolists\Components\TextEntry::make('maintenance.approver.name')->label('Disetujui oleh')->placeholder('Belum'),
                    Infolists\Components\TextEntry::make('maintenance.approved_at')->label('Waktu persetujuan')->dateTime()->placeholder('-'),
                    Infolists\Components\TextEntry::make('maintenance.technician.name')->label('Diterima teknisi')->placeholder('Belum'),
                    Infolists\Components\TextEntry::make('maintenance.accepted_at')->label('Diterima pada')->dateTime()->placeholder('-'),
                    Infolists\Components\TextEntry::make('maintenance.response_due_at')->label('Deadline respons')->dateTime(),
                    Infolists\Components\TextEntry::make('maintenance.resolution_due_at')->label('Deadline perbaikan')->dateTime(),
                    Infolists\Components\TextEntry::make('maintenance.diagnosis')->label('Analisis masalah')->placeholder('-')->columnSpanFull(),
                    Infolists\Components\TextEntry::make('maintenance.repair_actions')->label('Tindakan perbaikan')->placeholder('-')->columnSpanFull(),
                    Infolists\Components\TextEntry::make('maintenance.outcome')->label('Hasil pekerjaan')->badge()->placeholder('Belum'),
                    Infolists\Components\TextEntry::make('maintenance.work_finished_at')->label('Pekerjaan selesai pada')->dateTime()->placeholder('-'),
                    Infolists\Components\RepeatableEntry::make('maintenance.materials')->label('Material / Sparepart')->schema([
                        Infolists\Components\TextEntry::make('name')->label('Material'),
                        Infolists\Components\TextEntry::make('quantity')->label('Jumlah'),
                        Infolists\Components\TextEntry::make('unit')->label('Satuan'),
                    ])->columns(3)->columnSpanFull(),
                    Infolists\Components\TextEntry::make('maintenance.verifier.name')->label('Diverifikasi oleh')->placeholder('-'),
                    Infolists\Components\TextEntry::make('maintenance.verified_at')->label('Verifikasi pada')->dateTime()->placeholder('-'),
                    Infolists\Components\TextEntry::make('maintenance.finalApprover.name')->label('Disahkan oleh')->placeholder('-'),
                    Infolists\Components\TextEntry::make('maintenance.final_approved_at')->label('Pengesahan akhir')->dateTime()->placeholder('-'),
                    Infolists\Components\TextEntry::make('maintenance.asset_released_at')->label('Dinyatakan layak pakai')->dateTime()->placeholder('Belum'),
                ])->columns(2)->collapsible(),
            Infolists\Components\Section::make('Aset terkait')->schema([
                Infolists\Components\RepeatableEntry::make('assets')->hiddenLabel()->schema([
                    Infolists\Components\TextEntry::make('asset_type')->label('Jenis'),
                    Infolists\Components\TextEntry::make('asset_name')->label('Nama aset'),
                ])->columns(2),
            ])->collapsible(),
            Infolists\Components\Section::make('Percakapan')->schema([
                Infolists\Components\RepeatableEntry::make('visibleComments')
                    ->label('Komentar')->schema([
                        Infolists\Components\TextEntry::make('author.name')->label('Penulis'),
                        Infolists\Components\TextEntry::make('created_at')->dateTime('d M Y H:i')->label('Waktu'),
                        Infolists\Components\TextEntry::make('body')->label('Isi')->columnSpanFull(),
                        Infolists\Components\IconEntry::make('is_internal')->label('Internal')->boolean(),
                        // Native Filament entries render each upload beside the comment
                        // to which it belongs. No public storage URL or custom JS.
                        Infolists\Components\RepeatableEntry::make('attachments')
                            ->label('Foto / dokumen komentar')
                            ->schema([
                                Infolists\Components\ImageEntry::make('image_url')
                                    ->label('Foto')->height(120)->square()
                                    ->url(fn (\App\Models\TicketAttachment $record): string => $record->preview_url)
                                    ->openUrlInNewTab()
                                    ->visible(fn (Infolists\Components\ImageEntry $component): bool => filled($component->getState())),
                                Infolists\Components\TextEntry::make('original_name')
                                    ->label('Buka lampiran')
                                    ->url(fn (\App\Models\TicketAttachment $record): string => $record->preview_url)
                                    ->openUrlInNewTab(),
                                Infolists\Components\TextEntry::make('download_url')
                                    ->label('Unduh')
                                    ->formatStateUsing(fn (): string => 'Unduh file')
                                    ->url(fn (\App\Models\TicketAttachment $record): string => $record->download_url)
                                    ->openUrlInNewTab(),
                            ])->columns(3)->columnSpanFull(),
                    ])->columns(2),
            ])->collapsible(),
            Infolists\Components\Section::make('Semua Lampiran')
                ->description('Lampiran awal dan lampiran komentar. Foto/PDF dibuka privat; gunakan tautan unduh bila ingin menyimpan file.')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('visibleAttachments')->hiddenLabel()->schema([
                        Infolists\Components\TextEntry::make('original_name')->label('Buka file')
                            ->url(fn (\App\Models\TicketAttachment $record): string => $record->preview_url)
                            ->openUrlInNewTab(),
                        Infolists\Components\TextEntry::make('download_url')->label('Unduh')
                            ->formatStateUsing(fn (): string => 'Unduh file')
                            ->url(fn (\App\Models\TicketAttachment $record): string => $record->download_url)
                            ->openUrlInNewTab(),
                        Infolists\Components\TextEntry::make('created_at')->dateTime('d M Y H:i')->label('Diunggah'),
                    ])->columns(3),
                ])->collapsed(),
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
