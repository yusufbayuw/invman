<?php

use App\Licensing\InvalidAllowlist;
use App\Licensing\LicenseManager;
use App\Services\LoanRequestService;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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

Schedule::command('loans:expire-holds')
    ->everyMinute()
    ->when(fn (LicenseManager $licenses): bool => $licenses->validateAppUrl()->valid)
    ->withoutOverlapping();
