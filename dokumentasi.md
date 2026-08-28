# Dokumentasi Ringkas InvMan

InvMan adalah aplikasi pengelolaan inventaris dan peminjaman barang, ruangan, serta kendaraan. Jadwal peminjaman yang sudah disetujui dapat dilihat tanpa login, sedangkan pengajuan dan pengelolaan dilakukan melalui panel admin.

## Alamat aplikasi

- Jadwal publik: `/`
- Login: `/admin/login`
- Panel aplikasi setelah login: `/admin`

Jika dijalankan melalui Laravel Herd, alamat lokal biasanya mengikuti nama folder proyek, misalnya `http://invman.test`.

## Akun bawaan seeder

Semua akun berikut memakai kata sandi seeder `password`.

| Username | Peran | Unit / cakupan |
| --- | --- | --- |
| `admin` | Administrator | Seluruh modul dan pengaturan |
| `fasilitas` | Admin Fasilitas | Verifikasi dan operasional seluruh peminjaman |
| `sarpras.daycare` | Sarpras Unit | Daycare, KB & TK |
| `sarpras.sd` | Sarpras Unit | SD |
| `sarpras.smp` | Sarpras Unit | SMP |
| `sarpras.sma` | Sarpras Unit | SMA |
| `sarpras.tbu` | Sarpras Unit | TBU |
| `sarpras.adm` | Sarpras Unit | ADM |

Kata sandi dapat diganti sebelum seeding dengan mengatur `SEEDED_USER_PASSWORD` pada `.env`. Seeder selalu menimpa kata sandi seluruh akun bawaan, sehingga menjalankan `db:seed` ulang akan mereset akun-akun tersebut ke kata sandi yang dikonfigurasi.

> Penting: segera ubah kata sandi awal pada lingkungan produksi dan jangan menyimpan kata sandi produksi di dokumentasi.

## Menyiapkan aplikasi

1. Salin `.env.example` menjadi `.env`, lalu isi koneksi database dan alamat aplikasi.
2. Pasang dependensi PHP dan JavaScript dengan `composer install` dan `npm install`.
3. Buat application key dengan `php artisan key:generate`.
4. Buat tabel beserta data awal dengan `php artisan migrate --seed`.
5. Buat tautan penyimpanan dengan `php artisan storage:link`.
6. Bangun aset antarmuka dengan `npm run build`.
7. Buka alamat aplikasi, lalu login memakai salah satu akun di atas.

Untuk database pengembangan yang boleh dikosongkan sepenuhnya, gunakan `php artisan migrate:fresh --seed`. Perintah tersebut menghapus seluruh data lama.

## Data yang dibuat seeder

Seeder aman dijalankan ulang dan menyediakan:

- 7 unit, 3 peran, dan 8 akun uji;
- 6 jenis barang dan 4 pengelola barang;
- 7 gedung, 20 lantai, dan 5 ruangan;
- 4 jenis inventaris dengan total 15 instance barang;
- 2 kendaraan yang dapat dipinjam;
- 1 contoh peminjaman yang sudah disetujui dan 1 contoh pengajuan yang masih menunggu keputusan.

Data contoh diberi awalan `[DEMO]` agar mudah dibedakan dari data operasional.

## Tata cara penggunaan

### Sarpras unit

1. Login memakai akun `sarpras.*` sesuai unit.
2. Buka menu **Ajukan Peminjaman**.
3. Isi nama kegiatan, waktu mulai dan selesai, serta keterangan.
4. Tambahkan satu atau beberapa kebutuhan: barang, ruangan, atau kendaraan.
5. Kirim pengajuan dan pantau statusnya pada daftar pengajuan unit.
6. Pengajuan yang sudah dikirim tidak dapat diubah agar hold aset tetap konsisten. Batalkan lalu buat pengajuan baru jika kebutuhan berubah.

Sarpras hanya dapat melihat dan mengelola pengajuan milik unitnya sendiri.

### Admin fasilitas

1. Login dengan akun `fasilitas`.
2. Buka daftar **Kegiatan / Peminjaman** untuk melihat kebutuhan yang diajukan.
3. Setujui atau tolak setiap kebutuhan. Saat menolak, isi alasan agar pemohon mengetahui penyebabnya.
4. Untuk kendaraan, tentukan pengemudi jika diperlukan.
5. Saat barang, ruangan, atau kendaraan diserahkan, ubah status menjadi **Sedang Dipakai**.
6. Setelah dikembalikan, tandai sebagai **Selesai / Dikembalikan** dan lengkapi checklist bila diperlukan.

### Administrator

Administrator memiliki akses penuh untuk mengelola pengguna, peran, unit, master inventaris, gedung, ruangan, kendaraan, dan seluruh transaksi. Gunakan akun ini untuk konfigurasi, bukan untuk operasional harian.

## Alur status singkat

`Draf` → `Menunggu Persetujuan` → `Disetujui` → `Sedang Dipakai` → `Selesai / Dikembalikan`

Pengajuan juga dapat berstatus **Disetujui Sebagian**, **Ditolak**, **Dibatalkan**, atau **Kedaluwarsa**. Hanya reservasi yang disetujui atau sedang dipakai yang ditampilkan pada jadwal publik.

### Aturan hold dan ketersediaan

- `submitted`, `approved`, `partially_approved`, dan `checked_out` mengurangi ketersediaan selama jadwalnya bertabrakan.
- `draft`, `rejected`, `returned`, `cancelled`, dan `expired` tidak mengurangi ketersediaan.
- Hold `submitted` berlaku 24 jam secara default. Ubah melalui `LOAN_HOLD_HOURS` pada `.env`.
- Jika batas hold lewat, hanya kebutuhan yang masih menunggu yang dilepaskan. Kebutuhan yang sudah disetujui tetap dicadangkan.
- Jumlah stok selalu diperiksa ulang dalam transaksi ketika pengajuan dikirim untuk mencegah overbooking.

Scheduler Laravel wajib aktif di produksi agar status dan notifikasi kedaluwarsa diperbarui setiap menit. Jalankan `php artisan schedule:run` melalui cron setiap menit, atau gunakan `php artisan schedule:work` pada process manager.

## Pemeriksaan sebelum produksi

- Ubah seluruh kata sandi awal.
- Set `APP_ENV=production`, `APP_DEBUG=false`, dan `APP_URL` yang benar.
- Pastikan database sudah dicadangkan sebelum migrasi.
- Konfigurasikan queue, mail, storage publik, serta Reverb/Chatify jika fitur percakapan real-time digunakan.
- Pastikan Laravel scheduler aktif untuk memproses hold peminjaman yang kedaluwarsa.
- Jalankan `php artisan test` dan `npm run build` sebelum rilis.
