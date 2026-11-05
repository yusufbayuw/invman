# Demo matriks peran InvMan: TK, SD, SMP, SMA, Universitas

Seeder khusus: `InvManRoleMatrixDemoSeeder` (TIDAK dimasukkan ke `DatabaseSeeder`).

## Tujuan dan cakupan

Bertujuan mengeksplorasi alur lintas unit, jabatan dinamis Work Order, dan pengelola barang berbasis kelompok. **Bukan konfigurasi operasional final.**

- Lima unit contoh terisolasi: `[DEMO-ORG] TK`, SD, SMP, SMA, Universitas.
- **Akun `admin`** dengan password `password`, menggunakan **role existing `admin`** (super-admin Filament Shield) untuk mengakses seluruh menu dan kewenangan yang diizinkan sistem. Akun ini dibuat hanya untuk demo local/testing; tidak mengubah akun admin yang sudah ada.
- Lima akun `sarpras.tk`, `sarpras.sd`, `sarpras.smp`, `sarpras.sma`, dan `sarpras.univ` dengan role existing `sarpras`, masing-masing tepat satu unit.
- Tiga akun `staf.fas`, `spv.fas`, dan `man.fas` dengan role existing `fasilitas` dan penugasan jabatan global.
- Satu akun `teknisi` **tanpa role fasilitas global**, dengan jabatan Teknisi. Atasan langsung adalah Staf Fasilitas; staf merupakan lead tim lapangan `[DEMO-ORG] Tim Teknis Fasilitas`. Hierarki: Manager → Supervisor → Staf → Teknisi.
- Dua akun `it` dan `perpus`, terhubung ke pengelola barang `[DEMO-ORG] IT` dan `[DEMO-ORG] Perpustakaan`, tanpa role fasilitas.
- Akun **`driver`** dengan data SIM A demonstrasi dan kendaraan contoh, tanpa role `fasilitas` global. Akun ini adalah identitas pengemudi, bukan admin peminjaman.
- Tiap unit memiliki kursi lipat (4), laptop (2), buku referensi (3), total 15 master barang dan 45 barang satuan.
- Satu draf peminjaman campuran laptop + buku di tiap unit (5 draf); semua masih `draft` dan **belum memblokir stok**.
- Lima workflow Work Order per unit, semuanya `draft`, dengan jabatan **Teknisi** sebagai target dispatch. TK: staf + pengesahan manager. SD/SMP/SMA: staf + supervisor + pengesahan manager. Universitas: sama dengan tambahan checkpoint estimasi pada supervisor. Teknisi bukan approver final.
- Jabatan `[DEMO-ORG]` terpisah; penugasan pengelola IT dan perpustakaan juga scoped berdasarkan kelompok inventaris.

## Pengamanan

**Gunakan hanya pada lingkungan local/testing terisolasi.** Seeder menolak production maupun staging dan mengharuskan `SEED_DEMO_USERS=true`. Semua akun menggunakan password demo yang sama, yaitu `password` (disimpan sebagai hash). Jangan menjalankan seeder di server yang dapat diakses publik, termasuk staging. Tidak ada perintah otomatis untuk membersihkan data atau mengubah data asli.

Jalankan dari root aplikasi **setelah migration dan RoleSeeder**:

```bash
# .env pada lingkungan LOCAL atau TESTING terisolasi saja:
# APP_ENV=local (atau testing)
# SEED_DEMO_USERS=true
# Password tetap untuk seluruh akun demo: password

php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=InvManRoleMatrixDemoSeeder
php artisan test --filter=InvManRoleMatrixDemoSeederTest
```

Username dibuat singkat agar mudah login untuk UAT lokal. Password `password` **hanya layak untuk database disposable yang tidak dapat diakses publik**. Akun yang sudah ada tidak di-reset. Identitas yang bertabrakan menyebabkan seeding ditolak. Pemanggilan ulang tidak menambah duplikasi. Seeder tidak mempublikasikan approval workflow, mengirim pinjaman, atau menjalankan transaksi riil. Karena PHP observer bisa membuat data turunan, gunakan hanya database disposable.

**Perhatian:** akun `admin` menggunakan role super-admin yang memiliki kewenangan lintas modul; password `password` tidak boleh digunakan di production atau sistem yang dapat diakses publik. Bila username `admin` telah dimiliki akun asli, seeder berhenti dan tidak mengubah sandi/role akun tersebut. Semua pengguna dengan role existing `fasilitas` saat ini memiliki akses fasilitas global melalui `User::isFacility()`. Jabatan `Staf Fasilitas`, `Supervisor Fasilitas`, dan `Manager Fasilitas` membedakan Work Order, **bukan** memisahkan akses ke seluruh modul. Jika diperlukan hierarki RBAC ketat, lakukan redesign policy + test terpisah. Jangan menganggap role `fasilitas` saat ini least-privilege.

## Skenario operasional tambahan (opt-in)

Seeder `InvManBusinessScenarioSeeder` adalah pendamping `InvManRoleMatrixDemoSeeder`, dan tidak berjalan otomatis. Setelah seeder utama:

