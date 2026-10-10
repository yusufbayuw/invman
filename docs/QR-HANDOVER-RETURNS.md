# QR Serah Terima & Pengembalian

InvMan menyediakan halaman Filament `/admin/serah-terima` untuk setiap
reservasi barang, ruangan atau kendaraan yang dapat diakses dari
**Peminjaman Saya → QR Serah Terima** (status disetujui, dipakai, menunggu
pengembalian atau selesai).

## Alur

1. Pengelola atau unit pemohon membuka reservasi, lalu memperlihatkan QR
   pada perangkatnya. QR dapat dipindai dengan kamera HP lain.
2. QR mengarah ke **tautan bertanda tangan yang berlaku 30 menit**.
   Pemindai harus login dan menjadi pemohon dari unit terkait atau pengelola
   aset tersebut. Memindai QR **tidak mengubah status apa pun**.
3. Pada status Disetujui, pengelola dapat memilih **Pinjamkan** setelah
   konfirmasi. Validasi kendaraan, pengemudi, bus/kenek, dan audit tetap
   menggunakan `LoanRequestService`.
4. Pada status Dipakai, pemohon atau pengelola mengisi checklist kondisi,
   foto/bukti bila diperlukan, lalu **Catat Pengembalian**. Untuk barang,
   setiap unit fisik dialokasikan dan dicek secara terpisah.
5. Pengembalian berstatus Menunggu Konfirmasi. **Pihak kedua** (pengelola
   jika pemohon mengajukan, atau unit pemohon jika pengelola yang menerima)
   harus mengonfirmasi agar status Selesai. Satu akun tidak dapat menjadi
   kedua pihak. Nomor bukti serah-terima, aktor, waktu dan histori disimpan.

## Keamanan

- QR adalah **deep link sementara**, bukan bukti serah-terima dan bukan
  kredensial. Tidak ada auto-checkout, auto-return, atau bypass approval.
- Tautan scan memakai Laravel `temporarySignedRoute`, middleware
  `auth`, `signed`, throttle, dan cek izin terhadap reservasi yang sama.
- Halaman operasional juga memeriksa role/unit/pengelola sebelum merender
  data. Setiap aksi kembali memakai service transaksi dan validasi status,
  mencegah penggunaan QR lama untuk mengubah pengajuan yang telah selesai.
- Gambar PNG QR hanya dibuat untuk pengguna yang berwenang di halaman
  Filament (data URI), tanpa file QR publik di storage.
- Tidak menambah kolom atau mengubah histori DB; seluruh data receipt/checklist
  memakai tabel yang sudah tersedia dari migrasi sebelumnya.

## Pengujian dan operasional

```bash
php artisan test --filter=QrHandoverFlowTest
php artisan test
```

Pengujian meliputi tautan valid/expired/diubah, akun asing, serah-terima
oleh pengelola, dua arah pengembalian dengan konfirmasi silang, dan aksi QR
dari daftar unit. Sebelum pemakaian lapangan, pastikan HTTPS dan jam server
benar, lalu lakukan uji scan kamera HP serta upload foto di staging.

Lakukan backup dan deploy seperti biasa; **fitur ini tidak memerlukan
migrasi database baru**.
