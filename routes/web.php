<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MikrotikHotspotCaptiveController;
use App\Http\Controllers\PublicLoanScheduleController;

Route::get('/', PublicLoanScheduleController::class)->name('public.loan-schedule');
Route::get('/storage/users-avatar/avatar.png', function () {
    return response()->file(public_path(config('app.logo')));
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
