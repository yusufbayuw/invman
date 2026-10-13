<?php

use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\PublicRoomScheduleController;
use App\Http\Controllers\RoomQrCodePdfController;
use App\Http\Controllers\LoanHandoverPdfController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Controllers\TicketMaintenancePdfController;
use App\Models\G003M006Room;
use App\Models\G002M015ItemInstance;
use App\Filament\Resources\TicketResource;
use App\Services\TicketService;
use App\Services\LoanHandoverQrService;
use App\Filament\Pages\PeminjamanSerahTerima;
use Illuminate\Support\Facades\Route;

// Per-item QR is generated on demand, without generating thousands of permanent files.
Route::get('/tiket/lapor/barang/{item}', function (G002M015ItemInstance $item) {
    $service = app(TicketService::class);
    abort_unless($service->canCreate(auth()->user())
        && $service->assetOptionLabel('item_instance', $item->id, auth()->user()) !== null, 403);
    return redirect(TicketResource::getUrl('create', [
        'location' => $item->room?->name ?: $item->name,
        'asset_type' => 'item_instance',
        'asset_id' => $item->id,
    ]));
})->middleware(['auth', 'throttle:30,1'])->whereNumber('item')->name('tickets.report.item');

Route::get('/tiket/qr/barang/{item}', function (G002M015ItemInstance $item) {
    $service = app(TicketService::class);
    abort_unless($service->canCreate(auth()->user())
        && $service->assetOptionLabel('item_instance', $item->id, auth()->user()) !== null, 403);

    $result = (new \Endroid\QrCode\Builder\Builder(
        writer: new \Endroid\QrCode\Writer\PngWriter,
        writerOptions: [],
        validateResult: false,
        data: route('tickets.report.item', ['item' => $item->id]),
        encoding: new \Endroid\QrCode\Encoding\Encoding('UTF-8'),
        errorCorrectionLevel: \Endroid\QrCode\ErrorCorrectionLevel::High,
        size: 420,
        margin: 15,
        roundBlockSizeMode: \Endroid\QrCode\RoundBlockSizeMode::Margin,
    ))->build();
    return response($result->getString())->header('Content-Type', 'image/png')
        ->header('Cache-Control', 'private, no-store');
})->middleware(['auth', 'throttle:30,1'])->whereNumber('item')->name('tickets.report.item.qr');

// Existing room QR opens the public room page, with a safe logged-in report shortcut.
Route::get('/tiket/lapor/ruangan/{room:qr_uuid}', function (G003M006Room $room) {
    $service = app(TicketService::class);
    abort_unless($service->canCreate(auth()->user()), 403);
    $allowed = $service->assetOptionLabel('room', $room->id, auth()->user()) !== null;
    return redirect(TicketResource::getUrl('create', array_filter([
        'location' => $room->name,
        'asset_type' => $allowed ? 'room' : null,
        'asset_id' => $allowed ? $room->id : null,
    ], fn ($value) => $value !== null)));
})->middleware(['auth', 'throttle:30,1'])
    ->whereUuid('room')->name('tickets.report.room');

Route::get('/tiket/{ticket}/perawatan/pdf', TicketMaintenancePdfController::class)
    ->middleware(['auth', 'throttle:20,1'])->whereUuid('ticket')
    ->name('tickets.maintenance.pdf');

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
