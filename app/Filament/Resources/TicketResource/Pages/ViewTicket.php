<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use App\Models\TicketTeamMember;
use App\Services\TicketService;
use App\Services\TicketVisibility;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    public function getRecord(): \Illuminate\Database\Eloquent\Model
    {
        $record = parent::getRecord();

        abort_unless(app(TicketVisibility::class)->canView(auth()->user(), $record), 404);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('comment')
                ->label('Tambah Komentar')->icon('heroicon-o-chat-bubble-left-right')
                ->form([
                    Forms\Components\Textarea::make('body')->label('Komentar')->required()->minLength(2)->maxLength(10000),
                    Forms\Components\FileUpload::make('files')->label('Foto/PDF (opsional)')
                        ->multiple()->maxFiles(3)->maxSize(5120)
                        ->acceptedFileTypes(['image/jpeg','image/png','application/pdf'])
                        ->disk('local')->directory('tickets/attachments')->visibility('private'),
                    Forms\Components\Toggle::make('internal')->label('Catatan khusus pengelola')
                        ->visible(fn (): bool => $this->getRecord()->maintenance === null
                    && app(TicketVisibility::class)->canManage(auth()->user(), $this->getRecord())),
                ])
                ->action(function (array $data): void {
                    $comment = app(TicketService::class)->comment(
                        $this->getRecord(), auth()->user(), $data['body'], (bool) ($data['internal'] ?? false),
                    );
                    if (filled($data['files'] ?? [])) {
                        app(TicketService::class)->attach($this->getRecord(), auth()->user(), array_values($data['files']), $comment);
                    }
                    Notification::make()->title('Komentar ditambahkan')->success()->send();
                }),
            Actions\Action::make('linkAsset')
                ->label('Hubungkan Inventaris')->icon('heroicon-o-link')
                ->visible(fn (): bool => app(TicketVisibility::class)->canManage(auth()->user(), $this->getRecord())
                    && ! in_array($this->getRecord()->status, ['closed', 'cancelled'], true)
                    && ! $this->getRecord()->assets()->exists())
                ->form(TicketResource::assetSearchFields())
                ->action(function (array $data): void {
                    app(TicketService::class)->linkAsset(
                        $this->getRecord(), auth()->user(), $data['asset_type'], $data['asset_id'],
                    );
                    Notification::make()->title('Inventaris berhasil dihubungkan')->success()->send();
                }),
            Actions\Action::make('approveMaintenance')
                ->label('Setujui & Tetapkan PIC')->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn (): bool => $this->getRecord()->maintenance !== null
                    && $this->getRecord()->status === 'open'
                    && (auth()->user()?->isFacility() ?? false))
                ->form([
                    Forms\Components\Select::make('technician')->label('Koordinator / teknisi penanggung jawab')
                        ->helperText('Bisa pilih koordinator terlebih dahulu. Setelah disetujui, koordinator dapat mendelegasikan kepada petugas lapangan.')
                        ->options(fn (): array => $this->maintenanceTechnicians())->searchable()->required(),
                    Forms\Components\Select::make('priority')->label('Prioritas SLA')->required()
                        ->options(['low'=>'Rendah','normal'=>'Normal','high'=>'Tinggi','critical'=>'Kritis'])
                        ->default(fn (): string => $this->getRecord()->priority),
                    Forms\Components\Textarea::make('notes')->label('Catatan persetujuan')->maxLength(2000),
                ])
                ->action(function (array $data): void {
                    app(\App\Services\TicketMaintenanceService::class)->approve(
                        $this->getRecord(), auth()->user(), (int) $data['technician'],
                        $data['priority'], $data['notes'] ?? null,
                    );
                    Notification::make()->title('Pekerjaan disetujui dan ditugaskan')->success()->send();
                }),
            Actions\Action::make('delegateMaintenance')
                ->label('Delegasikan ke Tim')->icon('heroicon-o-user-group')->color('info')
                ->visible(fn (): bool => app(\App\Services\TicketMaintenanceService::class)
                    ->canDelegate($this->getRecord(), auth()->user()))
                ->modalHeading('Tugaskan petugas dalam hierarki')
                ->modalDescription('Tiket sudah disetujui. Koordinator hanya dapat memilih anggota aktif di bawahnya; tugas yang sudah diterima tidak boleh dipindahkan tanpa proses koreksi.')
                ->form([
                    Forms\Components\Select::make('technician')->label('Anggota tim')
                        ->options(fn (): array => app(\App\Services\TicketMaintenanceService::class)
                            ->delegationOptions($this->getRecord(), auth()->user()))
                        ->searchable()->required(),
                    Forms\Components\Textarea::make('reason')->label('Instruksi / alasan delegasi')
                        ->required()->minLength(5)->maxLength(2000)
                        ->placeholder('Contoh: Periksa instalasi listrik di lokasi dan dokumentasikan hasil.'),
                ])
                ->action(function (array $data): void {
                    app(\App\Services\TicketMaintenanceService::class)->delegate(
                        $this->getRecord(), auth()->user(), (int) $data['technician'], (string) $data['reason'],
                    );
                    Notification::make()->title('Tugas berhasil didelegasikan')->success()->send();
                }),
            Actions\Action::make('acceptMaintenance')
                ->label('Terima & Mulai Kerja')->icon('heroicon-o-play')->color('primary')
                ->visible(fn (): bool => $this->getRecord()->maintenance !== null
                    && $this->getRecord()->status === 'triaged'
                    && $this->getRecord()->assigned_to === auth()->id())
                ->requiresConfirmation()
                ->action(fn () => app(\App\Services\TicketMaintenanceService::class)
                    ->accept($this->getRecord(), auth()->user())),
            Actions\Action::make('waitParts')
                ->label('Menunggu Material')->icon('heroicon-o-clock')
                ->visible(fn (): bool => $this->getRecord()->maintenance !== null
                    && $this->getRecord()->status === 'in_progress'
                    && $this->getRecord()->assigned_to === auth()->id())
                ->form([Forms\Components\Textarea::make('reason')->label('Alasan')->required()->minLength(3)])
                ->action(fn (array $data) => app(\App\Services\TicketMaintenanceService::class)
                    ->waiting($this->getRecord(), auth()->user(), true, $data['reason'])),
            Actions\Action::make('resumeWork')
                ->label('Lanjutkan Pekerjaan')->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => $this->getRecord()->maintenance !== null
                    && $this->getRecord()->status === 'waiting_parts'
                    && $this->getRecord()->assigned_to === auth()->id())
                ->form([Forms\Components\Textarea::make('reason')->label('Keterangan')->required()->minLength(3)])
                ->action(fn (array $data) => app(\App\Services\TicketMaintenanceService::class)
                    ->waiting($this->getRecord(), auth()->user(), false, $data['reason'])),
            Actions\Action::make('finishMaintenance')
                ->label('Catat Hasil Perbaikan')->icon('heroicon-o-wrench-screwdriver')
                ->visible(fn (): bool => $this->getRecord()->maintenance !== null
                    && $this->getRecord()->status === 'in_progress'
                    && $this->getRecord()->assigned_to === auth()->id())
                ->form([
                    Forms\Components\Textarea::make('diagnosis')->label('Analisis masalah')
                        ->required()->minLength(5),
                    Forms\Components\Textarea::make('actions')->label('Tindakan perbaikan')
                        ->required()->minLength(5),
                    Forms\Components\Repeater::make('materials')->label('Material / Sparepart (opsional)')
                        ->schema([
                            Forms\Components\TextInput::make('name')->label('Nama material')->required()->maxLength(200),
                            Forms\Components\TextInput::make('quantity')->label('Jumlah')->numeric()->gt(0)->required(),
                            Forms\Components\TextInput::make('unit')->label('Satuan')->default('pcs')->required()->maxLength(25),
                        ])->columns(3)->maxItems(30)->default([])->addActionLabel('Tambah Material'),
                    Forms\Components\Select::make('outcome')->label('Hasil pekerjaan')->required()
                        ->options([
                            'complete' => 'Selesai Sempurna',
                            'temporary' => 'Selesai Sementara',
                            'unrepairable' => 'Tidak Dapat Diperbaiki',
                        ]),
                ])
                ->action(function (array $data): void {
                    app(\App\Services\TicketMaintenanceService::class)->finish(
                        $this->getRecord(), auth()->user(), $data['diagnosis'],
                        $data['actions'], $data['materials'] ?? [], $data['outcome'],
                    );
                    Notification::make()->title('Hasil pekerjaan menunggu verifikasi pelapor')->success()->send();
                }),
            Actions\Action::make('verifyMaintenance')
                ->label('Verifikasi Hasil')->icon('heroicon-o-clipboard-document-check')->color('success')
                ->visible(fn (): bool => $this->getRecord()->maintenance !== null
                    && $this->getRecord()->status === 'resolved'
                    && ! $this->getRecord()->maintenance->verified_at
                    && $this->getRecord()->reporter_id === auth()->id())
                ->form([
                    Forms\Components\Select::make('accepted')->label('Hasil pemeriksaan')->required()
                        ->options(['yes'=>'Sudah Baik', 'no'=>'Masih Bermasalah']),
                    Forms\Components\Textarea::make('notes')->label('Catatan / alasan jika menolak'),
                ])
                ->action(fn (array $data) => app(\App\Services\TicketMaintenanceService::class)
                    ->verify($this->getRecord(), auth()->user(),
                        $data['accepted'] === 'yes', $data['notes'] ?? null)),
            Actions\Action::make('closeMaintenance')
                ->label('Sahkan Penyelesaian')->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn (): bool => $this->getRecord()->maintenance !== null
                    && $this->getRecord()->status === 'resolved'
                    && $this->getRecord()->maintenance->verified_at !== null
                    && (auth()->user()?->isFacility() ?? false))
                ->form([
                    Forms\Components\Toggle::make('safe')
                        ->label('Sudah dinyatakan layak pakai setelah pemeriksaan fisik')
                        ->helperText('Hanya untuk hasil perbaikan sempurna. Jika tidak dipilih, aset tetap ditahan.')
                        ->default(false),
                    Forms\Components\Textarea::make('notes')->label('Catatan pengesahan'),
                ])
                ->action(fn (array $data) => app(\App\Services\TicketMaintenanceService::class)
                    ->close($this->getRecord(), auth()->user(), (bool) $data['safe'], $data['notes'] ?? null)),
            Actions\Action::make('cancelMaintenance')
                ->label('Batalkan Pengajuan')->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn (): bool => $this->getRecord()->maintenance !== null
                    && in_array($this->getRecord()->status, ['open','triaged'], true)
                    && (auth()->user()?->isFacility() ?? false))
                ->form([Forms\Components\Textarea::make('notes')->label('Alasan pembatalan')->required()->minLength(5)])
                ->action(fn (array $data) => app(\App\Services\TicketMaintenanceService::class)
                    ->cancel($this->getRecord(), auth()->user(), $data['notes'])),
            Actions\Action::make('maintenancePdf')
                ->label('Form Perawatan PDF')->icon('heroicon-o-document-arrow-down')
                ->visible(fn (): bool => $this->getRecord()->maintenance !== null)
                ->url(fn (): string => route('tickets.maintenance.pdf', ['ticket' => $this->getRecord()->id]))
                ->openUrlInNewTab(),
            Actions\Action::make('assign')
                ->label('Tugaskan')->icon('heroicon-o-user-plus')
                ->visible(fn (): bool => $this->getRecord()->maintenance === null
                    && app(TicketVisibility::class)->canManage(auth()->user(), $this->getRecord()))
                ->form([
                    Forms\Components\Select::make('user_id')->label('Petugas')
                        ->options(fn (): array => $this->assigneeOptions())->searchable()->nullable(),
                    Forms\Components\Select::make('priority')->label('Prioritas')
                        ->options(['low'=>'Rendah','normal'=>'Normal','high'=>'Tinggi','critical'=>'Kritis'])
                        ->visible(fn (): bool => auth()->user()->isFacility()),
                ])
                ->action(function (array $data): void {
                    app(TicketService::class)->assign(
                        $this->getRecord(), auth()->user(), $data['user_id'] ?? null, $data['priority'] ?? null,
                    );
                    Notification::make()->title('Penugasan diperbarui')->success()->send();
                }),
            Actions\Action::make('status')
                ->label('Proses Status')->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => $this->getRecord()->maintenance === null)
                ->form([
                    Forms\Components\Select::make('to')->label('Status baru')->required()
                        ->options(fn (): array => $this->availableTransitions()),
                    Forms\Components\Textarea::make('notes')->label('Catatan perubahan')->maxLength(2000),
                ])
                ->action(function (array $data): void {
                    app(TicketService::class)->transition($this->getRecord(), auth()->user(), $data['to'], $data['notes'] ?? null);
                    Notification::make()->title('Status diperbarui')->success()->send();
                }),
        ];
    }

    private function maintenanceTechnicians(): array
    {
        $ticket = $this->getRecord();
        $management = $ticket->management?->users()->pluck('users.id') ?? collect();
        $facilities = \App\Models\User::role(config('role.fasilitas'))->pluck('id')
            ->merge(\App\Models\User::role(config('role.admin'))->pluck('id'))
            ->merge(\App\Models\User::role('super_admin')->pluck('id'));
        $members = TicketTeamMember::query()->where('is_active', true)->pluck('user_id');
        $ids = $management->merge($facilities)->merge($members)->unique()
            ->reject(fn ($id) => (int) $id === (int) $ticket->reporter_id);
        return \App\Models\User::query()->whereKey($ids)->where('is_enabled', true)->orderBy('name')
            ->get()->filter(fn ($user): bool => TicketTeamMember::qualifiesFor($ticket, $user))
            ->mapWithKeys(function ($user): array {
                $member = $user->ticketTeamMember;
                $suffix = $member
                    ? ' · '.($member->position === 'lead' ? 'Koordinator' : 'Teknisi')
                        .' / '.($member->team_type === 'field' ? 'Lapangan' : 'Pendukung')
                    : ' · Pengelola';
                return [$user->id => $user->name.$suffix];
            })->all();
    }

    private function assigneeOptions(): array
    {
        $record = $this->getRecord();
        $ids = $record->management?->users()->pluck('users.id') ?? collect();
        return \App\Models\User::query()->whereKey($ids)->orderBy('name')->pluck('name','id')->all();
    }

    private function availableTransitions(): array
    {
        return app(TicketService::class)->availableTransitions($this->getRecord(), auth()->user());
    }
}
