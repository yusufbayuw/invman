{{-- Embedded same-origin PDF preview. Controllers re-authorize every request. --}}
<div class="space-y-3">
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Periksa isi dokumen sebelum mengunduh. Jika pratinjau tidak didukung browser,
        gunakan tombol <strong>Unduh PDF</strong> di bawah.
    </p>

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-white/10">
        <iframe
            title="{{ $title ?? 'Pratinjau dokumen PDF' }}"
            src="{{ $previewUrl }}"
            class="block w-full"
            style="height: 68vh; min-height: 360px;"
            loading="eager"
            referrerpolicy="no-referrer"
        >
            Browser tidak mendukung pratinjau PDF.
        </iframe>
    </div>

    <div class="flex justify-end">
        <a
            href="{{ $downloadUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:bg-primary-500 dark:hover:bg-primary-400"
        >
            <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-5 w-5" />
            Unduh PDF
        </a>
    </div>
</div>
