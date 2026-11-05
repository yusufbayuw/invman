# Kebijakan hold pengajuan peminjaman

Halaman **Pengaturan → Pengaturan Peminjaman** (hanya Admin) menyimpan kebijakan pada tabel `loan_settings`. Pilihan berlaku **untuk pengajuan baru**, bukan mengubah pengajuan yang sudah masuk.

| Parameter | Pilihan | Default |
| --- | --- | --- |
| Strategi pencadangan | Sejak pengajuan (`on_submission`), setelah persetujuan (`on_approval`) | Sejak pengajuan |
| Tenggat keputusan | 1–720 jam | 24 jam |
| Penghitungan tenggat | Jam kalender / jam kerja | Jam kalender |
| Hari dan jam kerja | Hari dalam minggu dan jendela HH:MM | Senin–Jumat, 08:00–17:00 |

## Contoh dan konsekuensi

1. **Sejak pengajuan:** tiga dari lima proyektor dikunci ketika diajukan untuk jadwal yang sama; permintaan lain hanya melihat dua tersedia. Ketika ditolak/dibatalkan/kedaluwarsa, tiga kembali tersedia. Reservasi yang sudah disetujui tetap aktif.
2. **Setelah persetujuan:** dua pengajuan dapat meminta aula yang sama pada jam yang sama, tetapi hanya pengajuan yang pertama disetujui akan memperoleh alokasi. Persetujuan kedua gagal dengan pesan bentrok; pengajuan tetap pending dan dapat ditolak.
3. **Jam kerja:** 2 jam yang dimulai Jumat 16:00 akan habis Senin 09:00 bila bekerja Senin–Jumat 08:00–17:00. Belum ada integrasi tanggal libur nasional.
4. **Deadline sebelum peminjaman:** tenggat keputusan selalu tidak lebih lambat dari `start_time`.

## Garansi kompatibilitas

- Migrasi menambahkan `hold_strategy` pada `g004_m008_activities` dengan default `on_submission` untuk data lama.
- Nilai strategi dan `hold_expires_at` disimpan pada setiap pengajuan ketika dikirim. Pergantian preferensi Admin tidak membatalkan alokasi lama.
- Status fisik `checked_out` dan `return_requested` selalu menghalangi ketersediaan hingga pengembalian, apa pun strategi.
- Persetujuan mode `on_approval` mengecek stok dan jadwal dalam transaksi dengan kunci pada aset. Jangan menulis `status=approved` lewat query manual yang melewati service.
- Scheduler `php artisan schedule:run` harus tetap berfungsi setiap menit untuk memproses `loans:expire-holds`. Jika terlambat, pengecekan ketersediaan tetap mengabaikan hold yang sudah lewat tenggat.
- Kebutuhan yang sudah disetujui pada pengajuan multi-aset tidak dilepas saat hanya kebutuhan lain yang kedaluwarsa.
- Otorisasi peminjam dan pengelola, audit status, serta alur serah-terima dua pihak tidak diubah.

## Deploy

Jalankan `php artisan migrate --force`, lalu `php artisan optimize:clear`. Jalankan regresi pada database uji dan pastikan scheduler berfungsi. Pertahankan strategi `on_submission` sampai pengujian selesai.
