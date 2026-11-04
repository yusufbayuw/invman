# Demo matriks peran InvMan: TK, SD, SMP, SMA, Universitas

Seeder khusus: `InvManRoleMatrixDemoSeeder` (TIDAK dimasukkan ke `DatabaseSeeder`).

## Tujuan dan cakupan

Bertujuan mengeksplorasi alur lintas unit, jabatan dinamis Work Order, dan pengelola barang berbasis kelompok. **Bukan konfigurasi operasional final.**

- Lima unit contoh terisolasi: `[DEMO-ORG] TK`, SD, SMP, SMA, Universitas.
- Lima akun `invman.orgdemo.sarpras.{tk|sd|smp|sma|universitas}` dengan role existing `sarpras`, masing-masing tepat satu unit.
- Tiga akun `invman.orgdemo.fasilitas.{staf|supervisor|manager}` dengan role existing `fasilitas` dan penugasan jabatan global.
- Dua akun `invman.orgdemo.pengelola.{it|perpustakaan}`, terhubung ke pengelola barang `[DEMO-ORG] IT` dan `[DEMO-ORG] Perpustakaan`, tanpa role fasilitas.
- Tiap unit memiliki kursi lipat (4), laptop (2), buku referensi (3), total 15 master barang dan 45 barang satuan.
- Satu draf peminjaman campuran laptop + buku di tiap unit (5 draf); semua masih `draft` dan **belum memblokir stok**.
- Lima workflow Work Order per unit, semuanya `draft`. TK: staf + pengesahan manager. SD/SMP/SMA: staf + supervisor + pengesahan manager. Universitas: sama dengan tambahan checkpoint estimasi pada supervisor.
- Jabatan `[DEMO-ORG]` terpisah; penugasan pengelola IT dan perpustakaan juga scoped berdasarkan kelompok inventaris.

## Pengamanan

**Jangan jalankan di production.** Seeder menolak production dan mengharuskan `SEED_DEMO_USERS=true` serta sandi demo kuat. Wajib memakai database pengujian terpisah. Tidak ada perintah otomatis untuk membersihkan data atau mengubah data asli.

Jalankan dari root aplikasi **setelah migration dan RoleSeeder**:

```bash
# .env pada lingkungan development/staging yang TERISOLASI:
# SEED_DEMO_USERS=true
# SEEDED_USER_PASSWORD=<masukkan sandi uji kuat melalui pengelolaan secret>

php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=InvManRoleMatrixDemoSeeder
php artisan test --filter=InvManRoleMatrixDemoSeederTest
```

Jangan menaruh password sungguhan pada dokumentasi, Git, atau percakapan publik. Akun yang sudah ada tidak di-reset. Identitas yang bertabrakan menyebabkan seeding ditolak. Pemanggilan ulang tidak menambah duplikasi. Seeder tidak mempublikasikan approval workflow, mengirim pinjaman, atau menjalankan transaksi riil. Karena PHP observer bisa membuat data turunan, gunakan hanya database disposable.

**Perhatian:** semua pengguna dengan role existing `fasilitas` saat ini memiliki akses fasilitas global melalui `User::isFacility()`. Jabatan `Staf Fasilitas`, `Supervisor Fasilitas`, dan `Manager Fasilitas` membedakan Work Order, **bukan** memisahkan akses ke seluruh modul. Jika diperlukan hierarki RBAC ketat, lakukan redesign policy + test terpisah. Jangan menganggap role `fasilitas` saat ini least-privilege.

## Eksplorasi yang disarankan

1. **Isolasi unit**: login Sarpras TK dan pastikan tidak bisa melihat/mengubah pengajuan SD, SMP, SMA, dan Universitas; coba URL langsung dan payload Livewire yang dimodifikasi.
2. **Kelompok pengelola**: login pengelola IT, periksa hanya kebutuhan/jenis barang IT yang dapat dikelola; pengelola perpustakaan hanya buku; kebutuhan lain dalam kegiatan campuran tidak boleh bisa diputuskan olehnya.
3. **Stok dan jadwal**: ajukan 2 unit laptop pada jam yang sama, lalu pengajuan kedua; pastikan tidak ada overbooking. Uji peminjaman multi-unit, partial return, pengembalian terlambat dan checklist dua pihak.
4. **Workflow Kerusakan**: tinjau alur TK dua checkpoint vs universitas yang mensyaratkan estimasi. Sebelum UAT, administrator yang sah perlu mempublikasikan workflow secara sadar; jangan otomatis mengubah draft.
5. **Hierarki**: cabut penugasan, nonaktifkan akun, ganti supervisor, coba self-approval dan approval out-of-order; sistem harus memblokir.
6. **Global vs scoped**: audit khusus apakah akun staf, supervisor dan manager fasilitas perlu menu/aksi berbeda. Seeder sengaja tidak menyamarkan akses global existing.
7. **Audit trail**: inspeksi histori approval, serah-terima, perubahan status, alasan override, sumber notifikasi, dan akses ke dokumen.

## Kriteria pengujian

`InvManRoleMatrixDemoSeederTest` memeriksa guard opt-in/production, jumlah unit/pengguna/inventaris/draf, scoped membership IT vs Perpustakaan, idempotensi, akun existing tidak diubah, serta penolakan collision. Ini belum menggantikan integrasi penuh CI MySQL dan browser UAT.

**Status:** branch implementasi tersedia sebagai PR review; tidak langsung merge ke main dan belum dinyatakan siap produksi.
