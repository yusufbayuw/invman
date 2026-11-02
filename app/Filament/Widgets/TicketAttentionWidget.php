<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\TugasTeknisi;
use App\Filament\Resources\TicketResource;
use App\Models\Ticket;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

class TicketAttentionWidget extends TableWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return TicketResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $technicianOnly = $user && ! $user->isFacility() && ! $user->isSarpras() && ! $user->isAssetManager();
        // Reuse the resource's visibility boundary; never expand scope for a dashboard.
        $query = TicketResource::getEloquentQuery()
            ->whereNotIn('status', ['closed', 'cancelled'])
            ->when($technicianOnly, fn ($query) => $query->where('assigned_to', $user->id))
            ->when($user?->isFacility() && filled($this->filters['unit_id'] ?? null),
                fn ($query) => $query->where('g001_m001_unit_id', $this->filters['unit_id']))
            ->with(['maintenance', 'assignee']);

        return $table
            ->heading($technicianOnly ? 'Pekerjaan Saya' : 'Tiket yang Perlu Perhatian')
            ->description($technicianOnly
                ? 'Buka tugas untuk menerima pekerjaan, menambahkan dokumentasi, atau mengirim hasil.'
                : 'Laporan aktif dalam kewenangan Anda. Buka tiket untuk melihat tindak lanjut yang tersedia.')
            ->query($query)
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('number')->label('Tiket')->searchable()
                        ->description(fn (Ticket $record): string => $record->title)->wrap(),
                    Tables\Columns\TextColumn::make('status')->label('Tahap')->badge()
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
                ])->from('md'),
            ])
            ->actions([
                Tables\Actions\Action::make('work')->label('Kerjakan')->icon('heroicon-o-wrench-screwdriver')
                    ->visible(fn (Ticket $record): bool => TugasTeknisi::canAccess()
                        && (int) $record->assigned_to === (int) auth()->id() && $record->maintenance !== null)
                    ->url(TugasTeknisi::getUrl()),
                Tables\Actions\ViewAction::make()->label('Buka Tiket')
                    ->url(fn (Ticket $record): string => TicketResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading($technicianOnly ? 'Tidak ada tugas aktif' : 'Tidak ada tiket aktif')
            ->emptyStateDescription($technicianOnly ? 'Tugas yang diberikan kepada Anda akan muncul di sini.' : 'Laporan selesai tetap tersedia di menu Laporan Kerusakan.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10])->defaultPaginationPageOption(5)->poll('30s');
    }
}
