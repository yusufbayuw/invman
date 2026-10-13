# Ticketing V3 — Perawatan, SLA, dan Kelayakan Aset

## Cakupan
Penambahan **aditif**, tanpa migrasi paksa tiket lama: tiket yang dibuat dari kategori pelaporan aktif dan tiket otomatis dari kerusakan saat pengembalian menggunakan workflow maintenance V3. Tiket legacy tetap memakai transition V1.

1. Pelapor: laporan sederhana dan pilihan aset opsional, identitas/jam otomatis.
2. Fasilitas/admin: **Setujui & Tugaskan** memilih petugas dan prioritas SLA. Catatan persetujuan/aktor/waktu diaudit.
3. Teknisi terpilih: **Terima & Mulai Kerja**, bisa **Menunggu Material** / **Lanjutkan**, catat diagnosis, tindakan, material+qty, hasil **Selesai Sempurna / Sementara / Tidak Dapat Diperbaiki**.
4. Pelapor asli: **Verifikasi Hasil**, menerima atau mengembalikan pekerjaan ke teknisi dengan alasan. Petugas tidak bisa menyatakan hasilnya sendiri disetujui.
5. Manajer fasilitas: **Sahkan Penyelesaian**, memilih layak pakai hanya untuk perbaikan sempurna dengan verifikasi pelapor yang sudah tercatat. Jika perbaikan sementara atau gagal, administratif dapat ditutup tetapi aset tetap ditahan.
6. Dokumen A4 **Form Perawatan PDF** mengambil data dari audit, tidak mengarang tanda tangan/waktu yang belum tercatat. Hanya orang yang berhak melihat tiket dapat mengunduh.

## Penahanan & pelepasan aset
Asset yang ditautkan pada tiket perawatan memperoleh **safety hold**. Peminjaman barang/ruangan/kendaraan memeriksa hold baik di daftar pilihan maupun validasi transaksi, termasuk pemilihan barang satuan fisik.
Persetujuan kelayakan saat akhir secara eksplisit melepas hold. Inventaris yang sebelumnya dinonaktifkan manual tidak diaktifkan otomatis; hanya aset yang berstatus `perlu_perbaikan` oleh proses pengembalian lama yang bisa diaktifkan lagi bila tidak ada hold lain. Penahanan berbasis tiket tidak menimpa status/master peminjaman existing.

## SLA
Konfigurasi awal `config/ticket_sla.php`: jam Senin-Jumat 08.00–17.00 zona Asia/Jakarta, target per prioritas, daftar hari libur yang bisa dikelola melalui konfigurasi. Ini **target awal** yang harus disahkan organisasi sebelum operasional. Belum ada jeda SLA saat menunggu material; masa tunggu tetap berjalan. Catatan respons dibuat saat manajer menyetujui; batas penyelesaian dipenuhi saat teknisi menyerahkan hasil kerja.

Cron aplikasi `tickets:escalate-sla` dijadwalkan **setiap jam** bersama scheduler Laravel existing, dengan validasi lisensi dan withoutOverlapping. Eskalasi response/resolution dikirim sekali per tenggat ke pengelola, fasilitas/admin, teknisi; tercatat append-only di audit. Pastikan `php artisan schedule:run` berjalan setiap menit pada server (queue worker saja tidak menjalankan scheduler).

## Deployment
Backup dan staging terlebih dahulu:
```bash
php artisan migrate --force
php artisan optimize:clear
php artisan test --filter=TicketMaintenanceV3Test
php artisan test
php artisan schedule:list
```

Uji semua role, uji fisik penyerahan/perbaikan/penutupan, PDF A4, serta pengembalian aset rusak. Tidak memerlukan paket Composer baru. PDF bukan tanda tangan digital tersertifikasi.

**Belum masuk tahap ini:** preventive maintenance terjadwal, dashboard kinerja teknisi, AI; kebijakan penggantian aset yang tidak dapat diperbaiki perlu otorisasi tersendiri.
