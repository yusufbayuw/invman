# Peminjaman Cepat

Peminjaman Cepat adalah cara utama membuat **satu pengajuan untuk satu aset**
melalui Filament `/admin/peminjaman-cepat`. Alur multi-aset yang sudah ada
di `/admin/ajukan-peminjaman` tetap tersedia sebagai **Pengajuan Lengkap**,
termasuk draf, lampiran, dan beberapa jenis kebutuhan sekaligus.

## Alur pengguna

1. Pada dashboard pilih **Pinjam Barang**, **Pinjam Kendaraan**, atau **Pinjam Ruangan**.
   Jenis aset otomatis dipilih dari tombol yang diklik.
2. Pilih aset yang tersedia. Untuk barang jumlah otomatis 1 dan bisa diubah.
3. Gunakan jadwal bawaan satu jam atau ubah mulai dan selesai.
4. Isi **satu alasan singkat**, kemudian klik **Ajukan Sekarang**.

Pemohon dan unit otomatis dari akun, tidak ada field pengemudi maupun kenek.
Bagi bus, kru tetap ditentukan secara internal oleh pengelola ketika menyetujui
pengajuan. Untuk kendaraan sewa, aturan kru bawaan juga tidak berubah.

### Jumlah input pada kondisi normal

- Barang: pilih barang dan isi alasan singkat (2 input manual). Jumlah=1,
  jenis=barang, mulai/selesai memiliki nilai default.
- Kendaraan: pilih kendaraan dan isi alasan singkat (2 input manual). Jenis
  kendaraan dan jadwal sudah tersedia lewat tautan dashboard.
- Ruangan: pilih ruangan dan isi alasan singkat (2 input manual).
- Tanggal/jam dan jumlah barang dapat diubah, jika dibutuhkan.

Ini adalah hitungan field yang perlu diisi (bukan jaminan jumlah klik browser
di semua perangkat). Pilihan tanggal memakai date-time picker.

## Validasi dan batasan keamanan

- Form memanggil `LoanRequestService::submit()` yang sama seperti pengajuan
  lengkap, sehingga pemeriksaan unit, jenis aset, stok/jadwal, penguncian
  transaksi, masa hold, histori dan notifikasi tetap berlaku.
- Alasan singkat dipetakan ke `name` **dan** `description` untuk menjaga
  format kegiatan lama tanpa meminta dua informasi berulang.
- Payload tidak diteruskan mentah ke database. Service membangun pemohon dan
  unit dari user terotentikasi, status awal ditetapkan sistem.
- Pengguna tanpa hak mengajukan pinjaman dilarang masuk halaman, bahkan
  lewat URL langsung.
- Jika ketersediaan berubah sejak dropdown dibuka, pengajuan ditolak oleh
  pemeriksaan server pada waktu submit dan tidak membuat pemesanan ganda.
- Halaman ini tidak memiliki fitur draf atau multi-aset; untuk skenario
  tersebut gunakan **Pengajuan Lengkap**.

## Regresi

```bash
php artisan test --filter=QuickLoanRequestTest
php artisan test
```

Test mencakup barang, ruangan, kendaraan tanpa memilih kru, validasi duplikasi
aset, akses tidak sah, pemalsuan user/unit/status, tanggal invalid,
navigasi dashboard dan keberadaan jalur pengajuan lengkap.
