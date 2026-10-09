# Reservasi Kendaraan dan Personel Operasional

## Kebijakan

- Pengguna hanya memilih kendaraan dan jadwal; tidak memilih pengemudi atau kenek.
- Kendaraan operasional **wajib** memiliki pengemudi saat disetujui dan ketika diberangkatkan.
- Bus wajib memiliki **kenek** selain pengemudi. Penugasan kenek adalah data internal pengelola, bukan informasi pemohon.
- Kendaraan sewa sudah termasuk pengemudi dan berada di luar alur penugasan personel internal ini.
- Pengemudi default dikelola dari master **Kendaraan** (`default_driver_id`). Perubahan default tidak mengubah reservasi yang sudah ditugaskan.
- Pengelola dapat mengganti pengemudi/kenek pada reservasi **Disetujui** maupun **Sedang Dipakai** dengan alasan wajib dan histori immutable.
- Pengemudi dan kenek tidak boleh memiliki dua penugasan aktif bertabrakan, termasuk perjalanan yang terlambat kembali.

## Pengoperasian

1. Buka **Kendaraan** dan tetapkan **Pengemudi Default**.
2. Aktifkan **Bus: wajib kenek** untuk setiap bus. Tiga kendaraan seed `BUS 01`, `BUS 02`, `BUS 03` ditandai otomatis melalui migration dan seeder; periksa armada lain secara manual.
3. Buka **Kendaraan > Kenek Bus** untuk mencatat personel internal. Nonaktifkan kenek yang tidak boleh ditugaskan lagi (jangan hapus historinya).
4. Pengguna mengajukan kendaraan tanpa perlu memilih personel.
5. Pada aksi **Setujui** di daftar peminjaman, relation manager, atau quick action, pengelola mengonfirmasi pengemudi (default sudah disarankan) dan kenek jika bus.
6. **Pinjamkan** kembali memeriksa bahwa penugasan lengkap dan tidak bertabrakan.
7. Gunakan **Ganti Penugasan** untuk pergantian khusus. Isi alasan, jangan mengubah master default kecuali pergantian permanen dikehendaki.
8. Riwayat penugasan dapat diperiksa pada tampilan detail reservasi kendaraan.

## Migrasi

Migration `2026_10_09_000001_add_vehicle_crew_assignments.php`:

- Menambahkan `default_driver_id` dan `requires_assistant` pada master kendaraan.
- Mengisi driver default dari relasi lama `g008_m018_drivers.vehicle_default` secara deterministik: bila satu kendaraan memiliki beberapa pengemudi default lama, ID terkecil diambil. Verifikasi hasilnya setelah deploy.
- Membuat tabel `vehicle_assistants` dan `vehicle_assignment_histories`.
- Menjadikan `g005_m019_vehicle_reservations.g008_m018_driver_id` nullable untuk draf/pengajuan, tetapi pengemudi wajib saat persetujuan.
- Mengubah foreign key pengemudi dan kendaraan pada reservasi menjadi **restrict on delete**, melindungi histori dari penghapusan cascade.

### Deployment

Backup MySQL terlebih dahulu, verifikasi restore dan migration pada staging, kemudian:

```bash
php artisan down
php artisan migrate --force
php artisan filament:optimize-clear
php artisan optimize:clear
php artisan up
```

Lakukan pengecekan setiap kendaraan, pengemudi default dan flag bus sebelum operasi normal. Rollback migration tidak dapat dilakukan bila masih ada reservasi dengan pengemudi kosong, untuk menghindari kehilangan data. Jangan menghapus draf hanya demi rollback.

## Kendali konkurensi

`LoanRequestService::submitDraft()` sekarang mengambil row lock aktivitas, lalu mengunci item, ruangan, dan kendaraan berdasarkan urutan ID **di dalam transaksi yang sama**, kemudian membaca ulang ketersediaan dan membuat hold. Pembacaan relasi sebelum validasi juga menggunakan locking read agar snapshot InnoDB tidak terlalu dini.

Persetujuan/pergantian personel mengunci baris pengemudi dan kenek sebelum mengecek tumpang tindih. Jika validasi gagal, transaksi dibatalkan tanpa perubahan status atau penugasan parsial.

## Uji regresi

```bash
php artisan test --filter=VehicleCrewWorkflowTest
php artisan test --filter=LoanBookingLifecycleTest
php artisan test
```

GitHub Actions menjalankan lint PHP, frontend Vite build, Laravel test suite (SQLite), serta MySQL `migrate:fresh` smoke test. Tes bersamaan di bawah beban nyata dan verifikasi antarmuka pada staging masih dianjurkan sebelum mengaktifkan deployment production.
