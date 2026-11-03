# InvMan — Production Readiness Audit & Remediation

Tanggal: **3 November 2026 (WIB)**. Pemilik sistem meminta audit mandiri; tidak ada akses atau perubahan database produksi, merge PR, ataupun deployment produksi.

## A. Executive Summary

**Keputusan: NO-GO untuk rilis gabungan #31/#32 dan aktivasi approval dinamis Work Order YTB. Risiko tinggi sampai blocker di bawah diselesaikan.**

Peminjaman existing mempunyai bukti regresi backend yang kuat pada integrasi ini. Itu **bukan sertifikasi keamanan instalasi produksi existing**. Head `main` dan kedua PR asli belum memuat perbaikan audit ini. Ditemukan self-approval pada fallback maintenance, kewenangan service bagi akun nonaktif, akses kandidat approval yang sudah dicabut, serta masalah integritas konfigurasi. Perbaikannya tersedia pada branch integrasi, bukan perubahan langsung ke `main`.

Alur backend yang didukung berhasil dijalankan untuk enam unit: laporan dengan ruangan yang dikelola Fasilitas → diketahui atasan → diterima staf → tinjauan supervisor → penugasan → diterima/dikerjakan teknisi → verifikasi pelapor → pengesahan manager → ditutup → PDF berotorisasi. **Alur koreksi setelah penolakan belum tersedia.** Bagian vendor/biaya pada formulir YTB juga belum didukung. Browser dengan login normal terautentikasi **BLOCKED**; tidak diubah menjadi PASS dengan fixture login.

Hasil akhir lokal:

- SQLite: **256 lulus, 1 skipped khusus foreign key MySQL; 2.009 assertions**.
- MySQL 8.0.46: **257 lulus, 2.017 assertions**; hasil lengkap disimpan di [mysql-tests.txt](evidence/2026-11-03/mysql-tests.txt). Jangan menjumlahkan suite SQLite dan MySQL sebagai skenario unik.
- Dua proses MySQL: keputusan approval hanya sekali; pembuatan draft global oleh dua admin hanya menghasilkan satu draft setelah perbaikan.
- Upgrade sintetis: 64 migrasi lama → migrasi baru; data existing/password/role/histori dipertahankan; seeder dua kali idempotent; contoh tidak terpublikasi otomatis.
- Build frontend, lint PHP, Composer validate, audit Composer produksi dan npm, pemeriksaan konfigurasi aman sintetis, serta kompatibilitas route diperiksa. CI commit final ditautkan pada deskripsi PR integrasi; keberhasilan CI tidak menggantikan UAT.

### Input yang benar-benar diperiksa

