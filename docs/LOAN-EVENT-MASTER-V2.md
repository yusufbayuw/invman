# Kegiatan Master & Traceability V2

## Keputusan arsitektur

`loan_events` adalah **identitas kegiatan yang independen** dari proses peminjaman.
Setiap record `g004_m008_activities` tetap merupakan pengajuan/transaksi
terpisah, lengkap dengan status, hold, keputusan, aset, konfirmasi serah-terima,
ulasan, serta histori audit sendiri. Semua transaksi dapat merujuk ke satu
`loan_event_id` tanpa berbagi status atau lock.

- Kegiatan master mencatat unit, pembuat, nama, deskripsi, dan jadwal opsional.
- Pengajuan Cepat: buat kegiatan master otomatis atau pilih salah satu yang
  sudah ada (termasuk kegiatan dengan pengajuan yang telah dibatalkan).
- Pengajuan Lengkap: field opsional `Gunakan kegiatan master`; bila kosong,
  master dibuat saat transaksi dibuat. Draf tetap mengingat master.
- Halaman **Peminjaman > Kegiatan Master** menyediakan daftar/detail dan
  pembuatan kegiatan tanpa harus mengajukan aset dulu. Edit hanya sebelum
  kegiatan digunakan; kegiatan dengan histori tidak boleh dihapus.
- Halaman detail peminjaman memiliki tautan ke master, dan **Rekapan Penggunaan**
  bisa dikelompokkan/difilter berdasarkan satu master kegiatan.

### Pertahanan keamanan

- Pilihan master selalu diverifikasi ulang dari database untuk **unit pemohon**
  dan peran yang berhak, tidak sekadar mengandalkan filter dropdown.
- Foreign key `loan_event_id` tidak dimasukkan ke Eloquent `$fillable`.
  Service menetapkannya secara eksplisit hanya setelah validasi unit.
- Data pemohon, unit dan status selalu ditentukan oleh service dari akun,
  tidak diambil dari input; semua pemeriksaan stok/jadwal memakai service lama.
- Query halaman master dibatasi pada unit pengguna, termasuk akses record langsung.
- Satu kegiatan yang sudah digunakan tidak dapat diubah dari UI agar judul
  historis tidak berubah diam-diam, dan tidak dapat dihapus melalui model.

## Migrasi aman

`2026_10_10_000001_create_loan_event_masters.php` hanya **menambahkan**
`loan_events` dan kolom nullable `loan_event_id`, lalu melakukan backfill:

1. Setiap pengajuan lama tanpa `related_activity_id` menjadi kegiatan master
   (satu kegiatan baru dengan metadata asli).
2. Setiap anak `related_activity_id` mendapat `loan_event_id` yang sama
   dengan induknya, meski status induk sudah ditolak, dibatalkan, atau selesai.
3. Tidak ada perubahan ID, status, keputusan, tabel reservasi, atau histori
   `related_activity_id`. Relasi legacy tetap dipertahankan untuk tautan lama.
4. Tidak ada backfill nama berbasis kemiripan teks: hanya hubungan eksplisit
   dianggap satu kegiatan.

**Sebelum deploy:** backup DB dan verifikasi restorasi, uji migrasi pada salinan
staging. Jangan menjalankan rollback jika sudah ada kegiatan baru yang hanya
memiliki representasi di tabel master. Pastikan proses upgrade schema dilakukan
sebelum mengaktifkan kode baru.

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan test --filter=LoanEventMasterTest
php artisan test
```

### Periksa integritas sesudah migrasi

```sql
SELECT COUNT(*) AS total_requests FROM g004_m008_activities;
SELECT COUNT(*) AS unmapped_requests
  FROM g004_m008_activities WHERE loan_event_id IS NULL;
SELECT COUNT(*) AS total_master_events FROM loan_events;
SELECT COUNT(*) AS cross_unit_links
  FROM g004_m008_activities a
  JOIN loan_events e ON e.id = a.loan_event_id
 WHERE a.g001_m001_unit_id <> e.g001_m001_unit_id;
```

`unmapped_requests` dan `cross_unit_links` untuk data normal harus 0.
Pengajuan yang dahulu memiliki unit NULL juga perlu diaudit manual jika ada.
Sesudah deploy, lakukan uji end-to-end satu kegiatan dengan dua aset dan
pengembalian terpisah. Uji GitHub Actions belum menggantikan pengujian
UI/perangkat di server staging.
