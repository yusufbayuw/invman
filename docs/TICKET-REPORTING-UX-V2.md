# Laporan Kerusakan Sederhana (Ticketing UX V2)

Pelapor: pilih satu dari tujuh kategori besar, isi lokasi dan masalah, pilih urgensi bila tidak Biasa; foto opsional. Identitas, unit, waktu, nomor dan judul tiket otomatis. Tidak perlu mengetahui kode aset.

Jika pelapor mengenal inventaris, aktifkan sakelar dan cari berdasarkan nama/kode. Pencarian dilakukan di server dengan maksimal 25 hasil, boleh disaring berdasarkan ruangan, dan selalu dibatasi unit/pengelola pengguna. Ini menggantikan dropdown yang hanya memuat 250 aset.

Jika identitas inventaris tidak diketahui, buat tiket tanpa aset. Pengelola yang berwenang dapat memakai aksi Hubungkan Inventaris pada detail tiket, satu kali dan tercatat sebagai event asset_linked. Tidak ada perubahan stok/ketersediaan. Tiket tanpa aset dikelola fasilitas/admin.

QR jadwal ruangan yang sudah ada tetap membuka halaman jadwal publik. Tombol Laporkan Kerusakan Ruangan mengarahkan ke halaman tiket setelah login. Jika pemindai tidak berhak mengakses inventaris ruangan, hanya lokasi yang diprefill.

Kategori tiket sebelumnya tetap disimpan untuk tiket/otomatisasi lama. Kolom tickets.location dan tickets.reported_urgency nullable. Urgensi pelapor tidak mengubah prioritas internal sampai triase dilakukan.

Belum termasuk perbaikan terstruktur teknisi, persetujuan manajer, daftar material, PDF kerja, atau QR stiker barang baru. Role yang diizinkan membuat tiket tetap mengikuti otorisasi sebelumnya.

## Deployment dan pengujian

Lakukan backup lebih dulu, migrasi di staging, lalu verifikasi:
- php artisan migrate --force
- php artisan optimize:clear
- php artisan test --filter=TicketReportingUxTest
- php artisan test
