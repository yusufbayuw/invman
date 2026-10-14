<?php

namespace App\Filament\Pages;

use App\Filament\Resources\TicketResource;
use App\Filament\Support\TicketWorkForms;
use App\Filament\Widgets\TechnicianTaskStats;
use App\Models\Ticket;
use App\Models\TicketTeamMember;
use App\Services\TicketMaintenanceService;
use App\Services\TicketService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TugasTeknisi extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationGroup = 'Layanan & Ticketing';
    protected static ?string $navigationLabel = 'Tugas Lapangan';
    protected static ?string $title = 'Tugas Teknisi Lapangan';
    protected static ?int $navigationSort = 0;
    protected static string $view = 'filament.pages.tugas-teknisi';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->is_enabled
            && ($user->isFacility() || $user->isAssetManager()
                || $user->ticketTeamMember()->where('is_active', true)->exists()));
    }

    public function getSubheading(): ?string
    {
        return 'Pekerjaan yang ditugaskan kepada Anda. Gunakan tindakan pada tiap baris untuk menerima, mencatat foto, menunggu material, atau mengirim hasil.';
    }

    protected function getHeaderWidgets(): array
    {
        return [TechnicianTaskStats::class];
    }

    /** Scope is enforced in SQL, never derived from a user-editable filter. */
    public static function taskQuery(): Builder
    {
        return Ticket::query()
            ->where('assigned_to', auth()->id())
            ->whereHas('maintenance');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(static::taskQuery()->with(['maintenance', 'category', 'unit']))
            ->heading('Antrean pekerjaan')
            ->description('Tugas hanya dapat diproses oleh PIC yang ditunjuk. Klik Detail Tiket untuk percakapan dan bukti lengkap.')
            ->poll('30s')
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('Tiket')
                    ->copyable()->searchable()
                    ->description(fn (Ticket $record): string => $record->category?->name ?? '-'),
                Tables\Columns\TextColumn::make('title')->label('Masalah')
                    ->searchable()->wrap()->limit(80)
                    ->description(fn (Ticket $record): string => $record->location ?: 'Lokasi tidak dicantumkan'),
                Tables\Columns\TextColumn::make('priority')->label('Prioritas')
                    ->badge()->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger', 'high' => 'warning', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')->label('Tahap')
                    ->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                        'triaged' => 'Perlu diterima',
                        'in_progress' => 'Dikerjakan',
                        'waiting_parts' => 'Menunggu material',
                        'resolved' => 'Menunggu verifikasi',
                        'closed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })->color(fn (string $state): string => match ($state) {
                        'triaged' => 'warning', 'in_progress' => 'info',
                        'waiting_parts' => 'warning', 'resolved' => 'success',
                        'closed' => 'gray', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('maintenance.resolution_due_at')
                    ->label('Batas SLA')->dateTime('d M Y H:i')
                    ->description(fn (Ticket $record): ?string =>
                        $record->maintenance?->resolution_due_at?->isPast()
                            && ! in_array($record->status, ['closed', 'cancelled', 'resolved'], true)
                            ? 'Melewati batas waktu' : null)
                    ->color(fn (Ticket $record): string =>
                        $record->maintenance?->resolution_due_at?->isPast()
                            && ! in_array($record->status, ['closed', 'cancelled', 'resolved'], true)
                            ? 'danger' : 'gray')
                    ->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('unit.name')->label('Unit')
                    ->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Tahap pekerjaan')
                    ->options([
                        'triaged' => 'Perlu diterima',
                        'in_progress' => 'Sedang dikerjakan',
                        'waiting_parts' => 'Menunggu material',
                        'resolved' => 'Menunggu verifikasi',
                        'closed' => 'Selesai',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('accept')
                    ->label('Terima & Mulai')->icon('heroicon-o-play')
                    ->color('primary')->button()->size('sm')
                    ->visible(fn (Ticket $record): bool => static::mayAccept($record))
                    ->requiresConfirmation()
                    ->modalDescription('Anda menjadi petugas yang bertanggung jawab sampai hasil pekerjaan diserahkan.')
                    ->action(function (Ticket $record): void {
                        app(TicketMaintenanceService::class)->accept($record, auth()->user());
                        Notification::make()->title('Pekerjaan dimulai')->success()->send();
                    }),
                Tables\Actions\Action::make('evidence')
                    ->label('Bukti Foto Kerja')->icon('heroicon-o-camera')
                    ->color('gray')
                    ->visible(fn (Ticket $record): bool => static::mayWork($record))
                    ->form([
                        Forms\Components\Select::make('stage')
                            ->label('Tahap dokumentasi')->native(false)
                            ->options([
                                'before' => 'Sebelum perbaikan',
                                'after' => 'Sesudah perbaikan',
                            ])->required(),
                        Forms\Components\FileUpload::make('files')
                            ->label('Foto lokasi / hasil pekerjaan')
                            ->image()->multiple()->maxFiles(3)->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png'])
                            ->disk('local')->directory('tickets/attachments')
                            ->visibility('private')->required()
                            ->helperText('JPG/PNG maksimal 5 MB per file, maksimal 3 foto. Foto terlihat dalam Percakapan tiket.'),
                        Forms\Components\Textarea::make('notes')->label('Keterangan singkat')
                            ->rows(2)->maxLength(1500)
                            ->placeholder('Kondisi kerusakan atau hasil pemeriksaan (opsional)'),
                    ])
                    ->action(function (Ticket $record, array $data): void {
                        // The saved FileUpload state is not trusted. Recheck on
                        // the server to prevent a stale/forged Livewire action.
                        if (! static::mayWork($record->fresh())) {
                            throw ValidationException::withMessages(['files' => 'Tugas tidak dapat diubah.']);
                        }
                        $stage = ($data['stage'] ?? null) === 'before' ? 'before'
                            : (($data['stage'] ?? null) === 'after' ? 'after' : null);
                        $files = array_values((array) ($data['files'] ?? []));
                        if ($stage === null || $files === [] || count($files) > 3) {
                            throw ValidationException::withMessages(['files' => 'Pilih tahap dan unggah 1–3 foto.']);
                        }
                        $caption = $stage === 'before' ? 'Sebelum perbaikan' : 'Sesudah perbaikan';
                        $note = trim((string) ($data['notes'] ?? ''));
                        $body = $caption.($note !== '' ? ': '.$note : ' — dokumentasi lapangan');
                        DB::transaction(function () use ($record, $body, $files): void {
                            $comment = app(TicketService::class)->comment($record, auth()->user(), $body);
                            app(TicketService::class)->attach($record, auth()->user(), $files, $comment);
                        }, 3);
                        Notification::make()->title('Foto pekerjaan tersimpan pada percakapan tiket')->success()->send();
                    }),
                Tables\Actions\Action::make('wait')
                    ->label('Menunggu Material')->icon('heroicon-o-clock')
                    ->color('warning')
                    ->visible(fn (Ticket $record): bool => static::mayWork($record) && $record->status === 'in_progress')
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('Material yang ditunggu / alasan')
                            ->required()->minLength(3)->maxLength(2000),
                    ])
                    ->action(function (Ticket $record, array $data): void {
                        app(TicketMaintenanceService::class)->waiting($record, auth()->user(), true, $data['reason']);
                        Notification::make()->title('Status menunggu material')->success()->send();
                    }),
                Tables\Actions\Action::make('resume')
                    ->label('Lanjutkan')->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->visible(fn (Ticket $record): bool => static::mayWork($record) && $record->status === 'waiting_parts')
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('Alasan pekerjaan dilanjutkan')
                            ->required()->minLength(3)->maxLength(2000),
                    ])
                    ->action(function (Ticket $record, array $data): void {
                        app(TicketMaintenanceService::class)->waiting($record, auth()->user(), false, $data['reason']);
                        Notification::make()->title('Pekerjaan dilanjutkan')->success()->send();
                    }),
                Tables\Actions\Action::make('finish')
                    ->label('Catat Hasil')->icon('heroicon-o-clipboard-document-check')
                    ->color('success')->button()->size('sm')
                    ->visible(fn (Ticket $record): bool => static::mayWork($record) && $record->status === 'in_progress')
                    ->form(TicketWorkForms::finish())
                    ->action(function (Ticket $record, array $data): void {
                        app(TicketMaintenanceService::class)->finish(
                            $record, auth()->user(), $data['diagnosis'], $data['actions'],
                            $data['materials'] ?? [], $data['outcome'],
                        );
                        Notification::make()->title('Hasil pekerjaan dikirim untuk verifikasi pelapor')->success()->send();
                    }),
                Tables\Actions\Action::make('detail')
                    ->label('Detail Tiket')->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Ticket $record): string => TicketResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (Ticket $record): string => TicketResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Belum ada tugas yang ditugaskan')
            ->emptyStateDescription('Tiket yang disetujui dan ditugaskan kepada Anda akan muncul di sini.');
    }

    public static function mayAccept(Ticket $record): bool
    {
        return (int) $record->assigned_to === (int) auth()->id()
            && $record->status === 'triaged'
            && $record->maintenance?->approved_at !== null
            && $record->maintenance?->accepted_at === null;
    }

    public static function mayWork(Ticket $record): bool
    {
        return (int) $record->assigned_to === (int) auth()->id()
            && in_array($record->status, ['in_progress', 'waiting_parts'], true)
            && $record->maintenance?->approved_at !== null
            && $record->maintenance?->accepted_at !== null;
    }
}
