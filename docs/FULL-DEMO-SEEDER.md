# InvManFullDemoSeeder — data demo terisolasi dan opt-in

**Status:** fasilitas demonstrasi eksplisit. `DatabaseSeeder` tetap tidak membuat data pada production. Jangan menambahkan `InvManFullDemoSeeder` ke `DatabaseSeeder`, migrasi atau startup aplikasi.

## Persiapan

1. Backup database dan storage, lalu pastikan restore mungkin dilakukan. Pastikan direktori `storage/` dan `bootstrap/cache/` dapat ditulis.
2. Gunakan commit/migrasi InvMan yang cocok dan sudah diuji. Jangan menggunakan `migrate:fresh` ataupun `db:wipe` pada production.
3. Pastikan role `sarpras` tersedia. Jika belum, `RoleSeeder` perlu dijalankan secara terpisah setelah diperiksa.
4. Jalankan perintah **melalui terminal interaktif**, bukan cron atau CI:

```bash
php artisan invman:seed-full-demo
```

Di production, operator harus mengonfirmasi backup, mengetik `TAMBAH-DATA-DEMO-KE-PRODUCTION`, dan memasukkan password contoh melalui prompt rahasia. Password minimal 16 karakter dan kombinasi huruf besar/kecil, angka, simbol; tidak ditulis ke .env atau argument shell.

**Jangan gunakan** `php artisan db:seed --class=InvManFullDemoSeeder --force` langsung pada production: akses tersebut ditolak. Perintah `php artisan db:seed` biasa tetap no-op pada production.

Untuk akun contoh dengan role `admin` dan `fasilitas` berakses **global** (lihat/kelola data semua unit), tersedia opsi tambahan yang harus dikonfirmasi:

```bash
php artisan invman:seed-full-demo --include-privileged
```

**Sangat tidak dianjurkan pada database produksi yang berisi data asli.** Pada production normal, gunakan akun Administrator/Fasilitas yang sudah ada untuk demonstrasi kontrol pengelola. Jabatan Work Order sendiri tidak memberikan seluruh permission admin.

## Isi demo

Semua data contoh memakai identitas `[DEMO-INVMAN]` atau `invman.demo.*`. Seeder membuat:

- Dua unit baru SD Demo dan SMA Demo; satu kelompok pengelola aset dan satu jenis barang demo.
- Satu gedung, satu lantai, dua ruangan demo (dengan QR melalui service existing).
- Tiga master barang awal: empat proyektor, tiga laptop, satu AC (8 Barang Satuan), ditambah **11 barang struktur pada setiap unit**. Total 25 barang, 28 Barang Satuan, 12 relasi. Contoh meliputi stok bulk, PC komposit (SSD/RAM), mikrofon dalam lemari, switch terpasang pada rak, dan definisi bundle kamera/lampu.
- Dua kendaraan dengan data pengemudi dan kenek bus contoh.
- Akun Sarpras per unit, atasan, staf/SPV/manager fasilitas, pengelola aset, koordinator teknisi, teknisi dan pengemudi.
- Jabatan dinamis dan assignment, tim koordinator/teknisi, dan workflow unit demo dalam status **DRAFT**, atau **PUBLISHED** jika sejak awal operator memilih `--include-privileged`. Publikasi terbatas pada unit demo dan dilakukan sebelum tiket contoh dibuat.
- Tiga pengajuan sebagai **DRAF**: proyektor, aula, minibus; **tidak otomatis dikirim, tidak memesan stok dan tidak tampil sebagai pinjaman disetujui**.
- Dua tiket perawatan status awal `open`; satu tanpa aset, satu tertaut ke AC contoh sehingga dapat mengilustrasikan safety hold.

Tiket demo yang belum disetujui dibuat dengan tenggat SLA kosong agar tidak langsung dieskalasi ke pengguna produksi. Saat operator mulai menangani tiket, service aplikasi menetapkan SLA normal kembali.

Relasi demo dibuat melalui `AssetRelationService` dan mengikuti pembatasan unit/pengelola, larangan siklus, serta aturan peminjaman. **Bundle tidak dapat otomatis dipinjam sebagai paket**; komponen dan instalasi juga tidak dapat dipinjam sendiri. Relasi terlihat pada halaman Barang Satuan. Perubahan barang demo oleh operator tidak ditimpa diam-diam saat seeder dijalankan ulang.

Data dibuat melalui model dan service existing; tidak melalui bypass authorization. Seeder membuat data secara idempotent, sehingga pemanggilan ulang tidak menambah salinan. Seeder **tidak mereset password, role, nama, unit, atau konfigurasi akun existing** dan hanya menerbitkan workflow unit demo (bukan workflow nyata) saat `--include-privileged` disetujui.

## Demo operasional

1. Login `invman.demo.sarpras.sd` untuk menguji pembuatan/pengajuan peminjaman pada aset demo. Tanggal draf dapat disesuaikan jika sudah berlalu.
2. Akses akun Fasilitas operasional untuk menyetujui peminjaman; cek stok/jadwal. Pengajuan yang dikirim mulai mengikuti hold dan notifikasi normal.
3. Lakukan serah-terima QR dengan dua akun berbeda. Konfirmasi pengembalian juga memerlukan kedua pihak.
4. Periksa dua laporan kerusakan demo. Dengan `--include-privileged`, workflow unit demo sudah terpublikasi sehingga tiket mempunyai approval snapshot; tanpa opsi ini workflow tetap draft dan tiket tidak memiliki approval baru hingga dibuat ulang setelah publikasi.
5. Setelah selesai, nonaktifkan akun demo dan jangan gunakan data contoh sebagai transaksi operasional resmi.

## Peringatan produksi

- **Data ini berada di database production yang sama**, bukan sandbox akses. Semua pengguna berizin sesuai aturan aplikasi dapat melihatnya, dan ia bisa muncul pada ringkasan atau laporan.
- Operator yang meneruskan draf atau tiket akan membuat histori/transaksi nyata dan notifikasi mengikuti alur normal.
- Akun role `admin`/`fasilitas` selalu berizin lintas unit menurut desain existing; label DEMO tidak dapat menguranginya.
- Jika data demo sudah dipakai, penghapusan sembarangan akan berisiko merusak foreign key, histori atau audit. Tidak ada fungsi cleanup otomatis.
- Pembuatan QR terjadi di filesystem dan tidak ikut rollback transaksi DB ketika ada kesalahan; file QR yatim perlu diperiksa secara manual bila gagal.
- Fitur ini tidak mengubah hasil audit **NO-GO produksi** sebelumnya. UAT browser dan verifikasi backup, infra, lisensi, queue, scheduler, TLS tetap terpisah.

## Tes yang diperlukan sebelum merge/deploy

```bash
php artisan test --filter=InvManFullDemoSeederTest
php artisan test
```

Uji database dan browser staging, termasuk idempotensi, akun lintas unit, ketersediaan barang, dua tiket dan akses role. Pastikan tidak ada data demo masuk unit dan aset sebenarnya.
