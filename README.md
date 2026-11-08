# InvMan (LIST) — Inventaris, Peminjaman & Ticketing

[![InvMan Quality Guard](https://github.com/yusufbayuw/invman/actions/workflows/ci.yml/badge.svg)](https://github.com/yusufbayuw/invman/actions/workflows/ci.yml)

**InvMan / LIST** adalah aplikasi pengelolaan barang inventaris, ruangan, kendaraan, peminjaman, serah-terima, dan laporan kerusakan/perawatan untuk organisasi multiunit. Dibangun dengan **Laravel 12** dan **Filament 3**, dengan akses berdasarkan peran, unit, serta pengelola aset.

> **Status dokumentasi:** README ini menjelaskan fitur yang tersedia di cabang `main`, bukan janji roadmap. Untuk perubahan workflow dan prosedur migrasi terperinci, ikuti dokumen pada bagian [Dokumentasi lanjutan](#dokumentasi-lanjutan).

## Akses aplikasi

| Kebutuhan | URL relatif | Keterangan |
| --- | --- | --- |
| Jadwal peminjaman publik | `/` | Pratinjau tersensor tanpa login; rincian tersedia setelah masuk |
| Login pengguna | `/admin/login` | Autentikasi Filament |
| Dashboard operasional | `/admin` | Memerlukan akun dan izin yang sesuai |
| Peminjaman Cepat | `/admin/peminjaman-cepat` | Pengajuan satu jenis aset secara ringkas |
| Pengajuan Lengkap | `/admin/ajukan-peminjaman` | Draf dan kebutuhan multi-aset |
| Serah-terima QR | `/admin/serah-terima` | Dibuka melalui transaksi yang diizinkan |
| Tiket | `/admin/tiket` | Laporan kerusakan dan penanganannya |

Alamat final mengikuti nilai `APP_URL`. URL internal tetap tunduk pada autentikasi, izin, dan konfigurasi lisensi.

## Fitur utama

| Modul | Kemampuan |
| --- | --- |
| **Inventaris dan fasilitas** | Master barang, jenis barang, unit fisik/nomor inventaris, pengelola, unit organisasi, gedung, lantai, dan ruangan; relasi serta histori aset |
| **Kendaraan** | Master kendaraan, pengemudi default, penugasan pengemudi/kenek bus, pergantian personel beserta alasan dan histori; aturan berbeda untuk kendaraan sewa |
| **Peminjaman** | Peminjaman Cepat (satu aset), Pengajuan Lengkap (multi-aset), kegiatan master, ketersediaan dan pencegahan benturan jadwal/stok, persetujuan/penolakan, hold kedaluwarsa |
| **Serah-terima & pengembalian** | Checklist kondisi, bukti foto opsional, QR bertanda tangan sementara, konfirmasi dua pihak, pengecualian tercatat, receipt `OUT-`/`RTN-`, dan PDF bukti |
| **Ticketing & maintenance** | Pelaporan sederhana, kategori aktif, QR laporan, lampiran privat, triase/penugasan teknisi, diagnosis/material, verifikasi pelapor, pengesahan fasilitas, PDF perawatan |
| **Kelayakan & SLA** | Safety hold untuk aset bermasalah; tenggat berbasis kalender kerja, eskalasi SLA, dan histori audit |
| **Operasional** | Dashboard prioritas, statistik, grafik, kalender, rekap penggunaan, notifikasi database; integrasi push, pesan, dan realtime sesuai konfigurasi |

Tiket historis V1 tetap dipertahankan; alur maintenance V3 digunakan untuk laporan yang memenuhi ketentuan kategori terbaru. QR **bukan** tanda tangan atau otorisasi otomatis: pemindai tetap harus login dan berhak melihat transaksi.

### Alur penggunaan singkat

1. **Pemohon/unit** memilih **Peminjaman Cepat** untuk satu aset atau **Pengajuan Lengkap** untuk beberapa aset. Peminjaman dapat ditautkan ke **Kegiatan Master** agar penggunaan lintas transaksi terlacak.
2. **Pengelola/fasilitas** meninjau kebutuhan dan menyetujui atau menolak. Jadwal, stok, izin dan personel kendaraan diperiksa kembali oleh sistem.
3. **Pengelola** mencatat kondisi awal dan memulai penyerahan. **Peminjam** yang berbeda mengonfirmasi penerimaan; baru kemudian transaksi normal berstatus sedang dipakai.
4. Saat kembali, kondisi fisik dicatat dan **pihak kedua** memverifikasi pengembalian. Bila ditemukan kerusakan, laporan/tiket dapat terhubung ke aset dan menahan kelayakan peminjaman berikutnya.
5. Pengelola dan pihak berwenang dapat menelusuri histori, status, bukti PDF, tiket dan rekap sesuai cakupan aksesnya.

Penyerahan khusus hanya tersedia sesuai izin dan memerlukan alasan; bukti tidak akan memalsukan konfirmasi pihak kedua. Pada kendaraan operasional, penugasan pengemudi wajib; bus juga memerlukan kenek internal. Pemohon **tidak** memilih kru.

## Teknologi dan persyaratan

- **Backend:** PHP **8.2+**, Laravel **12**, Composer 2.
- **Panel:** Filament **3**, Filament Shield, Laravel Livewire.
- **Frontend:** Vite, Tailwind CSS 3, **Node.js 22** dan npm (versi Node yang dipakai CI).
- **Database:** MySQL **8.0** untuk target produksi yang diuji CI; SQLite digunakan dalam pengujian dan dapat dipakai untuk pengembangan.
- **Layanan aplikasi:** queue worker, Laravel scheduler, mail/push/realtime **jika** fitur tersebut diaktifkan. Ekstensi PHP mengikuti pemeriksaan `composer install` (termasuk driver database dan ekstensi yang diperlukan paket).
- **Lisensi:** konfigurasi dan token lisensi offline yang cocok dengan domain/IP `APP_URL` — lihat [panduan lisensi](docs/LISENSI.md).

Web server produksi harus mengarahkan document root ke direktori **`public/`**, bukan root proyek. Gunakan HTTPS dan PHP-FPM versi yang sesuai.

## Instalasi baru (pengembangan)

Contoh ini untuk basis data **baru**, bukan untuk mengulang instalasi produksi yang sudah memiliki data.

```bash
git clone https://github.com/yusufbayuw/invman.git
cd invman
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env` sebelum migrasi, minimal:

- `APP_URL`, `APP_ENV`, `APP_DEBUG`, `APP_TIMEZONE`.
- `DB_CONNECTION` dan `DB_*` jika memakai MySQL; contoh bawaan memakai SQLite.
- `LICENSE_ALLOWED_DOMAINS`, `LICENSE_ALLOWED_HOST_IPS`, dan `LICENSE_TOKEN` **yang sah untuk host tersebut**.
- `QUEUE_CONNECTION`, `MAIL_*`, serta konfigurasi broadcast/push jika diperlukan.

Lanjutkan:

```bash
php artisan migrate
# Opsional hanya di database pengembangan baru:
php artisan db:seed
npm ci
npm run build
php artisan license:status
php artisan serve
```

Buka `/admin/login` pada host yang cocok dengan lisensi. `license:status` harus menunjukkan **VALID** agar aplikasi dapat digunakan. `.env.example` hanya **contoh**, bukan konfigurasi produksi; kredensial dan token di dalamnya tidak boleh dianggap sesuai untuk deployment baru.

**Akun awal tidak disediakan otomatis.** Seeder pengguna demo hanya membuat fixture pada lingkungan nonproduksi apabila `SEED_DEMO_USERS=true` **dan** `SEEDED_USER_PASSWORD` memenuhi kebijakan sandi kuat. Jangan mengandalkan username/password bawaan dari panduan lama. Untuk membuat administrator, ikuti bagian berikut.

## Konfigurasi awal produksi

1. Siapkan database kosong, HTTPS, PHP-FPM dan virtual host dengan document root `public/`. Cadangkan dan uji restorasi sebelum mengubah instalasi yang sudah berjalan.
2. Gunakan `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, `SESSION_SECURE_COOKIE=true`, dan `SEED_DEMO_USERS=false`. Batasi `TRUSTED_PROXIES` ke alamat proxy yang benar-benar dipercaya, **jangan `*`**.
3. Tetapkan `APP_KEY` hanya saat instalasi pertama. **Jangan jalankan `key:generate` ulang pada aplikasi berisi data** karena dapat merusak sesi dan data terenkripsi.
4. Konfigurasikan lisensi yang valid, database, mail, queue, file storage serta layanan tambahan sesuai kebutuhan.
5. Pasang dependensi dari **lockfile** dan jalankan migrasi tanpa menghapus histori:

   ```bash
   composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
   php artisan migrate --force
   php artisan db:seed --class=RoleSeeder --force
   php artisan invman:provision-admin admin admin@example.org
   php artisan license:status
   php artisan invman:security-audit
   ```

6. Build frontend dengan `npm ci && npm run build` di lingkungan build yang terkontrol, atau deploy hasil build yang cocok dengan commit tersebut. Pastikan `storage/` dan `bootstrap/cache/` dapat ditulis oleh pengguna proses aplikasi.
7. Jalankan dan verifikasi **queue worker + scheduler** (di bawah), lalu uji alur peminjaman, QR, pengembalian, tiket, dan hak akses pada staging sebelum operasional penuh.

`invman:provision-admin` **wajib dijalankan melalui terminal interaktif**. Password diminta secara tersembunyi, minimal 16 karakter dan kombinasi huruf besar/kecil, angka serta simbol; perintah tidak mencetak password dan tidak mereset akun yang sudah ada.

> **Keamanan data:** `php artisan db:seed` default merupakan **no-op di production**. Jika perlu data referensi, jalankan *seeder yang telah ditinjau* secara eksplisit setelah backup; jangan mengisi data demo ke database aktif. **Jangan** gunakan `migrate:fresh`, `db:wipe`, atau seeder yang mengubah data operasional pada produksi.

Penjelasan deployment dan kebijakan seeder: [Production Hardening](docs/PRODUCTION-HARDENING.md).

## Queue worker dan scheduler (wajib)

Queue worker mengerjakan pekerjaan asinkron. **Scheduler berbeda dari queue worker**: tanpa scheduler, hold peminjaman yang telah lewat waktunya, pengingat keterlambatan, dan eskalasi SLA tidak dijalankan sesuai jadwal.

Jalankan worker sebagai proses jangka panjang menggunakan Supervisor atau systemd:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

Contoh entri **cron sistem** untuk scheduler (sekali per menit):

```cron
* * * * * cd /path/to/invman && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Pada panel cron seperti aaPanel: isi jadwal `* * * * *` di **pengaturan waktu**, dan isi hanya `cd /path/to/invman && /usr/bin/php artisan schedule:run >> /dev/null 2>&1` pada **perintah shell**. Jangan memasukkan lima kolom `*` ke awal perintah shell.

| Proses terjadwal | Frekuensi |
| --- | --- |
| `loans:expire-holds` | Tiap menit |
| `loans:notify-overdue` | Tiap jam |
| `tickets:escalate-sla` | Tiap jam |

Jadwal ini memerlukan validasi lisensi yang berlaku. Periksa dengan `php artisan schedule:list`. Sesudah deploy jalankan `php artisan queue:restart`; restart pula PHP-FPM dan layanan jangka panjang lain jika konfigurasi berubah. Untuk notifikasi real-time/Chatify, konfigurasi broadcast dan server terkait harus diuji tersendiri.

**SLA tiket:** sesuaikan `TICKET_SLA_TIMEZONE`, `TICKET_SLA_WORKDAY_START`, `TICKET_SLA_WORKDAY_END`, `TICKET_SLA_WORKING_WEEKDAYS`, dan `TICKET_SLA_HOLIDAYS` dengan kalender kerja organisasi. Perubahan kalender tidak otomatis mengubah tenggat tiket yang sudah dibuat. Lihat [config/ticket_sla.php](config/ticket_sla.php).

## Update aplikasi tanpa menghilangkan data

Untuk instalasi aktif, lakukan di **staging** dahulu. Deploy kode bersama `composer.lock` dan `package-lock.json` dari commit yang sama; jangan `composer update` langsung pada server produksi.

```bash
# Setelah backup dan deploy source yang sudah diuji:
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear
php artisan license:status
php artisan invman:security-audit
php artisan queue:restart
```

Deploy juga aset Vite hasil build untuk commit tersebut; verifikasi cron, worker, login, hak akses, serta notifikasi. Uji minimal satu transaksi lengkap barang/ruangan/kendaraan yang relevan, konfirmasi dua pihak, PDF privat, dan laporan serta penyelesaian tiket. **Jangan mengganti `APP_KEY` yang sudah digunakan.**

## Pengujian dan keamanan

```bash
php artisan test
composer audit --locked --no-dev --abandoned=ignore
npm audit --omit=dev --audit-level=high
npm run build
```

[GitHub Actions CI](.github/workflows/ci.yml) menjalankan pemeriksaan Composer/PHP, tes Laravel di SQLite, smoke test migrasi dan pembatasan akses di MySQL 8, audit dependensi produksi, serta build Vite. **Lulus CI bukan pengganti UAT di staging.** Audit npm untuk dependensi *build-time* dilaporkan terpisah dan masih memiliki advisory upstream yang dicatat pada [dokumen hardening npm](docs/NPM-BUILD-SECURITY-2026-10.md).

Akses histori dan berkas privat selalu harus mengikuti izin pemohon, unit, atau pengelola. Jangan menaruh `.env`, bukti perawatan, lampiran tiket, atau token rahasia di direktori publik.

## Tindakan operasional dan privasi halaman awal

Dasbor `/admin` menampilkan **Pinjaman Cepat**, **Pinjaman Multi-aset**, dan **Laporan Kerusakan** sebagai tindakan utama sesuai izin pengguna. Tautan persetujuan, status pinjaman, jadwal, dan tugas dipisahkan pada bagian pemantauan. Pengelola/teknisi tetap tidak mendapat akses membuat pinjaman bila role-nya tidak mengizinkan.

Halaman `/` memakai dasbor, statistik, dan tabel native Filament yang sama sebelum maupun setelah login. Pengunjung dapat melihat jumlah reservasi, jumlah barang, waktu, serta status; nama aset, kegiatan, unit, lokasi, dan nomor polisi hanya menampilkan maksimal tiga karakter awal diikuti `•••`. Keterangan kegiatan dan modal detail lengkap hanya tersedia untuk akun aktif. Masking dilakukan di server pada setiap render, termasuk pembaruan Livewire setelah logout; pencarian nama lengkap hanya tersedia setelah login. Respons halaman memakai `private, no-store` serta `Vary: Cookie`. Jadwal kedua sesi mengikuti filter status/waktu yang sama. Halaman QR ruangan terpisah tetap mengikuti fungsinya sendiri.

## Relasi resource, dropdown, dan sesi halaman

Tab relasi tersedia pada resource yang memiliki data turunan: unit, jenis/pengelola/barang/barang satuan, gedung/lantai/ruangan, kendaraan/pengemudi/pendamping, pengguna, kegiatan/pengajuan/reservasi, kategori/tiket, jabatan/tim, dan workflow. Resource tanpa koleksi turunan tetap memakai field relasi pada formulir utamanya.

- CRUD master pada tab relasi memakai formulir dan kolom dari resource yang sama. Buka halaman **Ubah** induk untuk melakukan perubahan. Mode **Lihat** tetap hanya baca. Izin induk dan data terkait diperiksa kembali pada server, termasuk setelah role berubah.
- Hubungan ke induk dikunci. Mengubah nilai tersembunyi tidak memindahkan data ke unit, kelompok, gedung, atau aset lain. Barang satuan tetap memakai observer inventaris; bulk tidak dapat dibuat menjadi satuan palsu.
- Keanggotaan pengelola memakai pivot resmi dan menyelaraskan role otomatis. Menghapus keanggotaan di satu kelompok tidak mencabut akses dari kelompok lain.
- Reservasi pada pengajuan hanya dapat ditambah/diubah/dihapus selama masih draf dan sesuai izin resource. Jadwal/status mengikuti pengajuan; aset duplikat dan jadwal tidak tersedia ditolak. Persetujuan, serah terima, alokasi, bukti, koreksi, dan audit tetap melalui layanan proses, bukan CRUD mentah.
- Relasi komentar tiket memakai visibilitas internal/publik. Lampiran tetap diunduh melalui controller yang memeriksa izin. Riwayat dan estimasi internal tidak ditampilkan kepada pelapor yang tidak berwenang.
- Semua select dan filter dropdown mempunyai pencarian serta pilihan awal. Pemuatan awal dibatasi dan tetap memakai query izin/dependensi yang sama dengan pencarian; inventaris besar dicari lebih lanjut dengan mengetik nama/kode.

Widget dasbor yang hanya dipilih oleh role tetap didaftarkan untuk permintaan Livewire berikutnya. Tanpa pendaftaran ini, Livewire dapat mengembalikan 419 karena nama komponen tidak dikenali meskipun sesi dan token CSRF masih valid. Kesalahan komponen/versi ditampilkan terpisah dan tidak diulang sebagai transaksi.

Pemulihan sesi membedakan kegagalan pemeriksaan koneksi/server, sesi berakhir (401), pergantian akun, dan penolakan CSRF (419). Kesalahan pemeriksaan sesi tidak lagi diubah menjadi 419. Hanya respons penolakan CSRF yang ditandai server sebagai **belum menjalankan tindakan** yang dapat dicoba sekali lagi, jika token berubah dan akun masih sama. Respons lain dan kegagalan jaringan tidak diulang otomatis. Token pada metadata halaman juga disegarkan agar unggahan tidak memakai token lama. Isian formulir tetap dipertahankan; masuk ulang dibuka di tab terpisah bila sesi berakhir.

Setelah memperbarui versi ini, muat ulang penuh halaman browser sekali agar skrip pemulihan lama diganti.

## Ekspor, impor, dan template data

Tabel data menyediakan **Ekspor Excel (.xlsx)**, **Unduh Template (.xlsx)**, dan **Impor Data (.xlsx)** sesuai hak akses. Gunakan template XLSX terbaru dengan satu lembar kerja; ukuran berkas maksimal 5 MB dan 1.000 baris data per unggahan. Format CSV tidak diterima untuk fitur impor ini. Jangan mengubah nama header; isi sel dengan nilai langsung, bukan formula. Formatkan nomor telepon, kode, atau identitas yang diawali nol sebagai teks. Ekspor mengikuti filter tabel serta cakupan unit/pengelola; password tetap kosong dan teks berawalan tanda formula diekspor sebagai sel teks yang aman.

- ID kosong menambahkan data; ID terisi memperbarui data yang sama. Tersedia mode tambah saja, edit saja, atau campuran. ID terisi yang tidak ditemukan tidak dibuat menjadi data baru.
- Satu berkas merupakan satu transaksi: kesalahan pada baris mana pun membatalkan seluruh perubahan dan menampilkan nomor baris. Untuk edit sebagian, hapus kolom yang tidak diubah. Sel kosong mengosongkan kolom opsional; password kosong mempertahankan password lama.
- Relasi menggunakan ID dari ekspor menu terkait. `roles`, `permissions`, `member_ids`, `weekdays`, `steps`, dan `needs` memakai JSON. Contoh hari: `[1,3,5]` berarti Senin, Rabu, Jumat. Kolom informatif `available_quantity`, status/version workflow, serta status/pemilik pengajuan dari hasil ekspor tidak diimpor ulang.
- Master yang didukung: unit, jenis/pengelola/barang/barang satuan, gedung/lantai/ruangan, kendaraan/pengemudi/pendamping, pengguna/peran, kategori laporan, jabatan/penugasan/tim, workflow draft, kegiatan, jadwal rutin, checklist, serta catatan histori/ulasan mandiri. Impor tetap memakai validasi relasi, pembatasan akses, dan observer inventaris. Role otomatis pengelola tetap mengikuti keanggotaan, jumlah barang mengikuti Barang Satuan/pergerakan stok, dan workflow published tetap tidak dapat diedit.
- Pada peminjaman dan tabel reservasi, **Impor Draf Pengajuan** memakai ID pengajuan dan daftar kebutuhan lengkap, bukan mengubah status reservasi atau alokasi satuan. Contoh `needs`: `[{"type":"room","room_id":1},{"type":"item","item_id":2,"quantity":3}]`. Ketersediaan diperiksa saat impor; draf tetap harus diajukan melalui aplikasi. Mengimpor atas nama Sarpras lain membutuhkan `borrower_id` dan `override_reason` sesuai kewenangan.
- Laporan kerusakan dapat diimpor sebagai tiket baru. Triase, penugasan, persetujuan, ulasan terkait pengajuan, serah terima, lampiran/foto, serta perubahan status tetap memakai tindakan aplikasi yang diaudit. Kolom berkas tidak menerima path dari XLSX.

## Jadwal rutin aset

Buka **Data Aset → Jadwal Rutin Aset**, atau tab **Jadwal Rutin (Blokir Booking)** pada barang, ruangan, atau kendaraan. Tentukan hari mingguan, jam mulai/selesai, tanggal mulai, batas tanggal opsional, dan keaktifan. Jam selesai sebelum/sama dengan mulai berarti selesai esok hari; jam yang sama memblokir 24 jam. Semua jam mengikuti `APP_TIMEZONE` (bawaan proyek: Asia/Jakarta).

Jadwal rutin memblokir seluruh aset/jumlah barang selama waktu tersebut, termasuk pengajuan baru, pengiriman draf lama, persetujuan, dan jalur reservasi lama. Batas waktu bersifat `[mulai, selesai)`, sehingga booking boleh tepat setelah jadwal rutin selesai. Jadwal rutin baru/perubahan yang bentrok dengan booking aktif ditolak; sesuaikan booking terlebih dahulu. Nonaktifkan jadwal untuk membuka booking kembali. Halaman QR publik ruangan menampilkan hari/jam rutin tanpa judul kegiatan internal.

Jalankan `php artisan migrate` saat memasang perubahan ini. Verifikasi fitur dengan `php artisan test --filter='ResourceDataExchangeTest|RecurringAssetScheduleTest'`.

## Troubleshooting singkat

| Gejala | Pemeriksaan pertama |
| --- | --- |
| HTTP **423 Locked** | `php artisan license:status`, kecocokan `APP_URL`, domain/IP allowlist dan token |
| Login tidak bisa atau tak ada akun | `php artisan migrate:status`, `RoleSeeder`, provisioning admin dan izin panel; **tidak ada akun default produksi** |
| Job/notifikasi macet | Supervisor/queue, `QUEUE_CONNECTION`, `php artisan queue:failed`, dan log aplikasi |
| Hold/SLA tidak diproses | `php artisan schedule:list`, cron **setiap menit**, lisensi valid |
| Aset/CSS/JS tidak tampil | Hasil `npm run build`, direktori `public/build`, URL HTTPS serta cache web/PHP |
| Peminjaman, tiket, atau bukti ditolak | Periksa role, unit, pengelola aset, status transaksi, dan safety hold; jangan bypass otorisasi |

## Dokumentasi lanjutan

- [Dokumentasi penggunaan ringkas (historis; validasi terhadap kode terkini)](dokumentasi.md)
- [Peminjaman Cepat](docs/QUICK-LOAN-UX.md) · [Kegiatan Master](docs/LOAN-EVENT-MASTER-V2.md) · [Dashboard Operasional](docs/OPERATIONAL-DASHBOARD-V2.md)
- [Operasional Kendaraan dan kru](docs/VEHICLE-OPERATIONS.md)
- [Serah-terima keluar dua pihak](docs/OUTBOUND-HANDOVER-V2.md) · [QR & pengembalian](docs/QR-HANDOVER-RETURNS.md) · [Bukti PDF](docs/HANDOVER-EVIDENCE-PDF.md)
- [Ticketing inti dan histori V1](docs/TICKETING-CORE-V1.md) · [Pelaporan Tiket](docs/TICKET-REPORTING-UX-V2.md) · [Perawatan/SLA V3](docs/TICKETING-MAINTENANCE-V3.md)
- [Akses & retensi histori](docs/LOAN-ACCESS-RETENTION.md) · [Mass-assignment hardening](docs/MASS-ASSIGNMENT-HARDENING.md) · [Audit P1](docs/P1-AUDIT-HARDENING.md)
- [Lisensi offline](docs/LISENSI.md) · [Production Hardening](docs/PRODUCTION-HARDENING.md)
- [Peningkatan Laravel 12](docs/LARAVEL-12-UPGRADE-2026-10.md) · [Pembaruan dependensi PHP](docs/DEPENDENCY-REMEDIATION-2026-10.md) · [Hardening npm](docs/NPM-BUILD-SECURITY-2026-10.md)

**Batas cakupan:** integrasi MikroTik/Hotspot **tidak dikelola di InvMan**; pengembangannya dipisahkan ke repository [`yusufbayuw/dokumentasi`](https://github.com/yusufbayuw/dokumentasi). Jangan mengaktifkan kembali kontrol jaringan pada aplikasi inventaris ini tanpa keputusan arsitektur terpisah.
