<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\LoanRequestService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('loans:expire-holds', function (LoanRequestService $service) {
    $count = $service->expireStaleHolds();
    $this->info("{$count} hold peminjaman kedaluwarsa telah diproses.");
})->purpose('Melepaskan reservasi peminjaman yang melewati batas waktu persetujuan');

Schedule::command('loans:expire-holds')
    ->everyMinute()
    ->withoutOverlapping();
