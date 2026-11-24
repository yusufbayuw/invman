<x-filament-panels::page>
    <x-filament::section heading="Sistem Inti — selalu aktif" description="Pengguna, unit, peran, izin, pengaturan dan lisensi tetap tersedia.">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Menonaktifkan modul menutup aktivitas baru; transaksi yang sedang berjalan dan histori tetap dipertahankan.
            Status submodul mengikuti ketergantungannya. Simpan untuk menerapkan perubahan ke seluruh aplikasi.
        </p>
    </x-filament::section>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <div class="flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-check">Simpan Pengaturan</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
