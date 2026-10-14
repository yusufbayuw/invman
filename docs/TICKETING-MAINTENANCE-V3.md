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


## Hierarki tim teknis lapangan — fase penugasan

Menu **Layanan & Ticketing → Tim & Hierarki Petugas** memakai satu tabel/model `ticket_team_members` dan satu Resource Filament. Tidak ada role superuser baru, migrasi user, atau perubahan transaksi peminjaman. Satu pengguna hanya memiliki satu keanggotaan aktif/nonaktif: `team_name`, `team_type` (Lapangan/Pendukung), `position` (Koordinator/Teknisi), `supervisor_user_id` dan pengelola aset opsional.

1. Fasilitas/admin mengisi **koordinator** tim terlebih dahulu. Koordinator utama boleh tanpa atasan, teknisi wajib memiliki atasan aktif di tim yang sama. Koordinator bisa melapor kepada koordinator lain; siklus dan lintas tim ditolak.
2. Fasilitas/admin membuka tiket V3, memilih **Setujui & Tetapkan PIC** (koordinator atau teknisi), dan memberi prioritas SLA. Atasan **tidak** otomatis memperoleh hak approval atau final sign-off.
3. Jika PIC adalah koordinator, tombol **Delegasikan ke Tim** menampilkan hanya anggota aktif di bawah hierarki koordinator tersebut. Tindakan mewajibkan instruksi/alasan, terekam pada `ticket_events` dan membuat notifikasi database.
4. Delegasi hanya berlaku untuk pekerjaan **sudah disetujui** dengan status `triaged` dan **belum diterima**. Setelah petugas menekan **Terima & Mulai Kerja**, perpindahan PIC ditolak; tidak ada perubahan tersembunyi pada SLA, checklist, hasil kerja, verifikasi pelapor, maupun pengesahan fasilitas.
5. Teknisi tanpa role fasilitas/pengelola aset dapat membuka panel dan **hanya** melihat tiket yang ditugaskan kepadanya. Koordinator dapat melihat antrean bawahan lewat tab **Tugas Tim Saya**, tidak memperoleh akses atas seluruh unit. Nonaktifkan anggota tanpa menghapus histori lama.
6. Tiket V1 tetap berjalan; penugasan lama ke pengelola/fasilitas masih didukung dan opsi tim baru dibatasi sesuai cakupan pengelola. Data existing tidak perlu diubah.

Uji `php artisan test --filter=TicketTeamHierarchyTest`, lalu `php artisan test` dan pengujian manual di staging sebagai fasilitas, koordinator, petugas lapangan, pelapor, serta akun tim lain. Migrasi `php artisan migrate --force` bersifat create-only untuk tabel hierarki; tetap lakukan backup sebelum migrasi produksi.


## Workspace Teknisi Lapangan (Filament native)

Menu **Layanan & Ticketing → Tugas Lapangan** menyediakan antrean pribadi berbasis Filament Table, ringkasan statistik, pencarian, filter status, dan SLA. Setiap query dibatasi di server menggunakan `assigned_to = user login`; tidak ada opsi filter untuk melihat tugas pengguna lain. Rekaman lama yang telah selesai tetap bisa dilihat oleh PIC sesuai izin sebelumnya.

Alur petugas:

1. Fasilitas/admin menyetujui laporan dan menetapkan PIC, atau koordinator mendelegasikan tiket yang sudah disetujui.
2. PIC membuka **Tugas Lapangan → Terima & Mulai**, setelah itu status berpindah menjadi `in_progress`.
3. Gunakan **Bukti Foto Kerja** untuk mengunggah gambar JPG/PNG sebelum atau sesudah perbaikan. Gambar ditautkan sebagai komentar tiket dengan label tahap dan disimpan di disk privat. Maksimal tiga foto per aksi, ukuran maksimal 5 MB per file; tetap berlaku validator upload dan otorisasi viewer dari modul ticketing sebelumnya.
4. Untuk kendala material, gunakan **Menunggu Material** (alasan wajib). Saat material tersedia, gunakan **Lanjutkan**. Kedua aksi memanggil layanan maintenance yang sudah ada; SLA **tidak otomatis dijeda**.
5. Gunakan **Catat Hasil** untuk diagnosis, tindakan, daftar material, serta hasil akhir (sempurna/sementara/tidak dapat diperbaiki). Form yang sama dipakai halaman detail tiket agar konsisten.
6. Pelapor tetap memverifikasi hasil; pengesahan akhir dan pelepasan safety hold **hanya oleh fasilitas/admin**. Foto adalah bukti pendukung, bukan tanda persetujuan otomatis.

Keamanan tambahan: perubahan hierarki tidak boleh menghilangkan atasan dari anggota aktif. Petugas dengan keanggotaan dinonaktifkan tidak dapat menerima/melanjutkan/menyelesaikan tugas meskipun tiket lama masih tercatat atas namanya. Tim umum untuk tiket tanpa pengelola aset juga diperiksa berdasarkan unit agar tidak terjadi penugasan lintas unit. Tidak ada migrasi baru untuk workspace, perubahan skema tiket, maupun library UI tambahan.

Sesudah deploy, jalankan `php artisan optimize:clear`, pastikan aset Filament sudah dipublikasikan/dibangun sesuai instalasi, lalu uji dengan akun fasilitas, koordinator, teknisi lapangan dan pengguna unit lain. Gunakan `php artisan test --filter=TicketTeamHierarchyTest` sebagai tes regresi utama.
