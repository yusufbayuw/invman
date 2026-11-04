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

Artisan::command('tickets:escalate-sla', function (\App\Services\TicketSlaService $sla) {
    $this->info($sla->escalateOverdue().' tenggat SLA tiket dieskalasikan.');
    return Command::SUCCESS;
})->purpose('Idempotently escalate overdue ticket response and repair deadlines');

Schedule::command('tickets:escalate-sla')
    ->hourly()
    ->when(fn (LicenseManager $licenses): bool => $licenses->validateAppUrl()->valid)
    ->withoutOverlapping();

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

Artisan::command('invman:provision-admin {username} {email}', function (\App\Services\AdminProvisioner $provisioner) {
    if (! $this->input->isInteractive()) {
        $this->error('Provisioning admin memerlukan terminal interaktif agar password tidak bocor ke history/log.');

        return Command::FAILURE;
    }

    $password = $this->secret('Kata sandi baru (minimal 16 karakter, huruf besar/kecil, angka, simbol)');
    $confirmation = $this->secret('Konfirmasi kata sandi');

    if (! is_string($password) || $password !== $confirmation) {
        $this->error('Konfirmasi password tidak cocok.');

        return Command::FAILURE;
    }

    try {
        $user = $provisioner->provision(
            (string) $this->argument('username'),
            (string) $this->argument('email'),
            $password,
        );
    } catch (\Illuminate\Validation\ValidationException $e) {
        foreach ($e->errors() as $errors) {
            foreach ($errors as $message) {
                $this->error($message);
            }
        }

        return Command::FAILURE;
    }

    $this->info("Administrator {$user->username} berhasil dibuat. Password tidak ditampilkan atau disimpan dalam log.");

    return Command::SUCCESS;
})->purpose('Create an admin securely; never reset existing credentials');

Artisan::command('invman:security-audit', function (\App\Services\ProductionSecurityAudit $audit) {
    $issues = $audit->findings();
    if ($issues === []) {
        $this->info('Konfigurasi keamanan dasar valid.');

        return Command::SUCCESS;
    }

    foreach ($issues as $issue) {
        $this->error($issue);
    }

    return Command::FAILURE;
})->purpose('Check production security configuration without exposing secrets');


// Deliberately NOT scheduled or called from DatabaseSeeder: demo fixtures require a human.
Artisan::command('invman:seed-full-demo {--include-privileged : Create global admin/facility demo accounts}', function () {
    if (! $this->input->isInteractive()) {
        $this->error('Seeder demo memerlukan terminal interaktif agar sandi tidak bocor.');
        return Command::FAILURE;
    }

    $production = app()->environment('production') || config('app.env') === 'production';
    $privileged = (bool) $this->option('include-privileged');
    $this->warn('Menambahkan data [DEMO-INVMAN]. Tidak ada fasilitas hapus otomatis.');
    $this->warn('Pengajuan yang dibuat hanya draf; tiket demo tetap terlihat pada panel.');
    if ($production) {
        $this->error('DATABASE PRODUCTION: lakukan backup terlebih dahulu.');
        if (! $this->confirm('Backup database/storage sudah diverifikasi?', false)
            || $this->ask('Ketik TAMBAH-DATA-DEMO-KE-PRODUCTION') !== 'TAMBAH-DATA-DEMO-KE-PRODUCTION') {
            $this->error('Dibatalkan: database tidak berubah.');
            return Command::FAILURE;
        }
    }
    if ($privileged && $this->ask('Akun admin/fasilitas demo memiliki AKSES GLOBAL. Ketik AKSES-GLOBAL-DEMO') !== 'AKSES-GLOBAL-DEMO') {
        $this->error('Dibatalkan: akun berhak global tidak dibuat.');
        return Command::FAILURE;
    }

    $password = $this->secret('Sandi kuat untuk akun demo baru (minimal 16 karakter, campuran huruf/angka/simbol)');
    $confirmation = $this->secret('Konfirmasi sandi');
    if (! is_string($password) || $password !== $confirmation) {
        $this->error('Konfirmasi sandi tidak cocok.');
        return Command::FAILURE;
    }

    try {
        \Illuminate\Support\Facades\Validator::make(['password' => $password], [
            'password' => ['required', 'string',
                \Illuminate\Validation\Rules\Password::min(16)->mixedCase()->numbers()->symbols()],
        ])->validate();
    } catch (\Illuminate\Validation\ValidationException $exception) {
        $this->error('Sandi minimal 16 karakter, huruf besar/kecil, angka, dan simbol.');
        return Command::FAILURE;
    }

    $previousPassword = config('security.seed_demo_password');
    $previousAuthorization = config('security.full_demo_command_authorized');
    $previousPrivileged = config('security.full_demo_create_privileged');
    try {
        // Runtime-only switches; never persisted to .env or cached configuration.
        config()->set('security.seed_demo_password', $password);
        config()->set('security.full_demo_command_authorized', true);
        config()->set('security.full_demo_create_privileged', $privileged);
        return $this->call('db:seed', [
            '--class' => \Database\Seeders\InvManFullDemoSeeder::class,
            '--force' => true,
        ]);
    } catch (\Throwable $exception) {
        $this->error('Seeder gagal: '.$exception->getMessage());
        return Command::FAILURE;
    } finally {
        config()->set('security.seed_demo_password', $previousPassword);
        config()->set('security.full_demo_command_authorized', $previousAuthorization);
        config()->set('security.full_demo_create_privileged', $previousPrivileged);
        unset($password, $confirmation);
    }
})->purpose('Create guarded, isolated demo fixtures in development or production');
