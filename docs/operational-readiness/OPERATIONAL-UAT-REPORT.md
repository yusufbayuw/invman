# Laporan Operational UAT InvMan — 4 November 2026

**Keputusan kandidat: staging sintetis GO; pilot terbatas NO-GO; production seluruh unit NO-GO.** Status CI kandidat: success.

Revisi aplikasi yang menjadi sumber bukti adalah 4345107. Commit paket laporan sesudahnya hanya menambah dokumen/evidence; pemeriksaan pada head PR juga harus hijau. source-revision.json membedakan SHA checkout dari SHA merge event GitHub.

## Revisi dan batas verifikasi

- Main GitHub terbaru diverifikasi pada `e7b8b9ac3508ab67570c8c75bf9f2c86db844676`; PR #31/#32/#33 dan Quality Guard #150 diverifikasi pada awal audit (81a9666). Selama audit, PR #35/#36 masuk ke main; baseline diperbarui dan kandidat diuji ulang. Angka historis 52/0/7 tidak dipakai sebagai hasil saat ini. [Bukti baseline awal](evidence/2026-11-04/github-baseline-ci150.json); [baseline yang diperbarui](evidence/2026-11-04/baseline-updated.json).
- Kandidat perbaikan: `4345107993ad1dd83d678789ae34ab6546fa8f1b`, [PR #34](https://github.com/yusufbayuw/invman/pull/34). [UAT](https://github.com/yusufbayuw/invman/actions/runs/41792438401); [Quality Guard](https://github.com/yusufbayuw/invman/actions/runs/41792438399). Tidak ada merge, deployment, konfigurasi production, penggunaan kredensial production, atau operasi pada data nyata.
- QA lokal: clone terisolasi, PHP 8.4, SQLite untuk browser lokal dan memory untuk suite. MySQL 8.4 lokal berhasil dipersiapkan lalu dihentikan. QA CI kandidat terakhir memakai PHP 8.4, Nginx dan pool PHP-FPM terisolasi melalui socket privat, MySQL 8.0.46, Chromium; Quality Guard tetap menguji PHP 8.3; Edge 156 juga benar-benar dijalankan lokal. Dependensi QA dipasang dari lockfile: Laravel 12.69.3, Filament 3.3.57 dan Livewire 3.8.10.
- Fixture: enam unit operasional bawaan repository ditambah Organ Yayasan; 28 akun sintetis pada revisi akhir, termasuk Sarpras per unit, fasilitas, staf/supervisor IT dan Perpustakaan, manager, koordinator/teknisi multi-jabatan, multi-role, pegawai tanpa role, sopir dan akun nonaktif. Nama unit mentah ada di manifest; jangan mengubah ADM/TBU menjadi nama sekolah yang tidak ada pada implementasi.
- Normal login memakai widget dan verifikasi server Turnstile dengan kunci uji resmi, serta lisensi contoh repository yang valid pada loopback. Tidak ada injected session, fake auth, atau middleware security yang dinonaktifkan. [Panduan kunci uji Cloudflare](https://developers.cloudflare.com/turnstile/troubleshooting/testing/).
- HTTP loopback QA tidak mensertifikasi TLS/cookie/proxy production. Metadata GitHub tidak mencatat staging/environment/deployment. [Discovery](evidence/2026-11-04/staging-discovery.json).

## Hasil yang dapat dipertanggungjawabkan

Browser kandidat terbaru: **195 PASS / 0 FAIL / 0 BLOCKED**, mencakup 28 persona dalam runner lintas peran. Angka runner ini tidak mencakup gate yang belum diuji dan gap kilometer akhir yang terkonfirmasi FAIL. Hasil individual bukan pernyataan full-role certification; kolom `fullRoleUat=false` sengaja dipertahankan. [Matriks lengkap](TEST-MATRIX.md). Suite lokal, MySQL CI, browser, probe service, dan verifikasi server dipisahkan.

Alur Work Order enam unit mencapai gate atasan/staf/supervisor melalui browser. Alur mendalam pada SD dan retest SMP melibatkan akun terpisah: penugasan→terima→hasil→verifikasi pelapor→pengesahan manager→tutup→PDF. State berikutnya tampil tanpa reload setelah patch. Workflow draft untuk SD juga diedit dan dipublikasikan lewat browser; dua versi estimasi vendor disimpan, lalu atasan yang berbeda menyetujui versi terbaru. Snapshot workflow tiket lama tetap tidak berubah.

Peminjaman barang, ruang dan kendaraan dijalankan melalui pengajuan, persetujuan, pemeriksaan awal, penerimaan peminjam, pengajuan kembali dan konfirmasi pengelola. Rekonsiliasi stok menunjukkan 10 total, 9 siap dan 1 perlu_perbaikan; seluruh 6 receipt menggunakan dua aktor berbeda. [Invariant browser/database](evidence/2026-11-04/ci-uat13/browser-database-invariants.json).

Delapan probe MySQL menguji 10-3=7, dua pengajuan 3 pada stok 5, 10 proses pada stok 1, peak 3+3 berurutan dengan kapasitas tersisa 2 sepanjang 09–12, overlap nyata, batas bersebelahan, draft yang stoknya berubah, dan aset nonaktif. [Probe](evidence/2026-11-04/ci-uat13/transaction-probes.json). Tidak ditemukan overbooking dalam interleaving tersebut. Parallel return, counter atomic, snapshot lama, 19 ordering revokasi kewenangan dan recovery koneksi juga diverifikasi dari log Quality Guard kandidat terbaru (dan baseline CI #150), bukan diulang sebagai hasil browser. [Bukti terurai](evidence/2026-11-04/verified-candidate-quality.json).

Queue benar-benar diproses worker; scheduler benar-benar mengekspirasi hold. Restore MySQL berhasil ke database baru dengan 70 tabel pada rehearsal QA setelah migrasi baseline terbaru, 139 FK tanpa orphan, count/digest baris identik dan private-file hashes identik. Gunakan JSON kandidat untuk count akhir. Private-file rehearsal menggunakan file QA sentinel dan file yang benar-benar ada pada disk QA; ini tidak mensertifikasi upload browser atau volume lampiran production. SMTP delivery dan cron/daemon deployment nyata tetap belum terbukti. [Recovery](evidence/2026-11-04/ci-uat13/infra-recovery.json).

Suite lokal terbaru: 304 kasus, 303 PASS, 1 khusus MySQL tidak dijalankan pada SQLite, 2388 assertion. Quality Guard kandidat menjalankan suite MySQL dan SQLite secara terpisah; jumlah tepat serta seluruh delapan probe ada pada JSON terverifikasi. Batas 128 MiB proses lokal habis pada suite yang bertambah; ulang dengan 512 MiB selesai pada 154 MiB. Ini batas proses suite, bukan bukti kebutuhan memori tiap request.

Fitur hierarki yang baru masuk main mengizinkan self-approval hanya melalui override eksplisit beralasan dengan audit. Jalur normal tetap menolak. UAT admin mengeksekusi 9 audit dengan reporter/assignee asli tetap, sedangkan pemohon unit tidak memperoleh tombol superior. Safety hold tetap aktif setelah tutup tanpa deklarasi aman dan aset tetap tidak muncul pada opsi pinjam. Kebijakan kewenangan global ini memerlukan pengesahan organisasi sebelum transaksi nyata.

## Temuan dan remediasi

| ID | Severity | Reproduksi/akar masalah | Perbaikan dan bukti | Status pada main / kandidat |
|---|---|---|---|---|
| BUG-SESSION-01 | P1 keamanan sesi | Nonaktifkan akun yang sudah login; GET panel 403; aktifkan lagi; cookie lama kembali membuka /admin tanpa login. Guard appended pada web tidak ikut stack Filament yang mandiri | Guard dipasang setelah StartSession pada kedua panel. Regression gagal sebelum dan lulus sesudah; browser yang sama setelah re-enable tetap menuju halaman login. [Before](evidence/2026-11-04/disabled-session-before.json), [after](evidence/2026-11-04/disabled-session-after.json), [regression](evidence/2026-11-04/disabled-session-regression-after.txt) | Main belum diperbaiki; kandidat diperbaiki, menunggu review/adopsi |
| BUG-WO-UI-01 | P2 operasional | Terima pekerjaan mengubah DB menjadi in_progress tetapi halaman masih triaged dan tombol finish belum tersedia. Service memakai record terkunci yang berbeda dari record cached halaman | Refresh model halaman setelah action. Regresi 45 assertion dan retest browser tanpa reload. [Before](evidence/2026-11-04/work-order-stale-ui-before.txt), [after](evidence/2026-11-04/work-order-stale-ui-after.txt), [browser](evidence/2026-11-04/ci-uat13/work-order-browser-fix-retest.json) | Main belum diperbaiki; kandidat diperbaiki, menunggu review/adopsi |
| BUG-WO-HEADER-01 | P2 operasional | Pada baseline baru, aksi header melebar hingga x=2018 pada viewport 1366 px; penugasan SMP dan override admin tidak dapat diklik | CSS hanya pada header tiket membungkus tombol. Regresi browser memeriksa semua tombol pada 360/390/430/768/1366 dan menjalankan aksi sebenarnya. [Before](evidence/2026-11-04/hierarchy-header-before.json), [after](evidence/2026-11-04/ci-uat13/browser-hierarchy.json) | Main belum diperbaiki; kandidat diperbaiki, menunggu review/adopsi |
| UX-PWA-01 | P2 UX | Banner onboarding dapat menutupi submit pada layar/form tertentu | Tombol tutup normal bekerja dan dipakai runner. Rekomendasi produk: hindari menutup CTA penting; tidak diubah demi meloloskan audit | Rekomendasi terbuka, bukan bypass security |
| BUG-PDF-ACTOR-01 | P2 integritas dokumen | Fitur override baru menyimpan pelaku asli, tetapi PDF belum menampilkan audit dan mencampur petugas ditugaskan dengan akun penerima/pencatat | PDF membedakan kedua identitas dan menampilkan tindakan, pelaku, subject, alasan, waktu tersimpan serta self-approval. [Before](evidence/2026-11-04/pdf-override-regression-before.txt), [regresi 9 assertion](evidence/2026-11-04/pdf-override-regression-after.txt), [render](evidence/2026-11-04/pdf-qa.json) | Main belum diperbaiki; kandidat diperbaiki, menunggu review/adopsi |
| PDF-PAGEBREAK-01 | P3 tampilan | Header tabel serah-terima terpisah dari data pada pergantian halaman PDF | Blok serah-terima dijaga bersama saat memperbaiki PDF; render normal dan override sudah diperiksa | Diperbaiki pada kandidat |
| AUDIT-TIMEZONE-01 | P2 kejelasan audit | CURRENT_TIMESTAMP pada audit override mengikuti waktu DB; QA SQLite mencatat UTC sedangkan tahapan aplikasi tampil Asia/Jakarta. Zona waktu tidak disimpan pada rekaman audit | PDF mempertahankan nilai tersimpan dan memberi label yang jujur; tidak mengonversi atau menulis ulang sejarah dengan asumsi. Sinkronisasi zona DB/aplikasi dan interpretasi audit harus disahkan sebelum pilot | Terbuka sebagai gate konfigurasi/semantik audit |
| ENV-PHP83-RESET | P2 verifikasi lingkungan | QA PHP 8.3 pernah mengembalikan 502 saat membuka serah-terima item; Nginx mencatat reset upstream tanpa exception aplikasi | QA terakhir memakai PHP 8.4 dan socket pool terisolasi; gunakan hasil CI terakhir. Ini tidak membuktikan akar masalah PHP 8.3 sudah diselesaikan | PHP 8.3 browser belum disertifikasi; gate lingkungan nyata tetap BLOCKED |
| GAP-VEH-01 | P2 kebutuhan bisnis | Kilometer awal tersedia opsional, kilometer akhir tidak ada pada formulir kembali | Perlu keputusan kebutuhan/policy sebelum perubahan fitur; tidak mengarang bukti kilometer akhir | Terbuka |

Percobaan CI awal yang gagal karena selector UUID, readiness Choices, proses dev-server, waitUntil=load, dan rate limit dicatat sebagai kendala runner. Runner akhir menghormati limit 5 login/menit dengan jarak 15 detik; tidak menghapus cache limiter atau memalsukan IP. Kegagalan investigasi tidak dipoles menjadi PASS aplikasi.

## Tujuh blocker historis

| Gate lama | Tindakan baru dan hasil | Batas yang masih berlaku |
|---|---|---|
| AUTH-08 | 19 interleaving MySQL commit ordering benar-benar ada dan sukses pada Quality Guard kandidat terbaru; akun/session revocation juga diperiksa lewat browser dan ditemukan/diperbaiki BUG-SESSION-01 | PASS untuk 19 ordering jalur normal yang dibuktikan; jalur override baru mempunyai gate concurrency terpisah |
| UI-04 | Edge lokal dan Chromium CI berhasil; normal-login lintas persona, form, modal dan aksi operasional, lima viewport, private PDF | PASS per skenario matriks; full-role organisasi nyata belum disertifikasi |
| UI-05 | Polling JS 3 siklus, refresh/multi-tab, 419 tanpa CSRF dan snapshot diubah, sesi expired/role dicabut/akun nonaktif diuji | Gunakan status JSON kandidat; bukan HTTP-client-only claim |
| UI-06 | Alur browser barang/ruang/kendaraan dua pihak, QR wajib login, pengembalian rusak dan rekonsiliasi DB | Skenario concurrency tetap dibedakan sebagai service/MySQL |
| DEP-04 | Worker/scheduler/private storage/notifikasi QA dieksekusi | Infrastruktur nyata, SMTP/push/broadcast/TLS/license/domain/daemon masih BLOCKED |
| DEP-05 | Dump dan restore QA sungguhan, checksum tabel dan file serta FK | Backup existing-data/volume production-equivalent, RPO/RTO masih BLOCKED |
| DEP-06 | Race MySQL dan controlled read-load baru dengan metrik | Kapasitas mixed workload pengguna nyata pada hardware/SLA production masih BLOCKED |

Empat blocker akses browser/ordering lama (AUTH-08, UI-04, UI-05, UI-06) ditutup untuk skenario QA yang dibuktikan. Tiga gate deployment (DEP-04, DEP-05, DEP-06) tetap BLOCKED untuk lingkungan nyata, meskipun bagian QA sudah dieksekusi.

## Performa dan batas kapasitas

| Request in flight | Identitas login berbeda | p95 (ms) | Error rate | Hasil QA |
|---|---|---|---|---|
| 10 | 10 | 830.60 | 0 | PASS |
| 50 | 10 | 3119.72 | 0 | PASS |
| 100 | 10 | 5131.79 | 0 | PASS |
| 250 | 10 | 12798.38 | 0 | PASS |

[Raw load](evidence/2026-11-04/ci-uat13/http-load.json). CPU/RSS adalah snapshot proses PHP/MySQL host QA dan mencakup pool sistem yang idle; jangan mengatribusikan seluruhnya ke pool aplikasi. CPU driver dicatat terpisah. Counter slow-query memakai threshold MySQL yang dicatat, sehingga 0 slow query tidak membuktikan semua query cepat. Pada percobaan lama, status Innodb_deadlocks tidak dikembalikan MySQL dan tidak boleh dianggap 0. Runner terakhir mengambil lock_deadlocks dari INNODB_METRICS dan menggunakan threshold slow query 100 ms, hanya pada DB CI disposable. Counter serta backlog queue sebelum-sesudah tersedia pada evidence; worker menguras backlog pada rehearsal recovery berikutnya. Tidak ada klaim 50/100/250 identitas atau mixed write workload selesai ketika hanya request concurrency yang diukur.

Pada 250 request baca bersamaan, p95 mencapai 12.80 detik; status PASS pada tabel berarti pengukuran selesai tanpa error HTTP, bukan memenuhi SLA operasional. Counter QA pada threshold 100 ms mencatat 30 slow queries selama rentang load, 5 tambahan row-lock waits, 0 deadlock (counter enabled), 31 job antrean sebelum/sesudah dan 0 failed job. Queue kemudian dikuras worker pada rehearsal recovery. Counter global ini belum mengidentifikasi query penyebab; profiling dan target SLA tetap wajib sebelum pilot. [Sebelum](evidence/2026-11-04/ci-uat13/load-database-before.json), [sesudah](evidence/2026-11-04/ci-uat13/load-database-after.json).

## Jawaban 17 pertanyaan wajib

| No | Pertanyaan | Jawaban |
|---|---|---|
| 1 | Apakah pengajuan peminjaman bekerja? | Ya pada jalur Sarpras yang diizinkan, sesuai bukti browser dan service. Pegawai tanpa role ditolak menurut implementasi; jangan mengklaim self-service seluruh pegawai. |
| 2 | Apakah stok selalu konsisten? | Tidak dapat menjamin kata “selalu”. Invariant yang diuji lulus: total 10, siap 9 setelah satu barang rusak, receipt dua aktor; race dan parallel return tidak menambah stok palsu. |
| 3 | Apakah ketersediaan tanggal/jam benar? | Lulus pada skenario peak simultan, rentang panjang, jadwal bersebelahan dan overlap, draft basi serta keterlambatan yang tercatat. Backend dan UI tidak disamakan; lihat matriks method scheduling. |
| 4 | Apakah peminjaman paralel menyebabkan overbooking? | Tidak pada race MySQL yang dieksekusi: stok 5/dua pengajuan 3 menerima satu; stok 1/10 proses menerima satu. Snapshot transaksi lama juga ditolak dalam CI baseline yang diverifikasi. |
| 5 | Apakah serah-terima/pengembalian aman? | Alur dua pihak barang/ruang/kendaraan dijalankan lewat browser. Enam receipt punya peminjam dan pengelola berbeda. Duplicate/replay dan parallel return diuji di backend. |
| 6 | Apakah aset rusak dicegah dipinjam ulang? | Ya pada fixture yang diuji: satu unit berstatus perlu_perbaikan, is_available=0, is_borrowable=0; stok siap9 dari 10. Pelepasan hold keselamatan mengikuti guard Work Order yang diuji. |
| 7 | Apakah reservasi ruangan/kendaraan tepat? | Alur browser utama dan regression benturan kendaraan/sopir/pendamping tersedia. Reservasi bersebelahan dan overdue memakai kebijakan fail-closed. Kilometer akhir adalah gap fitur yang belum mempunyai input. |
| 8 | Apakah semua peran dapat bekerja lewat browser? | Belum merupakan sertifikasi semua pekerjaan. Login/logout, menu, viewport dan aksi operasional sintetis tercatat per persona. Alur Fasilitas/teknisi/pelapor/manager diuji lengkap; IT/Perpustakaan diuji pada cakupan aset dan kolaborasi. Publikasi workflow, revisi estimasi vendor dan approval versi nominal oleh aktor berbeda juga diuji lewat browser. Pemetaan organisasi nyata serta seluruh alur peran tetap gate sebelum pilot. |
| 9 | Apakah jabatan/approval dinamis tidak buntu? | Konfigurasi, publish, revisi, estimasi, approver nonaktif dan no-approver diuji secara fail-closed; 19 interleaving kewenangan diverifikasi. Workflow sintetis bukan pengesahan konfigurasi jabatan YTB nyata. |
| 10 | Apakah pengguna antarunit terlindungi? | IDOR browser Sarpras SD ke tiket SMA menghasilkan404. Regression cross-unit, dokumen privat, payload tampering dan self-approval tersedia. Ini bukan klaim audit seluruh endpoint production. |
| 11 | Apakah database existing aman di-upgrade? | Upgrade sintetis legacy dan seed dua kali lulus pada CI yang diverifikasi. Database existing nyata belum diperiksa/di-upgrade; keselamatan upgrade data nyata masih BLOCKED. |
| 12 | Apakah backup bisa dipulihkan? | Ya di QA: dump MySQL ke DB baru, digest setiap tabel identik, 139 FK tanpa orphan, file privat identik. Rehearsal backup/restore sumber nyata masih BLOCKED. |
| 13 | Apakah queue/cron/notifikasi/storage berfungsi? | Worker database, schedule:run, notifikasi database dan private file restore diuji. Mailer QA memakai log. Daemon/cron OS, SMTP delivery, Web Push/broadcast dan storage deployment nyata belum diverifikasi. |
| 14 | Apakah stabil untuk banyak pengguna? | Hanya hasil pengukuran yang dicatat yang dapat dinyatakan: read-load memakai 10 identitas dengan concurrency request bertingkat, ditambah service contention probes. Belum ada sertifikasi mixed workload 50/100/250 pengguna berbeda pada hardware/SLA production. |
| 15 | Apakah siap pilot terbatas? | NO-GO. Bug pada main perlu adopsi patch yang ditinjau, lalu gate infrastruktur, recovery, organisasi dan beban representatif harus ditutup. |
| 16 | Apakah siap production seluruh unit? | NO-GO. Bukti QA sintetis tidak membuktikan kesiapan server dan operasi seluruh unit. |
| 17 | Apa minimum sebelum operasional? | Tinjau/adopsi keempat patch tanpa merge otomatis; uji pada staging resmi yang sepadan; sahkan pemetaan jabatan dan cakupan; tutup TLS/session/license/private-storage/worker/cron/notification/monitoring; lakukan upgrade existing-data clone dan restore konsisten dengan RPO/RTO; buktikan workload/SLA pilot. |

## Minimum untuk menutup NO-GO

1. Tinjau/adopsi keempat perbaikan yang diuji pada PR #34; jangan merge otomatis. Main masih memuat bug sampai proses review selesai.
2. Sediakan staging non-production yang sepadan dan identitas QA per kewenangan; berikan akses baca konfigurasi efektif yang disanitasi dan status worker/cron/storage/monitoring. Akses production tidak diperlukan untuk melanjutkan UAT.
3. Sahkan jabatan, atasan, delegasi, cakupan unit/pengelola, kebijakan override/self-approval, zona waktu audit, approver biaya dan ownership untuk seluruh peran termasuk IT/Perpustakaan. Lanjutkan otomasi alur yang belum dicakup dan commit-order concurrency jalur override baru; pengguna tidak diminta menjalankan UAT manual yang dapat diotomasi.
4. Tutup gate TLS/proxy/cookie/license, private files/persistence, queue supervision, scheduler, delivery notifikasi, logging/alerting, failed jobs dan disk.
5. Rehearsal upgrade existing-data clone dan pemulihan DB+file+kode konsisten ke target baru, dengan RPO/RTO dan pemilik pemulihan.
6. Sepakati SLA, hardware/volume dan mixed workload pilot; uji 50/100 pengguna berbeda, 250 jika kapasitas memungkinkan, dengan invariant setelah beban.

[Checklist deployment](DEPLOYMENT-CHECKLIST.md), [runbook recovery/rollback](BACKUP-RESTORE-RUNBOOK.md), [keputusan terpisah](PRODUCTION-GO-NOGO.md). Bukti lengkap beserta screenshot ada pada folder kandidat dan artefak CI; nilai credential/session/APP_KEY/token tidak diterbitkan.

Bukti ringkas kandidat: [verifikasi lintas lapisan](evidence/2026-11-04/ci-uat13/verification-summary.json), [artefak CI](https://github.com/yusufbayuw/invman/actions/runs/41792438401/artifacts/13304408864), [indeks percobaan historis](evidence/2026-11-04/historical-ci.json), [manifest SHA-256](evidence/2026-11-04/SHA256SUMS.json), dan [pemeriksaan redaksi](evidence/2026-11-04/privacy-scan.json).