| Sumber | Commit | Status saat audit |
| --- | --- | --- |
| `main` | `153fef1c678d328766389d9093582b7f6d7fb3f1` | Branch dasar, tidak diubah |
| [PR #31](https://github.com/yusufbayuw/invman/pull/31) | `d68bd7a93c34e61d9473c256d158c30611b6aab8` | Open, draft, **belum merge** |
| [PR #32](https://github.com/yusufbayuw/invman/pull/32) | `77c7760c3430d728a0134d067425cbfab75e148e` | Open, draft, **belum merge** |
| SPMB `main`, referensi read-only | `3ab116c3dd0ce9f3de0e54679178cc1d54d15cf2` | Tidak dimodifikasi |

Seluruh diff kedua PR diperiksa, termasuk perubahan Filament, model, service, migration, seeder, konfigurasi, dependency lock, tes, dan dokumentasi. Integrasi dilakukan pada `audit/production-readiness-pr31-pr32`. Merge kedua head **tanpa konflik tekstual**, tetapi ada konflik perilaku: approver jabatan tanpa role teknis dianggap teknisi oleh dashboard #31 dan antrean persetujuannya tersembunyi. Itu diperbaiki, bukan dianggap aman karena Git berhasil merge.

SPMB digunakan sebagai pembanding konkret: draft/published immutable, normalisasi dan validasi konfigurasi, versi yang melekat pada transaksi, serta penguncian scope bersama. Tidak ada konfigurasi atau data SPMB yang disalin ke InvMan.

### Remediasi dan CI yang sudah diverifikasi

- [PR integrasi #33](https://github.com/yusufbayuw/invman/pull/33), draft/terbuka; [commit remediasi](https://github.com/yusufbayuw/invman/commit/439bfb593857f03a41c5b9a2faffa424ccb41f48).
- [InvMan Quality Guard #139](https://github.com/yusufbayuw/invman/actions/runs/41569321046) **SUCCESS** pada commit `49023044d6e220fb5f0c01cf1ca7c00217107d25`: `php-tests` dan `mysql-regression` lulus. Log CI mengonfirmasi 256 SQLite + 1 skipped (2.009 assertions), 257 MySQL (2.017 assertions), kedua probe concurrency true, upgrade PASS, histori/kredensial tetap, dan production demo guard PASS. [Bukti CI](CI-RESULTS-2026-11-03.md).
- Head asli [#31 CI #130](https://github.com/yusufbayuw/invman/actions/runs/41450095610) dan [#32 CI #138](https://github.com/yusufbayuw/invman/actions/runs/41559146599) juga SUCCESS. Temuan negatif tambahan audit menjelaskan mengapa CI lama tidak cukup untuk GO.
- Penambahan bukti CI ke dokumen ini tidak mengubah kode yang diuji. Status check head dokumen terakhir ditautkan pada PR #33. **Keputusan tetap NO-GO** karena OPEN-01, UAT dan gate operasional.
- Runtime cloud menjadi unavailable setelah bukti dan perbaikan tersimpan di GitHub. Penautan bukti CI diselesaikan melalui GitHub; tidak ada hasil browser tambahan yang diklaim setelah runtime berhenti.

### Matriks fitur dan dampak

| Area | Existing `main` | #31 | #32 / integrasi | Risiko perubahan |
| --- | --- | --- | --- | --- |
| Barang, ruang, kendaraan; QR; serah-terima dua pihak; pengembalian | Ada | Presentasi/navigasi, URL dipertahankan | Regresi diuji | Hak unit/aset, status, bukti dan histori harus tetap sama |
| Pengajuan cepat / multi-aset; Agenda Kegiatan | Ada | Jalur cepat utama; menu lanjutan dikelompokkan | Tetap tersedia | Perbedaan jumlah pengajuan dan reservasi dilindungi tes |
| Dashboard peran | Dashboard existing | Antrean pekerjaan dan pintasan relevan | Kandidat approval dinamis masuk antrean | Multi-role dan approver tanpa role teknis perlu perhatian |
| Maintenance V3 / SLA / safety hold | Ada | Tidak dirombak | Gate start/final dinamis; fallback tanpa workflow | Salah konfigurasi dapat memblokir pekerjaan; fallback harus aman |
| Jabatan / penugasan / workflow | Belum ada | Tidak ada | 4 tabel baru, 3 resource admin, snapshot per tiket | Cakupan NULL berarti semua unit/pengelola; jangan disalahartikan pusat |
| Penolakan dan revisi laporan | Maintenance existing | Tidak berubah | Reject memblokir; revisi/resubmit belum ada | **P1 untuk cakupan YTB lengkap** |
| Vendor dan estimasi biaya | Tidak lengkap | Tidak berubah | Belum didukung | Gap kebutuhan bisnis, bukan fitur yang diklaim lulus |
| PDF | PDF maintenance existing | Tetap ada | Audit menambah langkah keputusan dinamis | Identitas/nama jabatan historis belum snapshot penuh |

## B. Test Matrix

Lihat [matriks rinci](TEST-MATRIX-2026-11-03.md) untuk expected, actual, PASS/FAIL/BLOCKED dan referensi tes per skenario. Bukti mesin ada di [folder evidence](evidence/2026-11-03/).

### Lingkungan dan batas pengujian

- PHP **8.3.6**, Laravel **12**, Filament **3**, Livewire sesuai `composer.lock`; MySQL **8.0.46** dengan strict/ONLY_FULL_GROUP_BY, utf8mb4. SQLite dipakai sebagai pembanding, bukan pengganti MySQL.
- MySQL, PHP web server, dan database `invman_audit`, `invman_tests`, `invman_upgrade` terisolasi. Datadir MySQL ditempatkan pada filesystem sementara lokal runtime, bukan folder yang disinkronkan. Tidak ada koneksi produksi.
- Database kosong dimigrasikan; upgrade merekonstruksi **skema main dari migration**, lalu memasukkan user, role, kegiatan, reservasi, tiket, maintenance dan event sintetis sebelum migrasi tambahan. Ini bukan salinan database produksi dan tidak mengukur durasi upgrade pada volume produksi.
- Struktur demo: Daycare/KB/TK, SD, SMP, SMA, TBU, ADM; Kepala Sarpras, staf/SPV/manager Fasilitas, staf/SPV IT, staf/SPV Perpustakaan, koordinator teknisi, teknisi. Administrator adalah **role teknis terpisah**, bukan jabatan yang otomatis memberikan izin. Ada multi-jabatan, rotasi unit/atasan, akun/jabatan nonaktif, dan pemegang kosong dalam tes.
- Mail/notification fake, penyimpanan pengujian, dan identitas sintetis. Pengiriman SMTP, push, broadcast, worker daemon, cron, serta delivery provider produksi **belum diverifikasi**.
- Lisensi QA ditandatangani dengan fixture provider seperti `Tests\TestCase`; middleware lisensi tetap aktif. Ini tidak membuktikan lisensi/domain produksi valid.
- Browser membuka aplikasi Laravel sungguhan, login publik, dan jadwal publik. **Tidak ada endpoint fixture-login, cookie injection, CSRF bypass, atau middleware auth yang dinonaktifkan.**
- Browser mengharuskan kredensial melalui mekanisme aman yang tidak tersedia tanpa handoff. Agen tidak mengetik kredensial model-visible. Pengujian autentikasi positif dilakukan melalui **tes Laravel/Livewire**, bukan browser UAT.
- Black-box HTTP menggunakan cookie jar/session database dan token CSRF dari halaman normal. Pembaruan Livewire valid 200; token hilang/salah 419; checksum snapshot palsu 419 (respons produksi native Livewire); halaman konfigurasi tanpa login 302 ke login.
- Percobaan HTTP login dengan widget Turnstile resmi khusus pengujian mencapai 500 karena backend QA tidak dapat resolve `challenges.cloudflare.com`. **BLOCKED konektivitas provider**, bukan hasil login berhasil ([bukti](evidence/2026-11-03/captcha-provider-blocker.txt)). Frontend widget test berhasil dimuat. Default CAPTCHA lokal diuji pada komponen auth dengan challenge milik proses tes; session diregenerasi dan akun nonaktif ditolak. Tidak ada perubahan keamanan CAPTCHA aplikasi yang dikirim ke repository.

### Bukti browser saat audit ini

| Nomor | Bukti | Cakupan sebenarnya |
| --- | --- | --- |
| 01 | [Login desktop](evidence/2026-11-03/01-login-desktop.jpg) | Halaman normal sebelum autentikasi, native browser 1365×936 |
| 02 | [Login mobile](evidence/2026-11-03/02-login-mobile.jpg) | Iframe CSS 360/390/430×820, bukan sesi dashboard |
| 03 | [Jadwal publik mobile](evidence/2026-11-03/03-public-mobile.jpg) | Halaman nyata, empty state, pembungkus QA; tidak ada fixture transaksi |
| 04 | [Jadwal publik desktop](evidence/2026-11-03/04-public-desktop.jpg) | Iframe CSS 1366×900, ditampilkan skala 95% agar muat di browser |

[Pengukuran](evidence/2026-11-03/browser-measurements.json): pada jadwal publik, scrollWidth sama dengan clientWidth: 345, 375, 415, 1351 (15 px dipakai scrollbar vertikal native). Itu **tidak membuktikan** dashboard/tabel/modal terautentikasi bebas overflow. Wrapper QA tidak masuk kode produk.

Screenshot dashboard sebelum/sesudah dari PR #31 adalah **bukti historis fixture pada pekerjaan sebelumnya**, bukan UAT normal audit ini. Dashboard semua role, konfigurasi/publish, approval/reject/reassign, detail tiket/PDF, QR dan pengembalian melalui browser normal pada 360/390/430/1366 tetap **BLOCKED**. Tidak disediakan screenshot seolah-olah sesi login tersebut berhasil.

Warning aset Livewire usang sempat terlihat pada persiapan preview karena vendor/runtime disalin tanpa menjalankan script Composer. Aset dipublikasikan ulang melalui script existing `livewire:publish --assets`; ini masalah persiapan lingkungan. Warning tidak membuktikan akar 419 historis PR #31. Jangan menonaktifkan CSRF; investigasi polling terautentikasi harus memakai staging normal, APP_KEY/domain/proxy/cookie/build yang konsisten.

## C. Security Review

### Bug yang direproduksi dan diperbaiki

| ID | Prioritas | Temuan dan dampak | Remediasi / bukti |
| --- | --- | --- | --- |
| FIX-01 | P1 | Kandidat snapshot yang penugasannya dicabut masih dapat melihat tiket/PDF; jabatan nonaktif masih memberi panel access | Query eligibility aktif konsisten pada list/detail/private PDF/panel/resource; tes revoke dan inactive title |
| FIX-02 | P1 | Service legacy menerima akun Fasilitas nonaktif untuk assign/cancel; reporter nonaktif dapat verify | Gate backend enabled dipertahankan di jalur service dan create; tes langsung, bukan hanya tombol |
| FIX-03 | P1 | Pelapor multi-role Fasilitas dapat menyetujui laporan maintenance legacy sendiri | Self-approval ditolak di fallback dan gate assign/close; kandidat dinamis self juga diuji |
| FIX-04 | P1 | Pemeriksaan authority/checkpoint sebelum ticket lock menyisakan celah revokasi antara precheck dan transaksi | Check dilakukan kembali di dalam transaksi tiket; regresi menyuntikkan revokasi sebelum lock. Bukan klaim semua kemungkinan race revokasi sudah diuji |
| FIX-05 | P1 | Dua admin berbeda membuat dua draft global versi sama karena mengunci baris admin masing-masing | Shared scope lock untuk create/publish + stale version guard + retry 3; dua proses MySQL membuktikan satu draft |
| FIX-06 | P1 | Urutan start→final→start lolos validasi dan dapat membuat alur tidak dapat diselesaikan | Semua tahap start harus sebelum tahap final pertama |
| FIX-07 | P2 | Siklus atasan tersembunyi melalui jabatan kedua; default aktif DB luput dari validasi akun nonaktif | Telusuri seluruh graph assignment aktif, atribut default model sama dengan DB; dua regresi |
| FIX-08 | P2 | Integrasi #31/#32 menyembunyikan approval-only ticket queue sebagai teknisi | Bedakan membership teknisi nyata dari kewenangan approver; tes widget dan multi-role |
| FIX-09 | P2 | Kontak Chatify HTTP 500 pada strict MySQL GROUP BY | Group by alias contact_id, tanpa menurunkan SQL mode; regresi Chatify dijalankan MySQL |
| FIX-10 | P2, tes | Nomor fixture tiket #31 melampaui varchar(32), lolos SQLite | Gunakan ULID fixture; schema/data lama tidak diperbesar |
| FIX-11 | P2 | PDF tidak mencetak rantai approval dinamis | Cetak tahap/status/aktor/waktu/catatan aktual dan versi workflow; legacy tetap didukung |
| FIX-12 | P2 | Label assignment kosong = “pusat”, padahal artinya semua unit | Label/helper/placeholder menyebut cakupan semua unit/pengelola, tanpa mengubah arti NULL |

[Bukti negatif sebelum perbaikan](evidence/2026-11-03/security-before.txt), [integrasi](evidence/2026-11-03/integration-before.txt), [konfigurasi](evidence/2026-11-03/configuration-before.txt), [regresi sesudah](evidence/2026-11-03/security-regressions.txt), [balapan sebelum](evidence/2026-11-03/concurrency-before.json), [sesudah](evidence/2026-11-03/concurrency-after.json). Original head #31/#32 **belum otomatis menerima FIX ini**. Perubahan model/service di audit dibatasi pada bug yang dibuktikan tersebut, tidak ada migration tambahan.

### Batas kewenangan yang dipertahankan

- Fasilitas/Administrator mempunyai kewenangan lintas unit yang memang disengaja existing. Itu dibedakan dari akses Sarpras/kandidat jabatan. Filter UI tidak memperluas query otorisasi.
- Kandidat wajib tercantum dalam snapshot **dan** masih memenuhi assignment/title/unit/pengelola saat ini. Pergantian atasan memerlukan pengalihan admin beralasan; penunjukan jabatan baru tidak otomatis mengubah kandidat snapshot.
- Urutan start/final ditegakkan service; role admin/Fasilitas tambahan tidak otomatis memberikan keputusan manager pada workflow yang membatasi jabatan. Teknisi tidak dapat menutup tanpa verifikasi/gate final.
- PDF/lampiran tidak menjadi file publik. View/attachment/PDF memakai batas tiket; komentar internal tetap privat, tipe file/path palsu ditolak. Blade mencetak field PDF dengan escaping; Dompdf remote access nonaktif.
- Session auth dan CSRF tetap aktif. Tes positive login memeriksa regenerasi session; disabled account ditolak. Snapshot Livewire yang dimanipulasi ditolak pada HTTP; URL/ID/payload form yang dipalsukan diuji melalui feature/Livewire tests.
- Work order approval dan audit event tercatat sekali pada uji keputusan paralel; retry transaksi tetap ada. Publikasi immutable, tiket lama tidak diganti ketika versi baru diterbitkan. Riwayat tidak dihapus untuk mengoreksi hasil.

Tidak dilakukan pentest jaringan produksi, fuzzing luas, uji beban, red-team, maupun verifikasi seluruh kombinasi concurrency revoke/publish/assignment. Perlindungan berbasis model bukan tamper-proof database terhadap DBA atau kode yang menulis SQL langsung. Snapshot menyimpan label tahap/ID jabatan/kandidat/keputusan, **belum snapshot penuh nama jabatan, unit dan organisasi historis**; perubahan assignment/jabatan juga belum mempunyai audit konfigurasi setara ticket events.

Dependency `main` mempunyai advisory **medium** `symfony/mime` CVE-2026-45070, v7.4.19; kedua PR sudah membawa v7.4.22. [Hasil main](evidence/2026-11-03/main-composer-audit.json), [integrasi produksi 0 advisory](evidence/2026-11-03/composer-production-audit.json), [npm 0](evidence/2026-11-03/npm-audit.json). Hasil ini point-in-time; tidak menjamin tidak ada kerentanan yang belum dipublikasikan.

## D. Regression Review

Suite lengkap menguji peminjaman item/room/vehicle, cepat/multi-aset, agenda/master, approval, filter per unit, kalender publik/privat, QR signed/expired/tampered, penyerahan dua pihak, return/checklist/kondisi, histori dan koreksi admin, petugas kendaraan, notifikasi fake, tiket otomatis barang rusak, maintenance dan safety hold, inventory, serta role/permission existing. Tidak ditemukan regresi backend tersisa pada suite akhir. Hasil ini tidak mencakup delivery eksternal dan seluruh interaksi browser.

**181 route main tetap ada; integrasi 192 route.** Perbandingan method/URI/nama/action pada konfigurasi sama `APP_DEBUG=false`: [bukti](evidence/2026-11-03/route-compatibility.json). Selisih awal route Livewire `.js` versus `.min.js` berasal dari perbedaan debug environment, bukan route yang dihapus. Tidak ada route existing diganti.

Role dashboard yang diuji melalui Laravel/Livewire: Sarpras, Fasilitas, Administrator, Teknisi, Pengelola Aset, Sarpras+Teknisi, admin tanpa pekerjaan teknisi, dan approver-only. Tindakan Kerjakan tetap membuka tiket spesifik dan mempertahankan ownership/kualifikasi/status. Dashboard dan form konfigurasi dikelola lewat komponen Filament native. Pengujian form admin create jabatan/assignment/workflow dan publish benar-benar memakai Livewire, **bukan browser login**.

### Kesesuaian formulir Work Order YTB

Formulir kosong asli milik pengguna diperiksa langsung sebagai referensi privat; tidak disalin ke repository.

| Kebutuhan | Hasil audit |
| --- | --- |
| Pelapor/unit/waktu/lokasi/deskripsi/foto/urgensi | Didukung; kategori/urgensi aplikasi tidak persis nomenklatur formulir. Upload izin/type diuji, UI browser BLOCKED |
| Diajukan/diketahui atasan/diterima Fasilitas/ditinjau supervisor | Dapat dimodelkan sebagai approval start berurutan, dengan aktor/waktu/notes; bukan formulir inspeksi terpisah |
| Tinjauan internal atau vendor eksternal | **Belum ada pilihan/alur vendor terstruktur** |
| Diagnosis + daftar material + perkiraan biaya sebelum persetujuan | Diagnosis/material dicatat saat finish; **estimasi biaya dan persetujuan nominal belum ada** |
| Penugasan/teknisi/selesai/verifikasi user/pengesahan manager | Didukung dengan konfigurasi dan aset/cakupan yang sesuai; tes enam unit lulus |
| Penolakan → koreksi → kirim ulang dalam tiket sama | **Belum ada; penolakan memblokir permanen pada tahap tersebut** |
| PDF seluruh persetujuan | Setelah FIX-11 mencetak keputusan aktual; bukan tanda tangan digital tersertifikasi atau snapshot penuh jabatan historis |
| Kerusakan bangunan tanpa inventaris terkait dikerjakan teknisi pusat | Aturan team existing membatasi laporan tanpa aset ke unit teknisi; tim pusat Fasilitas demo tidak otomatis berhak. **Gap dispatch lintas unit**, jangan memperluas izin diam-diam |

## E. Release Readiness

### Database dan seeder

Migrasi #32 bersifat additive: empat tabel Work Order, foreign key restrict dan index/unique langkah tiket. Tidak mengubah transaksi lama atau menempelkan workflow pada tiket lama. [Probe upgrade](../../tests/Support/work-order-upgrade.php) menolak database non-testing/non-`*_upgrade` dan database yang sudah berisi migrations; tidak menghapus tabel. Bukti [upgrade MySQL](evidence/2026-11-03/mysql-upgrade.json). Test foreign key history yang dilewati SQLite dijalankan MySQL.

Seeder produksi default read-only; demo membutuhkan opt-in + password kuat di non-production, contoh tetap draft. Akun username existing tidak ditimpa dan tidak otomatis diberi role admin. Seeder referensi nama jabatan dapat dipanggil eksplisit; review dulu, jangan `db:seed` demo pada production. Tidak ada `migrate:fresh`, `db:wipe`, atau migration down yang direkomendasikan untuk produksi.

CI ditingkatkan dari smoke MySQL sebagian menjadi **suite lengkap MySQL + probe dua proses + upgrade/seeder dua kali**. Skrip probe concurrency hanya boleh berjalan dengan `APP_ENV=testing`, flag opt-in dan nama DB test/CI. Di CI, `migrate:fresh` hanya pada service MySQL disposable.

Build konfigurasi/route/Blade berhasil lokal. Pembersihan cache awal gagal karena default database SQLite host belum dibuat, kemudian berhasil dengan cache array pada lingkungan kontrol. Itu tidak membuktikan cache/database produksi tersedia. `invman:security-audit` lulus dengan konfigurasi produksi **sintetis**, bukan pemeriksaan server produksi.

### Prasyarat infrastruktur yang masih BLOCKED

Versi/ukuran/schema database produksi aktual, PHP extensions server, TLS/reverse proxy, lisensi domain/IP, secure cookies, storage privat/writable, kredensial mail/push/broadcast, daemon queue, cron, monitoring dan restore backup aktual tidak tersedia untuk audit ini. MySQL lokal tidak menguji failover, replication lag, disk-full, jaringan terputus, ataupun throughput produksi. Tidak ada janji RTO/RPO tanpa uji restore/operasional.

### Rencana rilis setelah semua gate ditutup

1. Pilih commit artefak yang sudah CI hijau dan ditinjau; jangan deploy branch bergerak. Jalankan UAT staging login normal, polling ≥ beberapa siklus, seluruh role/unit dan viewport. Jangan publish workflow organisasi sebelum pemegang jabatan dan coverage setiap unit/aset diverifikasi.
2. Backup konsisten MySQL (`mysqldump --single-transaction --routines --triggers --events --set-gtid-purged=OFF` sesuai privilege), private storage/uploads dan dokumen; simpan APP_KEY, konfigurasi dan lisensi lewat secret store, bukan log/repo. Uji restore ke database terpisah; catat titik backup/binlog, checksum, durasi dan penanggung jawab.
3. Masuk maintenance, hentikan intake dan worker yang menulis; deploy artefak PHP 8.3 yang sesuai, `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader` **dengan script existing** (publish Livewire/Filament), build Vite dari lockfile atau gunakan artefak CI. Jangan membawa env, CAPTCHA test keys, lisensi fixture atau data demo QA.
4. Review `migrate --pretend` dan backup, lalu `php artisan migrate --force`. Verifikasi empat tabel/FK/index baru, hitung histori/transaksi lama. Jangan publish workflow otomatis atau reset akun. Jalankan reference seeder spesifik hanya jika diperlukan dan ditinjau.
5. Set `APP_ENV=production`, debug false, HTTPS APP_URL, secure/HttpOnly/SameSite cookie, trusted proxy terbatas, shared/stable APP_KEY/session store, permission `storage` dan `bootstrap/cache`. Jalankan `invman:security-audit`, `license:status` sesuai daftar command aplikasi, cache config/route/view; verifikasi hasil dan host lisensi. Hindari cache env dari build yang berbeda.
6. Worker queue sesuai driver produksi di supervisor/systemd; restart worker setelah rilis. Cron `schedule:run` setiap menit. Jadwal source: `loans:expire-holds` setiap menit, `loans:notify-overdue` per jam, `tickets:escalate-sla` per jam, memakai withoutOverlapping dan validasi lisensi. Cache lock bersama diperlukan bila multi-server. Uji notifikasi terkontrol ke tujuan uji yang sah.
7. Smoke setelah maintenance dibuka secara terbatas: login normal semua role; halaman/dashboard/filter; satu pengajuan uji untuk tiap jenis aset; approval; QR; konfirmasi dua pihak; return kondisi baik/rusak; tiket/hold; satu alur WO bila sudah diizinkan; private attachment/PDF dan cross-unit denied. Pantau 419/403/500, failed jobs, queue lag, storage dan SLA. Tutup data uji sesuai prosedur tanpa menghapus audit.

### Pemulihan tanpa kehilangan histori

Jika migrasi gagal, pertahankan maintenance dan worker berhenti; ambil log, jangan menjalankan down/drop, evaluasi partial DDL MySQL dan perbaiki maju. Restore hanya ke database terpisah lebih dulu, validasi, lalu lakukan keputusan cutover oleh pemilik rilis berdasarkan backup/binlog dan write freeze.

**Rollback kode ke main lama saat tiket dinamis sudah berjalan berisiko melewati gate baru**, sebab kode lama tidak memahami approval dinamis. Jangan sekadar menukar release lama dan membuka traffic. Bekukan semua penulisan/worker, pertahankan tabel/history baru, pilih forward-fix atau restore konsisten yang sudah direhearsal. Database down #32 menghapus tabel histori baru dan **tidak boleh digunakan pada data nyata**. Restore lama setelah write baru dapat menghilangkan transaksi; rekonsiliasi/PITR harus direncanakan sebelum perubahan layanan.

## F. Open Issues

| ID | Prioritas | Status / dampak / tindakan minimum |
| --- | --- | --- |
| OPEN-01 | **P1 untuk WO YTB** | Rejected approval tidak dapat dikoreksi/resubmit pada tiket yang sama. Fail-closed aman dari bypass, tetapi perjalanan bisnis buntu. Tentukan revisi berversi dengan audit, implementasikan terpisah dan uji reject→correct→resubmit. Tidak diatasi dengan menghapus/reset keputusan |
| OPEN-02 | P2 / blocker UAT | Browser authenticated normal dan polling semua peran/viewport belum diverifikasi. Sediakan staging + alur secure auth, jalankan otomatis; pengguna tidak diminta melakukan UAT bisnis sendiri |
| OPEN-03 | P2 / gap bisnis | Vendor eksternal, estimasi biaya, approval nominal dan diagnosis/material sebelum approval belum tersedia. Batasi cakupan rilis eksplisit atau implementasi dan uji terpisah; jangan klaim formulir YTB lengkap |
| OPEN-04 | P2 / gap dispatch | Laporan tanpa aset di unit tidak dapat diberikan ke teknisi pusat yang hanya punya membership pengelola. Perlu desain cakupan berotorisasi; jangan memberi role Fasilitas luas sebagai jalan pintas |
| OPEN-05 | P2 | Audit historis nama jabatan/unit/assignment belum snapshot penuh; konflik beberapa atasan yang sama-sama cocok memakai assignment pertama. Tetapkan governance/validasi dan tambah audit sebelum penggunaan organisasi kompleks |
| OPEN-06 | P2 / deployment | TLS/session/proxy/license/queue/cron/storage/notification delivery dan backup restore produksi belum terbukti. Tutup gate infra dan uji staging sepadan |
| OPEN-07 | P2 existing main | Advisory medium symfony/mime pada lockfile main; sudah terpatch di kedua PR. Pastikan artefak yang dirilis memuat patch |
| OPEN-08 | P3 | Logo pada screenshot login desktop terlihat sempit dibanding mobile. Ditunda; tidak mengubah branding saat audit keamanan |

Tidak ada P0 ditemukan dalam cakupan yang diuji; itu bukan pernyataan tidak ada P0 di luar cakupan. P1 keamanan yang direproduksi sudah diperbaiki **di branch integrasi**; tetap terbuka pada head asli sampai perubahan diadopsi dan ditinjau. Fitur pemulihan penolakan sengaja tidak direka melalui refactor besar tanpa aturan bisnis yang jelas.

## G. Final Verdict

| Pertanyaan | Jawaban yang dapat dipertanggungjawabkan |
| --- | --- |
| 1. Apakah InvMan existing aman digunakan? | **Belum dapat disertifikasi aman.** Main mempunyai temuan backend/self-approval dan advisory dependency; konfigurasi/data/server live tidak diperiksa. Jangan samakan kuatnya regresi peminjaman integrasi dengan keamanan produksi existing |
| 2. Apakah PR #31 layak merge? | **Belum direkomendasikan merge sekarang.** UX/backend peran mempunyai bukti baik, tetapi MySQL fixture perlu perbaikan dan browser staging normal belum selesai; temuan existing/integrasi perlu diadopsi sesuai cakupan rilis. Pertahankan draft/review |
| 3. Apakah PR #32 layak merge? | **NO-GO pada head asli**: temuan P1 dan gap rejection; foundation dapat diuji di staging setelah patch audit, bukan approval YTB produksi |
| 4. Apakah gabungannya aman? | **NO-GO untuk production.** Integrasi sudah diuji dan ditambal; P1 alur reject serta gate UAT/infra masih terbuka |
| 5. Apakah approval dinamis siap untuk WO YTB? | **Belum.** Happy path terkonfigurasi lulus enam unit, tetapi reject/revisi, vendor/biaya dan dispatch tidak memenuhi perjalanan lengkap |
| 6. Risiko kehilangan data/bypass kewenangan? | Tidak teramati pada tes akhir yang dicakup; upgrade sintetis mempertahankan data dan gate yang ditemukan ditambal. **Risiko nyata tetap ada pada head asli dan rollback kode lama/DB down**, serta cakupan race/infra yang belum diuji |
| 7. Deploy production sekarang? | **Tidak direkomendasikan; tidak dilakukan** |
| 8. Tindakan minimum? | Adopsi/review FIX-01–12; selesaikan OPEN-01 untuk aktivasi WO; tutup UAT normal dan infra/restore; tetapkan cakupan vendor/biaya/dispatch; CI final hijau dan review manusia sebelum keputusan merge/rilis |

Rilis UI/peminjaman existing **dapat dievaluasi terpisah** setelah patch yang relevan, UAT normal, dan gate deployment selesai, dengan workflow dinamis tetap tidak dipublikasikan. Saat ini itu kemungkinan cakupan CONDITIONAL GO di masa depan, **bukan GO yang sudah diberikan oleh audit ini**.
