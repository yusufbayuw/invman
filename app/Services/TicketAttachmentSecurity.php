<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class TicketAttachmentSecurity
{
    public const MAX_BYTES = 5242880;

    private const MIME_BY_EXTENSION = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'pdf' => 'application/pdf',
    ];

    /**
     * Examine the stored bytes, not a client-supplied MIME type. Used on uploads
     * and when deciding whether older attachments may be previewed inline.
     *
     * @return array{mime: string, size: int}
     */
    public function inspect(string $path, bool $scan = true): array
    {
        if (! preg_match('~^tickets/attachments/[A-Za-z0-9_.\-/]+$~D', $path)
            || str_contains($path, '..') || str_contains($path, '\\')
            || str_starts_with($path, '/') || preg_match('/[\x00-\x1f]/', $path)) {
            throw ValidationException::withMessages(['files' => 'Lokasi lampiran tidak valid.']);
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            throw ValidationException::withMessages(['files' => 'Lampiran tidak ditemukan.']);
        }

        $size = (int) $disk->size($path);
        if ($size < 1 || $size > self::MAX_BYTES) {
            throw ValidationException::withMessages(['files' => 'Ukuran lampiran tidak valid atau melebihi 5 MB.']);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $expectedMime = self::MIME_BY_EXTENSION[$extension] ?? null;
        $absolute = $disk->path($path);
        $mime = (string) ((new \finfo(FILEINFO_MIME_TYPE))->file($absolute) ?: '');

        if ($expectedMime === null || $mime !== $expectedMime) {
            throw ValidationException::withMessages(['files' => 'Ekstensi dan isi lampiran tidak sesuai.']);
        }

        if ($mime === 'application/pdf') {
            $handle = fopen($absolute, 'rb');
            $signature = $handle ? fread($handle, 5) : null;
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($signature !== '%PDF-') {
                throw ValidationException::withMessages(['files' => 'Signature PDF tidak valid.']);
            }
        } else {
            $image = @getimagesize($absolute);
            $expectedType = $mime === 'image/jpeg' ? IMAGETYPE_JPEG : IMAGETYPE_PNG;
            if (! is_array($image) || ($image[2] ?? null) !== $expectedType) {
                throw ValidationException::withMessages(['files' => 'Isi gambar tidak valid.']);
            }
        }

        // Reject simple embedded executable content as an additional guard;
        // this is not a replacement for malware scanning.
        $handle = fopen($absolute, 'rb');
        $sample = $handle ? fread($handle, 65536) : false;
        if (is_resource($handle)) {
            fclose($handle);
        }
        if (is_string($sample) && preg_match('/<\?(?:php|=)|<script\b/i', $sample)) {
            throw ValidationException::withMessages(['files' => 'Lampiran mengandung konten yang tidak diizinkan.']);
        }

        if ($scan) {
            $this->scanMalware($absolute);
        }

        return ['mime' => $mime, 'size' => $size];
    }

    private function scanMalware(string $path): void
    {
        $required = (bool) config('ticket_uploads.require_malware_scan', false);
        $binary = (new ExecutableFinder)->find((string) config('ticket_uploads.clamav_binary', 'clamscan'));

        if (! $binary) {
            if ($required) {
                throw ValidationException::withMessages(['files' => 'Antivirus tidak tersedia; upload ditolak.']);
            }

            return;
        }

        $process = new Process([$binary, '--no-summary', $path]);
        $process->setTimeout((float) config('ticket_uploads.clamav_timeout', 30));
        $process->run();

        if ($process->getExitCode() === 1) {
            throw ValidationException::withMessages(['files' => 'Lampiran terdeteksi sebagai malware.']);
        }

        if ($process->getExitCode() !== 0 && $required) {
            throw ValidationException::withMessages(['files' => 'Pemindaian antivirus gagal.']);
        }
    }
}
