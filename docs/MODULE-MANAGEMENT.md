# Manajemen Modul

Pengaturan → Manajemen Modul tersedia hanya bagi role super admin yang dikonfigurasi Filament Shield. Default seluruh modul baru ON; nilai Chat yang sudah ada dipertahankan. Modul inti tidak dapat dimatikan. Status konfigurasi dan status efektif berbeda jika induk atau prasyarat dinonaktifkan.

Graceful Disable: pengajuan baru (termasuk pengajuan dari draf dan on-behalf) ditolak; persetujuan, serah terima, pengembalian, dan perawatan yang sudah berjalan tetap memakai kontrol otorisasi existing. Draf dan histori tidak dihapus. Safety hold dan jadwal rutin yang sudah aktif tetap memblokir aset. Tiket otomatis dari kerusakan saat pengembalian tetap dibuat. Tiket perawatan baru menyimpan mode simple/advanced yang tidak dapat diubah; tiket lama dengan nilai null mempertahankan perilaku lama. Saat Work Order OFF, tiket baru menggunakan perawatan dasar tanpa workflow approval dinamis, estimasi, pembelian, vendor dan revisi.

Pengaturan dicatat pada module_setting_audits. Perubahan berlaku global, bukan per unit atau per role, tanpa restart worker. Jalankan migrasi sebelum memakai halaman. Jangan ubah permission Shield hanya untuk menonaktifkan modul.

## Verifikasi sebelum rilis

- Jalankan php artisan test --no-ansi pada SQLite dan MySQL.
- Pastikan akses Resource, Page, Livewire, impor, aksi relasi, direct URL, QR, dashboard, favorit, notifikasi, dan scheduler sesuai konfigurasi.
- Uji peminjaman campuran, draf lama, pengembalian rusak, perawatan simple/advanced, safety hold, persetujuan, riwayat, reaktivasi dan perubahan konfigurasi bersamaan.
- UAT setiap role di staging; jangan merge jika ada jalur yang melewati pengaturan atau kewajiban existing terhambat.
