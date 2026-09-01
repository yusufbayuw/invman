<?php

use App\Http\Controllers\MikrotikHotspotCaptiveController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\PublicRoomScheduleController;
use App\Http\Controllers\RoomQrCodePdfController;
use Illuminate\Support\Facades\Route;

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

Route::get('test-mikrotik', function () {
    return view('mikrotik.test');
})->name('test.mikrotik');
Route::get('test-add-user', function () {
    return view('mikrotik.test-add-user');
})->name('test.add.user');

Route::get('captive-login', [MikrotikHotspotCaptiveController::class, 'login'])->name('mikrotik.login');
Route::post('captive-login', [MikrotikHotspotCaptiveController::class, 'login']);

Route::get('captive-portal', [MikrotikHotspotCaptiveController::class, 'showLogin'])->name('mikrotik.login.show');
Route::post('captive-portal', [MikrotikHotspotCaptiveController::class, 'showLogin']);
