<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\TugasTeknisi;
use App\Filament\Resources\TicketResource;
use App\Models\G001M001Unit;
use App\Models\WorkOrderApproval;
use App\Services\WorkOrderWorkflowService;
use App\Models\Ticket;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TicketAttentionWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return TicketResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $technicianOnly = $user && ! $user->isFacility() && ! $user->isSarpras() && ! $user->isAssetManager()
            && $user->ticketTeamMember()->where('is_active', true)->exists();
        $hasApprovals = $user && app(WorkOrderWorkflowService::class)
            ->scopeEligibleCandidates(WorkOrderApproval::query(), $user)->exists();
        // Reuse the resource's visibility boundary; never expand scope for a dashboard.
        $query = TicketResource::getEloquentQuery()
            ->whereNotIn('status', ['closed', 'cancelled'])
            ->when($technicianOnly, fn (Builder $query) => $query->where(fn (Builder $work) => $work
                ->where('assigned_to', $user->id)
                ->orWhereHas('workOrderApprovals', fn (Builder $approvals) => app(WorkOrderWorkflowService::class)
                    ->scopeEligibleCandidates($approvals, $user))))
            ->with(['maintenance', 'assignee']);

        return $table
            ->heading($technicianOnly && ! $hasApprovals ? 'Pekerjaan Saya' : 'Tiket yang Perlu Perhatian')
            ->description($technicianOnly
                ? 'Buka tugas untuk menerima pekerjaan, menambahkan dokumentasi, atau mengirim hasil.'
                : 'Laporan aktif dalam kewenangan Anda.'.($user?->isFacility() ? ' Filter unit hanya berlaku pada daftar tiket ini.' : ' Buka tiket untuk melihat tindak lanjut yang tersedia.'))
            ->query($query)
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('number')->label('Tiket')->searchable()
                        ->description(fn (Ticket $record): string => $record->title)->wrap(),
                    Tables\Columns\TextColumn::make('status')->label('Tahap')->badge()
                        ->wrap()
                        ->color(fn (Ticket $record): string => match ($record->status) {
                            'open', 'reopened' => 'danger',
                            'triaged', 'waiting_parts', 'waiting_requester', 'resolved' => 'warning',
                            default => 'info',
                        })
                        ->formatStateUsing(fn (Ticket $record): string => match ($record->status) {
                            'open' => 'Perlu ditinjau', 'triaged' => 'Ditugaskan',
                            'in_progress' => 'Dikerjakan', 'waiting_parts' => 'Menunggu material',
                            'waiting_requester' => 'Menunggu pelapor', 'resolved' => 'Menunggu verifikasi',
                            'reopened' => 'Dibuka kembali', default => ucfirst($record->status),
                        }),
                    Tables\Columns\TextColumn::make('assignee.name')->label('Petugas')->placeholder('Belum ditugaskan')
                        ->hidden($technicianOnly),
                    Tables\Columns\TextColumn::make('maintenance.resolution_due_at')->label('Batas penyelesaian')
                        ->dateTime('d M Y H:i')->placeholder('—')
                        ->color(fn (Ticket $record): string => $record->maintenance?->resolution_due_at?->isPast()
                            && $record->status !== 'resolved' ? 'danger' : 'gray')
                        ->description(fn (Ticket $record): ?string => $record->maintenance?->resolution_due_at?->isPast()
                            && $record->status !== 'resolved' ? 'Terlambat' : null),
                ])->from('xl'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('unit')
                    ->label('Unit pelapor')
                    ->options(fn (): array => auth()->user()?->isFacility()
                        ? G001M001Unit::query()->orderBy('name')->pluck('name', 'id')->all() : [])
                    ->searchable()->preload()
                    ->visible(fn (): bool => auth()->user()?->isFacility() ?? false)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(auth()->user()?->isFacility() && filled($data['value'] ?? null),
                            fn (Builder $query): Builder => $query->where('g001_m001_unit_id', $data['value']))),
            ])
            ->actions([
                Tables\Actions\Action::make('work')->label('Kerjakan')->icon('heroicon-o-wrench-screwdriver')
                    ->visible(fn (Ticket $record): bool => $this->canWork($record))
                    ->url(fn (Ticket $record): string => TicketResource::getUrl('view', ['record' => $record])),
                Tables\Actions\ViewAction::make()->label('Buka Tiket')
                    ->visible(fn (Ticket $record): bool => ! $this->canWork($record))
                    ->url(fn (Ticket $record): string => TicketResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading($technicianOnly ? 'Tidak ada tugas aktif' : 'Tidak ada tiket aktif')
            ->emptyStateDescription($technicianOnly ? 'Tugas yang diberikan kepada Anda akan muncul di sini.' : 'Laporan selesai tetap tersedia di menu Laporan Kerusakan.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10])->defaultPaginationPageOption(5)->poll('30s');
    }

    private function canWork(Ticket $record): bool
    {
        return TicketResource::canView($record) && TugasTeknisi::canAccess()
            && (TugasTeknisi::mayAccept($record) || TugasTeknisi::mayWork($record));
    }
}
