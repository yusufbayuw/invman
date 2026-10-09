# Pembatasan Akses Peminjaman dan Retensi Histori

## Batas akses operasional

Sumber aturan pembacaan privat adalah `App\\Services\\LoanVisibility`.
Aturan ini dipakai bersama oleh resource kegiatan, global search, widget
dasbor, kalender, dan laporan. Filter tanggal/status/unit adalah **penyaring
tambahan**, tidak boleh memperluas data yang sudah lolos otorisasi.

| Akun | Kegiatan yang boleh dibaca |
|---|---|
| Admin/Fasilitas | Seluruh unit |
| Sarpras | Kegiatan unitnya |
| Pengelola aset | Kegiatan yang mengandung barang/ruangan/kendaraan dalam kelompok `itemManagements` yang ditugaskan |
| Tanpa peran atau penugasan | Tidak ada data peminjaman privat |

Jika seseorang memiliki dua penugasan, cakupan efektif adalah gabungannya.
Untuk tindakan operasional pada reservasi tertentu, gunakan otorisasi
`User::managesReservation()`, bukan hanya akses kegiatan.

Kegiatan lintas unit yang menampung beberapa kebutuhan dapat muncul pada
statistik kegiatan pengelola yang terlibat. Aksi persetujuan dan serah-terima
tetap dibatasi pada kebutuhan yang benar-benar dikelolanya. Relation manager,
penghitung reservasi, dan detail review/checklist/lampiran pada kegiatan lintas
unit juga disaring agar pengelola tidak bisa mengakses kebutuhan tim lain.

## Perlindungan retensi

Migration `2026_10_09_000002_restrict_historical_loan_cascades.php`
memperbarui foreign key berisiko di **MySQL/MariaDB** menjadi `RESTRICT`.
Dengan demikian, tidak mungkin menghapus pengguna, unit, jenis barang,
kelompok pengelola, ruangan, aset, atau kegiatan yang masih menjadi induk
transaksi melalui **SQL langsung** maupun operasi Filament tanpa lebih dulu
mendapatkan penolakan dari database.

Histori aset, ulasan, checklist, dan detail reservasi juga mendapat proteksi
dari penghapusan berantai. Tombol penghapusan langsung atau bulk untuk
pengguna, unit, dan ruangan dihilangkan; operasi pembatalan kegiatan tetap
dipakai alih-alih menghapus histori.

**Catatan database:** SQLite tidak mendukung mengganti named foreign keys
yang sudah dibuat. Migration ini tidak mengubah FK lama pada SQLite. Keamanan
database level dipastikan oleh job MySQL dalam CI; deploy production harus
menggunakan MySQL/MariaDB. Jangan memakai hasil tes SQLite sebagai bukti
perlindungan FK produksi.

## Deployment

1. Backup database dan verifikasi restore.
2. Uji migration pada database staging dengan data yang mewakili produksi.
3. Jalankan `php artisan migrate --force`.
4. Jalankan `php artisan filament:optimize-clear` kemudian `php artisan optimize:clear`.
5. Verifikasi akses dengan akun admin, fasilitas, Sarpras dua unit berbeda,
   pengelola aset, dan akun tanpa penugasan.
6. Simulasikan permintaan hapus di staging: record master yang direferensikan
   histori harus mendapat galat constraint, bukan menghapus historinya.

Rollback migration akan mengembalikan aturan `CASCADE` bawaan. **Jangan
menjalankan rollback di produksi** tanpa evaluasi dampak retensi; lebih aman
memperbaiki masalah melalui migration forward-only.

## CI

`php artisan test` menjalankan regresi aplikasi SQLite.
GitHub Actions job MySQL menjalankan `migrate:fresh` serta
`php artisan test --filter=LoanAccessAndHistoryTest` untuk memastikan
aturan visibility dan integritas foreign key tidak hanya berupa asumsi.
