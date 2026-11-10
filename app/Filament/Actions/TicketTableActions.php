<?php

namespace App\Filament\Actions;

use App\Filament\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\WorkOrderApproval;
use App\Services\TicketMaintenanceService;
use App\Services\TicketVisibility;
use App\Services\WorkOrderEstimateService;
use App\Services\WorkOrderWorkflowService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;

/**
 * Row-level entry points only; all state changes are delegated to the
 * existing authorization-aware workflow and maintenance services.
 */
final class TicketTableActions
{
    /** @return array<int, mixed> */
    public static function make(): array
    {
        return [
            Tables\Actions\ViewAction::make()->label('Detail'),
            Tables\Actions\Action::make('work_order_decision')
                ->label('Putuskan Persetujuan')
                ->icon('heroicon-o-document-check')
                ->color('warning')->button()->size('sm')
                ->visible(fn (Ticket $record): bool => static::canApprove($record))
                ->modalHeading(fn (Ticket $record): string => static::nextApproval($record)?->label ?? 'Persetujuan Work Order')
                ->fillForm(function (Ticket $record): array {
                    $step = static::nextApproval($record);
                    $estimate = app(WorkOrderEstimateService::class)->current($record);

                    return [
                        'approval_id' => $step?->id,
                        'estimate_id' => $estimate?->id,
                        'estimate_summary' => $estimate
                            ? 'Estimasi #'.$estimate->id.' · '.($estimate->vendor_name ?? 'Tim internal').' · Rp '.$estimate->amount
                            : 'Belum ada estimasi',
                    ];
                })
                ->form([
                    Forms\Components\Hidden::make('approval_id')->required(),
                    Forms\Components\Hidden::make('estimate_id'),
                    Forms\Components\Textarea::make('estimate_summary')
                        ->label('Estimasi yang diperiksa')->disabled()->dehydrated(false),
                    Forms\Components\Select::make('decision')->label('Keputusan')
                        ->options(['approve' => 'Setujui', 'reject' => 'Tolak'])->required(),
                    Forms\Components\Textarea::make('notes')
                        ->label('Catatan / alasan penolakan')->maxLength(2000),
                ])
                ->action(function (Ticket $record, array $data): void {
                    // Never accept another ticket's approval ID from a forged
                    // Livewire request; the workflow service locks and checks
                    // the decision's current step and live assignment.
                    $step = $record->workOrderApprovals()->findOrFail($data['approval_id']);
                    app(WorkOrderWorkflowService::class)->decide(
                        $step, auth()->user(), $data['decision'] === 'approve',
                        $data['notes'] ?? null,
                        filled($data['estimate_id'] ?? null) ? (int) $data['estimate_id'] : null,
                    );
                    Notification::make()->title('Keputusan Work Order tersimpan')->success()->send();
                }),
            Tables\Actions\Action::make('assign_maintenance')
                ->label('Tetapkan Petugas')
                ->icon('heroicon-o-user-plus')
                ->color('info')->button()->size('sm')
                ->visible(fn (Ticket $record): bool => $record->maintenance !== null
                    && $record->status === 'open'
                    && app(WorkOrderWorkflowService::class)->canPerform($record, auth()->user(), 'assign')
                    && ! $record->workOrderApprovals()->where('checkpoint', 'start')->whereIn('status', ['pending', 'rejected'])->exists())
                ->url(fn (Ticket $record): string => TicketResource::getUrl('view', ['record' => $record])),
            Tables\Actions\Action::make('start_work')
                ->label('Terima & Mulai')
                ->icon('heroicon-o-play')
                ->color('primary')->button()->size('sm')
                ->visible(fn (Ticket $record): bool => \App\Filament\Pages\TugasTeknisi::mayAccept($record))
                ->requiresConfirmation()
                ->action(fn (Ticket $record) => app(TicketMaintenanceService::class)
                    ->accept($record, auth()->user())),
            Tables\Actions\Action::make('continue_work')
                ->label('Kerjakan')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('primary')->button()->size('sm')
                ->visible(fn (Ticket $record): bool => \App\Filament\Pages\TugasTeknisi::mayWork($record))
                ->url(fn (Ticket $record): string => TicketResource::getUrl('view', ['record' => $record])),
            Tables\Actions\Action::make('verify_maintenance')
                ->label('Verifikasi Hasil')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('success')->button()->size('sm')
                ->visible(fn (Ticket $record): bool => $record->maintenance !== null
                    && $record->status === 'resolved'
                    && $record->maintenance->verified_at === null
                    && (int) $record->reporter_id === (int) auth()->id())
                ->form([
                    Forms\Components\Select::make('accepted')->label('Hasil pemeriksaan')
                        ->options(['yes' => 'Sudah Baik', 'no' => 'Masih Bermasalah'])->required(),
                    Forms\Components\Textarea::make('notes')->label('Catatan / alasan jika menolak')->maxLength(2000),
                ])
                ->action(fn (Ticket $record, array $data) => app(TicketMaintenanceService::class)
                    ->verify($record, auth()->user(), $data['accepted'] === 'yes', $data['notes'] ?? null)),
            Tables\Actions\Action::make('close_maintenance')
                ->label('Sahkan Penyelesaian')
                ->icon('heroicon-o-check-circle')
                ->color('success')->button()->size('sm')
                ->visible(fn (Ticket $record): bool => $record->maintenance !== null
                    && $record->status === 'resolved'
                    && $record->maintenance->verified_at !== null
                    && app(WorkOrderWorkflowService::class)->canPerform($record, auth()->user(), 'close')
                    && ! $record->workOrderApprovals()->where('checkpoint', 'final')->whereIn('status', ['pending', 'rejected'])->exists())
                ->url(fn (Ticket $record): string => TicketResource::getUrl('view', ['record' => $record])),
            Tables\Actions\ActionGroup::make([
                Tables\Actions\Action::make('record_estimate')
                    ->label('Catat Estimasi')
                    ->icon('heroicon-o-calculator')
                    ->visible(fn (Ticket $record): bool => app(WorkOrderEstimateService::class)
                        ->canRecord($record, auth()->user()))
                    ->url(fn (Ticket $record): string => TicketResource::getUrl('view', ['record' => $record])),
                Tables\Actions\Action::make('delegate')
                    ->label('Delegasikan ke Tim')
                    ->icon('heroicon-o-user-group')
                    ->visible(fn (Ticket $record): bool => app(TicketMaintenanceService::class)
                        ->canDelegate($record, auth()->user()))
                    ->url(fn (Ticket $record): string => TicketResource::getUrl('view', ['record' => $record])),
                Tables\Actions\Action::make('detail_actions')
                    ->label('Tindakan Lanjutan / Riwayat')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Ticket $record): string => TicketResource::getUrl('view', ['record' => $record])),
            ])->label('Lainnya')->icon('heroicon-m-ellipsis-vertical')->color('gray'),
        ];
    }

    public static function nextApproval(Ticket $record): ?WorkOrderApproval
    {
        return $record->workOrderApprovals()->where('status', 'pending')
            ->orderBy('sequence')->first();
    }

    public static function canApprove(Ticket $record): bool
    {
        $user = auth()->user();
        if (! $user || ! app(TicketVisibility::class)->canView($user, $record)) {
            return false;
        }
        $step = static::nextApproval($record);

        return $step !== null && app(WorkOrderWorkflowService::class)->canDecide($step, $user);
    }
}
