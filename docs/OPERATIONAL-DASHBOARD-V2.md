# Dashboard Operasional V2

Dashboard `/admin` kini menampilkan panel **Prioritas Operasional V2** di atas
Tindakan Cepat. Panel mempertahankan kartu statistik, grafik, kalender, dan
tabel pengajuan sebelumnya.

## Kategori langsung dari reservasi

- **Menunggu Persetujuan**: status `submitted`, termasuk pengajuan yang
  masih tampak submitted meskipun hold-nya telah habis. Tombol tindak lanjut
  hanya menawarkan review jika memang diizinkan service.
- **Siap Diserahkan**: `approved` tanpa bukti serah-terima keluar.
- **Menunggu Penerimaan**: `approved` dengan tanda tangan pengelola pada
  bukti penyerahan keluar, namun belum selesai dikonfirmasi peminjam.
- **Sedang Dipakai**: `checked_out`.
- **Terlambat**: `checked_out` atau `return_requested` yang melewati
  jadwal akhir.
- **Konfirmasi Pengembalian**: `return_requested`, termasuk pengembalian
  yang menunggu pihak kedua.

Angka kategori **tidak dijumlahkan**: aset yang sedang dipakai dan terlambat
termasuk kedua kategori, begitu pula pengembalian terlambat. Daftar Semua
Prioritas mengurutkan keterlambatan terlebih dahulu, kemudian pengembalian,
penerimaan, persetujuan, kesiapan serah, dan peminjaman aktif. Setiap
reservasi hanya muncul satu kali di daftar gabungan. Penghitungan angka
menggunakan query database penuh; tabel hanya menampilkan 12 yang teratas.

## Isolasi akses dan filter

Semua jenis aset (barang, ruangan, kendaraan) memakai
`LoanVisibility::reservations`, sehingga:

- **Unit/Sarpras** hanya melihat peminjaman unitnya (serta aset unit lain
  yang secara eksplisit mereka kelola, jika berperan ganda).
- **Pengelola aset** hanya melihat peminjaman yang asetnya berada pada grup
  pengelolaan miliknya.
- **Fasilitas/Admin** dapat melihat seluruh unit dan menyaring unit dari
  filter dasbor.
- Akun di luar peran di atas tidak melihat panel atau data apa pun.
- Teks tombol tindak lanjut mengikuti izin service saat ini, misalnya
  hanya unit pemohon dapat mengonfirmasi penerimaan awal.

**Penting:** filter historis periode/status tidak diterapkan pada panel
Prioritas Operasional karena pinjaman dari bulan lalu yang belum kembali
harus tetap tampak. Filter unit fasilitas diterapkan pada seluruh angka dan
daftar; kontrol ini tidak dapat memperluas akses peran lain.

## Operasional

Panel memperbarui dirinya setiap 30 detik. Klik kategori untuk melihat
daftar dan klik tindak lanjut agar menuju halaman transaksi yang sudah
memiliki pemeriksaan izin dan aturan bisnis. Tidak ada aksi status langsung,
bypass approval, migrasi, atau tabel database baru pada fitur ini.

Jalankan:

```bash
php artisan test --filter=OperationalPriorityDashboardTest
php artisan test
```

Setelah deploy, uji akun unit, pengelola, fasilitas dan akun tanpa akses
dengan dua unit terpisah. Pastikan juga transaksi dari bulan lalu yang masih
dipakai tetap ditandai terlambat. Daftar untuk status masa lalu dan laporan
lengkap tetap menggunakan menu **Peminjaman Saya / Rekapan Penggunaan**.
