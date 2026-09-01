<?php

use App\Licensing\InvalidAllowlist;
use App\Licensing\LicenseManager;
use App\Models\G003M006Room;
use App\Services\LoanRequestService;
use App\Services\RoomQrCodeService;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('rooms:generate-qr {--force}', function (RoomQrCodeService $qrCodes) {
    $count = 0;

    G003M006Room::query()->chunkById(100, function ($rooms) use ($qrCodes, &$count): void {
        foreach ($rooms as $room) {
            $qrCodes->ensure($room, (bool) $this->option('force'));
            $count++;
        }
    });

    $this->info("{$count} QR code ruangan tersedia.");

    return Command::SUCCESS;
})->purpose('Generate missing QR codes for public room schedule pages');

Artisan::command('license:request', function (LicenseManager $licenses) {
    try {
        $request = $licenses->requestDocument();
    } catch (InvalidAllowlist $exception) {
        $this->error($exception->getMessage());

        return Command::FAILURE;
    }

    $this->line(json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    return Command::SUCCESS;
})->purpose('Generate a canonical offline license request without exposing the token');

Artisan::command('license:status', function (LicenseManager $licenses) {
    $configuration = $licenses->validateConfiguration();
    $applicationUrl = $licenses->validateAppUrl();
    $context = [...$configuration->context, ...$applicationUrl->context];

    $this->table(['Field', 'Value'], [
        ['Status', $applicationUrl->valid ? 'VALID' : 'LOCKED'],
        ['Reason', $applicationUrl->code->value],
        ['License ID', $context['license_id'] ?? '-'],
        ['Issued at', $context['issued_at'] ?? '-'],
        ['Allowlist fingerprint', $context['fingerprint'] ?? '-'],
        ['APP_URL host', parse_url((string) config('app.url'), PHP_URL_HOST) ?: '-'],
    ]);

    return $applicationUrl->valid ? Command::SUCCESS : Command::FAILURE;
})->purpose('Check the offline license and APP_URL host without printing the token');

Artisan::command('loans:expire-holds', function (LoanRequestService $service, LicenseManager $licenses) {
    $status = $licenses->validateAppUrl();

    if (! $status->valid) {
        $this->error("License check failed: {$status->code->value}");

        return Command::FAILURE;
    }

    $count = $service->expireStaleHolds();
    $this->info("{$count} hold peminjaman kedaluwarsa telah diproses.");

    return Command::SUCCESS;
})->purpose('Melepaskan reservasi peminjaman yang melewati batas waktu persetujuan');

Artisan::command('loans:notify-overdue', function (LoanRequestService $service, LicenseManager $licenses) {
    $status = $licenses->validateAppUrl();

    if (! $status->valid) {
        $this->error("License check failed: {$status->code->value}");

        return Command::FAILURE;
    }

    $count = $service->notifyOverdueLoans();
    $this->info("{$count} pengingat peminjaman terlambat telah dikirim.");

    return Command::SUCCESS;
})->purpose('Mengirim pengingat peminjaman yang melewati batas pengembalian');

Schedule::command('loans:expire-holds')
    ->everyMinute()
    ->when(fn (LicenseManager $licenses): bool => $licenses->validateAppUrl()->valid)
    ->withoutOverlapping();

Schedule::command('loans:notify-overdue')
    ->hourly()
    ->when(fn (LicenseManager $licenses): bool => $licenses->validateAppUrl()->valid)
    ->withoutOverlapping();
