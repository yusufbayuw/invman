# Ticketing Core V1 — InvMan

## Ruang lingkup yang diaktifkan
Satu engine tiket yang dapat dikembangkan ke berbagai layanan, dengan kategori awal khusus sarana, prasarana, kendaraan, insiden peminjaman, dan permintaan fasilitas. Halaman Filament `/admin/tiket` menampilkan antrean sesuai hak pengguna. Pengguna dapat membuat tiket, menautkan aset inventaris, mengomentari, mengunggah bukti privat, dan mengelola status jika berwenang.

### Jenis dan alur status
Tiket dimulai pada `open`. Pengelola dapat melanjutkan ke `triaged`, `in_progress`, `waiting_requester`, `waiting_parts`, `resolved`, `closed`, atau `cancelled` mengikuti transisi service, bukan update bebas melalui form. Pelapor dapat menutup tiket resolved serta membuka ulang tiket yang telah ditutup. Penugasan hanya kepada anggota pengelola aset tersebut atau pengguna fasilitas, tidak kepada akun asing.

### Data dan relasi
- `tickets`: UUID, nomor unik `TKT-YYYY-RANDOM`, kategori, pelapor, unit, pengelola, penanggung jawab, kaitan pengajuan opsional, prioritas, status, tanggal penyelesaian.
- `ticket_categories`: master kategori aktif yang dapat dikembangkan melalui migrasi/konfigurasi administratif.
- `ticket_asset_links`: referensi aset berjenis `item`, `item_instance`, `room`, `vehicle`. Whitelist divalidasi di service; tidak menambah kolom baru pada master aset.
- `ticket_comments`: komentar publik dan catatan internal pengelola.
- `ticket_attachments`: lampiran yang hanya dapat diambil melalui URL autentikasi; penyimpanan privat disk `local`.
- `ticket_events`: histori append-only perubahan status, penugasan, komentar, dan kejadian yang menimbulkan tiket.

### Aturan akses
Pelapor hanya dapat melihat tiket miliknya; petugas yang ditugaskan dan pengelola aset dapat melihat tiket terkait kewenangannya; fasilitas/admin dapat melihat seluruh tiket. Catatan internal dan lampirannya tidak terlihat oleh pelapor biasa. Parameter URL/query tidak menambah kewenangan. Kategori awal tidak memberi pelapor akses ke data aset unit lain.

### Hubungan peminjaman
Ketika checklist pengembalian mencatat aset tidak baik, tiket otomatis dibuat per aset rusak dan satu reservasi (unique `source_key`). Tiket tidak mengubah status pengembalian, dan menutup tiket tidak mengembalikan aset menjadi `is_available=true`: pemeriksaan kondisi aset tetap dilakukan dengan workflow inventaris yang sudah ada. Jalur lama peminjaman dan receipt OUT/RTN tetap dipertahankan.

### Batasan V1
Belum ada SLA berbasis jam kerja, penugasan otomatis untuk kategori umum tanpa aset, alokasi biaya kerja/perbaikan, portal pelapor eksternal, QR tiket, maintenance preventif, atau AI triase. Itu fase berikutnya; jangan menganggapnya telah aktif. Tiket yang tidak memiliki aset diarahkan ke fasilitas/admin sampai penugasan eksplisit dilakukan.

### Operasional
Backup database dan validasi staging sebelum migrasi:
```bash
php artisan migrate --force
php artisan optimize:clear
php artisan test --filter=TicketingCoreTest
php artisan test
```
Uji role sarpras, fasilitas, pengelola aset, dan unit asing. Konfirmasi file dalam `storage/app/private/tickets/attachments` tidak bisa diambil dari symlink `public/storage`. Konfigurasi queue dan scheduler lama tidak diubah dalam fase pertama ini.
