<?php

namespace App\Filament\Actions;

use App\Enums\ReservationStatus;
use App\Filament\Pages\PeminjamanSerahTerima;
use App\Filament\Support\VehicleAssignmentForm;
use App\Models\G005M019VehicleReservation;
use App\Services\LoanHandoverQrService;
use App\Services\LoanRequestService;
use App\Services\VehicleAssignmentService;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Database\Eloquent\Model;

/**
 * Keep the three reservation list tables consistent with the existing
 * activity relation managers and the Peminjaman Dikelola transaction service.
 *
 * Visibility is only a UI hint: the service reauthorizes every write.
 */
final class ReservationTableActions
{
    /** @return array<int, Tables\Actions\Action|Tables\Actions\ActionGroup> */
    public static function make(string $type): array
    {
        return [
            Tables\Actions\ViewAction::make()->label('Detail'),
            Tables\Actions\Action::make('approve')
                ->label('Setujui')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->button()->size('sm')
                ->visible(fn (Model $record): bool => app(LoanRequestService::class)->canDecideReservation($record))
                ->form(fn (Model $record): array => $record instanceof G005M019VehicleReservation
                    ? VehicleAssignmentForm::fields($record) : [])
                ->action(fn (Model $record, array $data) => app(LoanRequestService::class)->processReservation(
                    $type, $record->getKey(), ReservationStatus::Approved, null, $data,
                )),
            Tables\Actions\Action::make('checkout')
                ->label('Serah Terima')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('info')
                ->button()->size('sm')
                ->visible(fn (Model $record): bool => app(LoanRequestService::class)->canCheckoutReservation($record))
                ->url(fn (Model $record): string => static::handoverUrl($type, $record)),
            Tables\Actions\Action::make('record_return')
                ->label('Catat Pengembalian')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('warning')
                ->button()->size('sm')
                ->visible(fn (Model $record): bool => app(LoanRequestService::class)->canRecordManagedReturn($record))
                ->url(fn (Model $record): string => static::handoverUrl($type, $record)),
            Tables\Actions\Action::make('request_return')
                ->label('Ajukan Pengembalian')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->button()->size('sm')
                ->visible(fn (Model $record): bool => app(LoanRequestService::class)->canRequestReservationReturn($record))
                ->url(fn (Model $record): string => static::handoverUrl($type, $record)),
            Tables\Actions\Action::make('confirm_return')
                ->label('Konfirmasi Pengembalian')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->button()->size('sm')
                ->visible(fn (Model $record): bool => app(LoanRequestService::class)->canConfirmReturn($record))
                ->requiresConfirmation()
                ->modalDescription('Konfirmasi pihak kedua menyelesaikan pengembalian. Pastikan kondisi dan bukti fisik telah diperiksa.')
                ->action(fn (Model $record) => app(LoanRequestService::class)->confirmReturn($type, $record->getKey())),
            Tables\Actions\ActionGroup::make([
                Tables\Actions\Action::make('reject')
                    ->label('Tolak Pengajuan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Model $record): bool => app(LoanRequestService::class)->canDecideReservation($record))
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Alasan penolakan')->required()->maxLength(2000),
                    ])
                    ->action(fn (Model $record, array $data) => app(LoanRequestService::class)->processReservation(
                        $type, $record->getKey(), ReservationStatus::Rejected, $data['rejection_reason'],
                    )),
                Tables\Actions\Action::make('change_vehicle_crew')
                    ->label('Ganti Penugasan Kendaraan')
                    ->icon('heroicon-o-user-group')
                    ->visible(fn (Model $record): bool => $record instanceof G005M019VehicleReservation
                        && (auth()->user()?->managesReservation($record) ?? false)
                        && in_array($record->status, [ReservationStatus::Approved->value, ReservationStatus::CheckedOut->value], true))
                    ->form(fn (G005M019VehicleReservation $record): array => VehicleAssignmentForm::fields($record, changing: true))
                    ->action(fn (G005M019VehicleReservation $record, array $data) => app(VehicleAssignmentService::class)
                        ->change((string) $record->getKey(), auth()->user(), $data)),
                Tables\Actions\Action::make('handover_qr')
                    ->label('QR Serah Terima')
                    ->icon('heroicon-o-qr-code')
                    ->visible(fn (Model $record): bool => static::mayViewHandover($record))
                    ->url(fn (Model $record): string => static::handoverUrl($type, $record)),
                Tables\Actions\Action::make('handover_pdf')
                    ->label('Bukti Serah Terima PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->visible(fn (Model $record): bool => static::mayViewHandover($record))
                    ->url(fn (Model $record): string => route('loans.handover.pdf', [
                        'type' => $type, 'reservation' => $record->getKey(),
                    ]))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('correct_status')
                    ->label('Koreksi Status (Admin)')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->visible(fn (Model $record): bool => (auth()->user()?->isAdmin() ?? false)
                        && in_array($record->status, [ReservationStatus::ReturnRequested->value, ReservationStatus::Returned->value], true))
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('Alasan koreksi')
                            ->required()->maxLength(2000),
                    ])
                    ->requiresConfirmation()
                    ->action(fn (Model $record, array $data) => app(LoanRequestService::class)->correctReservationStatus(
                        $type, $record->getKey(), ReservationStatus::CheckedOut->value, $data['reason'],
                    )),
            ])
                ->label('Lainnya')
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray')
                ->visible(fn (Model $record): bool =>
                    app(LoanRequestService::class)->canDecideReservation($record)
                    || static::mayViewHandover($record)
                    || ($record instanceof G005M019VehicleReservation
                        && (auth()->user()?->managesReservation($record) ?? false)
                        && in_array($record->status, [ReservationStatus::Approved->value, ReservationStatus::CheckedOut->value], true))
                    || ((auth()->user()?->isAdmin() ?? false)
                        && in_array($record->status, [ReservationStatus::ReturnRequested->value, ReservationStatus::Returned->value], true))),
        ];
    }

    private static function handoverUrl(string $type, Model $record): string
    {
        return PeminjamanSerahTerima::getUrl(['type' => $type, 'reservation' => $record->getKey()]);
    }

    private static function mayViewHandover(Model $record): bool
    {
        return in_array($record->status, [
            ReservationStatus::Approved->value,
            ReservationStatus::CheckedOut->value,
            ReservationStatus::ReturnRequested->value,
            ReservationStatus::Returned->value,
        ], true) && app(LoanHandoverQrService::class)->canView(auth()->user(), $record);
    }
}
