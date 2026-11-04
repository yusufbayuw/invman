# Hierarki operasional penuh InvMan

Status: implementasi pada branch fitur. Jalur normal tidak berubah. Semua pegawai memiliki akun pribadi, tidak ada login bersama atau impersonasi.

## Kewenangan

- Admin dapat mengambil alih semua peran dalam organisasi.
- Fasilitas dapat mengambil alih semua peran di bawahnya, namun tidak fasilitas sebaya atau Admin.
- Koordinator / atasan dinamis dapat mengambil alih bawahan pada hierarki tim atau jabatan aktif dengan cakupan unit dan pengelola yang sesuai.
- Self-approval boleh dilakukan hanya lewat tindakan override eksplisit, dicatat sebagai self_approval.
- Setiap tindakan memiliki actor_id (akun login), subject_user_id (orang yang diganti), jenis objek, tahapan, alasan, perubahan status dan waktu, dalam tabel append-only operational_overrides.
- Alasan minimal 10 karakter dan maksimal 2000 karakter.
- Override tidak menonaktifkan validasi stok, ketersediaan, relasi kendaraan, keselamatan aset, checklist fisik, atau penanganan status terminal.

## UI

1. Detail Tiket: Override Persetujuan, Override Penugasan, Terima Tugas atas Nama Petugas, Input Hasil atas Nama Petugas, Override Verifikasi Pelapor, Override Pengesahan Akhir.
2. Pengajuan Multi-aset oleh Fasilitas: pilih pegawai Sarpras sebagai pemohon, masukkan alasan, data pemohon tidak berubah tetapi aktor tercatat sebagai penginput.
3. Peminjaman Serah-Terima: Override Konfirmasi Pengembalian jika checklist dan receipt ada. Kolom konfirmasi peminjam tetap null jika peminjam tidak benar-benar mengonfirmasi.
4. Detail tiket dan peminjaman: audit override dengan nama pelaku yang login dan pegawai yang diwakili.

## Batasan

- Tindakan normal tetap menolak self-approval; override memiliki form dan alasan sendiri. Keputusan terdahulu tidak ditimpa.
- Persetujuan estimasi membutuhkan versi terbaru meskipun approver menggantikan pencatat.
- Penutupan WO tidak otomatis melepas blokir aset: opsi aman secara fisik default NONAKTIF.
- Data tidak boleh masuk sebagai bukti konfirmasi pegawai lain; actor_id harus cocok akun login.
- Tanpa checklist pengembalian, override ditolak.
- Semua penggunaan akses global wajib dipantau secara berkala.

## Akun demo

InvManFullDemoSeeder membuat satu akun per peran/jabatan dengan nama pegawai fiktif berbeda. Hanya satu Sarpras, satu Fasilitas (Manager) dan satu Admin jika flag akun privileged diaktifkan. Staf, Supervisor, Koordinator, Teknisi, Pengelola Aset dan Pengemudi punya akun masing-masing, tidak mendapat role global.

Setelah PR lulus dan deploy, jalankan di terminal interaktif:

    php artisan migrate --force
    php artisan invman:seed-full-demo --include-privileged

Sandi harus kuat; perintah meminta konfirmasi backup dan akses global. Data demo hanya untuk latihan; lakukan prosedur reset sebelum GO LIVE.

## Pengujian sebelum penggabungan

    php artisan test --filter=HierarchyOverrideTest
    php artisan test --filter=InvManFullDemoSeederTest
    php artisan test

UAT browser harus membuktikan admin/fasilitas dapat melanjutkan tahap bawahan, user lain tidak bisa lintas unit, dua request paralel tidak membuat keputusan ganda, audit mempertahankan actor asli, dan safety hold tidak terlepas tanpa bukti fisik. Status production readiness terpisah dari fitur ini.
