<x-filament-panels::page>
    <div class="mx-auto w-full max-w-4xl space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-gray-700 dark:border-primary-800 dark:bg-primary-950/30 dark:text-gray-200">
            <div>
                <p class="font-semibold text-gray-950 dark:text-white">Cukup satu aset dan satu alasan singkat</p>
                <p class="text-gray-600 dark:text-gray-300">Identitas pemohon otomatis. Pengemudi kendaraan diatur pengelola setelah pengajuan.</p>
            </div>
            <x-filament::button
                tag="a"
                :href="\App\Filament\Pages\AjukanPeminjaman::getUrl()"
                color="gray"
                outlined
                icon="heroicon-o-rectangle-stack"
            >
                Pengajuan lengkap
            </x-filament::button>
        </div>

        <form wire:submit="submit" class="space-y-5">
            {{ $this->form }}

            <div class="flex flex-wrap items-center justify-end gap-3">
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    Stok dan bentrok jadwal diperiksa kembali saat pengajuan dikirim.
                </span>
                <x-filament::button type="submit" size="lg" icon="heroicon-o-paper-airplane" wire:loading.attr="disabled" wire:target="submit">
                    Ajukan Sekarang
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
