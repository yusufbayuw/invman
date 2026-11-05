<x-filament::section>
    <x-slot name="heading">Panduan pengaturan Peran (Filament Shield)</x-slot>
    <x-slot name="description">
        Menu ini mengatur izin dasar aplikasi. Jabatan Work Order, penugasan, dan hierarki tim diatur di menu terpisah.
    </x-slot>

    <div class="grid gap-3 text-sm text-gray-700 dark:text-gray-300 md:grid-cols-2">
        <div>
            <p class="font-semibold text-gray-950 dark:text-white">Nama peran dan guard</p>
            <p>Nama adalah identitas kelompok hak akses. Guard harus sesuai autentikasi aplikasi (web); perubahan peran pada akun dapat mengubah menu yang tersedia.</p>
        </div>
        <div>
            <p class="font-semibold text-gray-950 dark:text-white">Permission / izin</p>
            <p>Centang hanya izin yang diperlukan: melihat, membuat, mengubah, atau tindakan lain. Mengaktifkan membuka peluang tindakan; mencabut dapat menghambat pekerjaan pengguna.</p>
        </div>
    </div>
    <p class="mt-3 text-sm font-medium text-amber-700 dark:text-amber-300">
        Perhatian: peran admin adalah super-admin dan mempunyai kewenangan luas.
        Menambah peran baru tidak otomatis menambahkan aturan bisnis atau kewenangan lintas unit.
        Tinjau pengguna terdampak sebelum mengubah izin.
    </p>
</x-filament::section>
