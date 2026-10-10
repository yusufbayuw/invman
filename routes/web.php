<?php

use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\PublicRoomScheduleController;
use App\Http\Controllers\RoomQrCodePdfController;
use App\Http\Controllers\LoanHandoverPdfController;
use App\Http\Controllers\TicketAttachmentController;
use App\Services\LoanHandoverQrService;
use App\Filament\Pages\PeminjamanSerahTerima;
use Illuminate\Support\Facades\Route;

Route::get('/tiket/lampiran/{attachment}', TicketAttachmentController::class)
    ->middleware(['auth', 'throttle:30,1'])
    ->whereUuid('attachment')
    ->name('tickets.attachment');

Route::get('/pinjam/bukti/{type}/{reservation}/pdf', LoanHandoverPdfController::class)
    ->middleware(['auth', 'throttle:20,1'])
    ->whereIn('type', ['item', 'room', 'vehicle'])
    ->whereUuid('reservation')
    ->name('loans.handover.pdf');

Route::get('/pinjam/scan/{type}/{reservation}', function (string $type, string $reservation) {
    $qr = app(LoanHandoverQrService::class);
    $record = $qr->resolve($type, $reservation);

    // Never reveal an unowned or unrelated reservation via QR scanning.
    abort_unless($qr->canView(auth()->user(), $record), 404);

    return redirect(PeminjamanSerahTerima::getUrl([
        'type' => $type,
        'reservation' => $reservation,
    ]));
})->middleware(['auth', 'signed', 'throttle:20,1'])
    ->whereIn('type', ['item', 'room', 'vehicle'])
    ->whereUuid('reservation')
    ->name('loans.handover.scan');

Route::middleware(['auth', 'throttle:30,1'])
    ->prefix('push')
    ->name('push.')
    ->group(function (): void {
        Route::get('/vapid-public-key', [PushSubscriptionController::class, 'publicKey'])
            ->name('vapid-public-key');
        Route::post('/subscriptions', [PushSubscriptionController::class, 'store'])
            ->name('subscriptions.store');
        Route::delete('/subscriptions', [PushSubscriptionController::class, 'destroy'])
            ->name('subscriptions.destroy');
    });

Route::get('/ruangan/{room:qr_uuid}/qrcode-a4', RoomQrCodePdfController::class)
    ->whereUuid('room')
    ->name('public.rooms.qrcode-pdf');
Route::get('/ruangan/{room:qr_uuid}', PublicRoomScheduleController::class)
    ->whereUuid('room')
    ->name('public.rooms.show');

Route::get('/storage/users-avatar/avatar.png', function () {
    return response()->file(public_path('images/app/fav.png'));
})->name('avatar.default');
Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');