```bash
php artisan db:seed --class=InvManBusinessScenarioSeeder
php artisan test --filter=InvManBusinessScenarioSeederTest
```

Seeder pendamping menambah 5 kendaraan (termasuk bus dengan kebutuhan kenek), 10 ruangan pada 5 gedung/lantai, 10 draf peminjaman ruangan/kendaraan, serta 9 pengajuan yang mencakup `submitted`, `approved`, `rejected`, `partially_approved`, `cancelled`, `checked_out`, `return_requested`, dan `returned`. Persetujuan dilakukan melalui `LoanRequestService`; serah-terima awal melalui `LoanCheckoutService` oleh pengelola dan dikonfirmasi peminjam; pengembalian juga memerlukan konfirmasi pihak kedua.

Terdapat 7 tiket laporan kerusakan melalui `TicketService` dan `TicketMaintenanceService`: `open`, `triaged`, `in_progress`, `waiting_parts`, `resolved`, `closed`, dan `cancelled`. Semua contoh menggunakan identitas `[DEMO-ORG]` dan `source_key` tersendiri untuk idempotensi. Workflow yang dikonfigurasi seeder utama tetap DRAFT, sehingga tiket menggunakan fallback kewenangan fasilitas existing; **ini belum memverifikasi approval Work Order bertingkat yang dipublikasikan**.

**Batas cakupan:** bukan matriks lengkap seluruh pengecualian. Belum ada dataset khusus stok habis/overbooking, kerusakan saat pengembalian, pergantian pengemudi di tengah perjalanan, pembayaran/tarif, serta approval Work Order versi published dengan seluruh riwayat keputusan. Pengujian atas skenario negatif dilakukan dengan automated test terpisah dan tidak boleh dibuat sebagai keadaan database yang melanggar invariant.

Seeder pendamping hanya dapat dijalankan di local/testing terisolasi (`SEED_DEMO_USERS=true`). Transaksi terkirim memicu hold dan notifikasi aplikasi sebagaimana proses normal. Menjalankannya di production tidak diizinkan karena risiko transaksi demo dan akun berkredensial publik; untuk UAT dengan konfigurasi production, gunakan salinan database terisolasi tanpa data pengguna asli.

## Eksplorasi yang disarankan

1. **Isolasi unit**: login Sarpras TK dan pastikan tidak bisa melihat/mengubah pengajuan SD, SMP, SMA, dan Universitas; coba URL langsung dan payload Livewire yang dimodifikasi.
2. **Kelompok pengelola**: login pengelola IT, periksa hanya kebutuhan/jenis barang IT yang dapat dikelola; pengelola perpustakaan hanya buku; kebutuhan lain dalam kegiatan campuran tidak boleh bisa diputuskan olehnya.
3. **Stok dan jadwal**: ajukan 2 unit laptop pada jam yang sama, lalu pengajuan kedua; pastikan tidak ada overbooking. Uji peminjaman multi-unit, partial return, pengembalian terlambat dan checklist dua pihak.
4. **Workflow Kerusakan**: tinjau alur TK dua checkpoint vs universitas yang mensyaratkan estimasi. Sebelum UAT, administrator yang sah perlu mempublikasikan workflow secara sadar; jangan otomatis mengubah draft.
5. **Hierarki**: cabut penugasan, nonaktifkan akun, ganti supervisor, coba self-approval dan approval out-of-order; sistem harus memblokir. Periksa distribusi pekerjaan dari Staf ke Teknisi, kemampuan teknisi menerima/menyelesaikan tugas, dan visibilitas tiket tim.
6. **Global vs scoped**: audit khusus apakah akun staf, supervisor dan manager fasilitas perlu menu/aksi berbeda. Seeder sengaja tidak menyamarkan akses global existing.
7. **Audit trail**: inspeksi histori approval, serah-terima, perubahan status, alasan override, sumber notifikasi, dan akses ke dokumen.

## Kriteria pengujian

`InvManRoleMatrixDemoSeederTest` dan `InvManBusinessScenarioSeederTest` memeriksa guard opt-in/production, jumlah 5 unit/13 pengguna/inventaris/draf, hubungan atasan langsung dan keanggotaan tim teknisi, scoped membership IT vs Perpustakaan, idempotensi, akun existing tidak diubah, serta penolakan collision. Ini belum menggantikan integrasi penuh CI MySQL dan browser UAT.

**Status:** branch implementasi tersedia sebagai PR review; tidak langsung merge ke main dan belum dinyatakan siap produksi.

## Catatan teknisi

Akun teknisi tidak mendapat akses `fasilitas` global. Work Order assignment berjabatan `Teknisi` bersifat global untuk keperluan demo lintas lima unit. Keanggotaan `TicketTeamMember` bersifat tim lapangan dengan Staf Fasilitas sebagai lead dan Teknisi sebagai bawahannya. Workflow tetap DRAFT; setelah dipublikasikan oleh admin yang berhak, lakukan uji dispatch, akses tiket, dan kewenangan teknisi sesuai kondisi sesungguhnya.
