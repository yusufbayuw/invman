# InvMan — Lanjutan PR #33, 3 November 2026

**Keputusan diperbarui: NO-GO untuk production.** Tiga FAIL bisnis awal telah diperbaiki dan lulus pada lapisan backend/Livewire. DEP-07 berhasil diuji melalui HTTP normal dengan provider CAPTCHA asli. Tujuh BLOCKED masih terbuka. Tidak ada merge atau deployment.

Pembaruan ini memakai baseline PR #33 `182c12e5b8e9f5ede7bc239a409a9e745187d516`. Audit A–G awal tidak diulang. Pengujian regresi diulang karena ada perubahan pada approval, schema, otorisasi teknisi dan histori. **Kode, dokumen dan evidence lanjutan masih lokal; head PR #33 belum berubah dan belum ada CI GitHub untuk perubahan ini.** CI baseline tidak boleh dipakai untuk menyatakan perubahan baru lulus CI.

## Hasil untuk tiga FAIL

| Skenario | Hasil lokal dan batas |
| --- | --- |
| WO-06: rejected → revisi → resubmit | PASS backend/Livewire. Pelapor aktif memperbaiki isi laporan pada tiket sama. Keputusan terdahulu tidak dihapus/ditimpa; langkah lama diarsipkan dan langkah baru dimulai pending. Semua persetujuan laporan diulang ketika isi laporan berubah. Penolakan akhir dikoreksi oleh teknisi yang ditugaskan, kembali ke in_progress, wajib hasil baru dan verifikasi pelapor baru sebelum pengesahan ulang. Safety hold tidak dilepas saat revisi. |
| WO-07: vendor/tinjauan/estimasi/approval nominal | PASS backend/Livewire. Pelaksana internal/vendor eksternal, diagnosis awal, material dan total rupiah dicatat dalam versi estimasi immutable. Admin dapat mewajibkan tahap `requires_estimate` sebelum penugasan. Keputusan nominal mengikat ID estimasi yang diperiksa, pencatat tidak boleh menyetujui nominal sendiri, perubahan estimasi saat modal lama terbuka ditolak. Estimasi tidak bisa diganti sesudah nominal disetujui; revisi laporan membuat estimasi lama tidak berlaku untuk siklus baru. Ini belum menjadi sistem pembelian/pembayaran, pencatatan realisasi biaya, akun vendor, atau routing otomatis berdasarkan ambang nominal. |
| WO-08: laporan tanpa aset → teknisi pusat | PASS backend/Livewire. Workflow mempunyai `dispatch_job_title_id` opsional. Teknisi lintas unit harus anggota tim aktif, memiliki assignment/jabatan aktif sesuai cakupan, dan memperoleh penugasan sah. Versi workflow milik tiket dipertahankan. Kolom kosong tidak memberi hak lintas unit baru. Revokasi/salah unit/anggota nonaktif ditolak. Seeder demo mencontohkan jabatan terpisah “Teknisi Laporan Umum”; tidak memberi role Fasilitas atau membership inventaris kepada teknisi. |

## Tindak lanjut delapan BLOCKED

| ID | Status lanjutan | Bukti atau kebutuhan tersisa |
| --- | --- | --- |
| AUTH-08 | BLOCKED | Reload aktor di dalam transaksi tiket dan guard stale approval/estimasi ditambahkan. Dua proses resubmit hanya menghasilkan satu revisi/event. Namun seluruh urutan revokasi role/jabatan/publish lintas transaksi belum dibuktikan. Lock tiket tidak diklaim mengunci seluruh konfigurasi organisasi. Perlu spesifikasi ordering revokasi dan probe interleaving tambahan. |
| UI-04 | BLOCKED | Aksi baru diuji dengan komponen Filament asli, bukan browser. Playwright tersedia tetapi Chromium tidak ada dan unduhan runtime gagal sebagai arsip tidak lengkap. Belum ada UAT browser semua role, form/publish/approval/revisi/PDF pada 360/390/430/1366. Tidak ada screenshot dashboard baru yang diklaim. |
| UI-05 | BLOCKED | Login HTTP normal dan tiga refresh Livewire authenticated memperoleh 200; missing/wrong CSRF dan forged snapshot memperoleh 419. Ini tidak membuktikan polling JS, modal, console atau failed requests pada browser sesungguhnya. |
| UI-06 | BLOCKED | Suite backend booking/QR/handover/return lulus ulang. UAT browser transaksi end-to-end tetap memerlukan runtime browser. |
| DEP-04 | BLOCKED | Tidak ada akses staging/infra setara production untuk SMTP/push/broadcast, worker daemon, cron, lisensi/domain produksi, TLS/proxy/session/cookie dan storage produksi. QA memakai mail array/fake dan lisensi fixture yang validasi middleware-nya tetap berjalan. |
| DEP-05 | BLOCKED | Upgrade sintetis lulus, termasuk baris approval rejected sebelum migration baru. Backup/PITR/restore volume produksi belum tersedia, sehingga rehearsal pemulihan konsisten produksi belum dibuktikan. |
| DEP-06 | BLOCKED | Race approval/draft/resubmit terarah lulus. Tidak ada hasil stress/load, disk full, failover atau SLA kapasitas produksi; tidak ada angka kapasitas yang dikarang. |
| DEP-07 | PASS — HTTP QA | GET login 200 → normal Livewire authenticate 200 → dashboard authenticated 200, session cookies berubah. Validasi server-side benar-benar menghubungi Cloudflare siteverify dengan kunci uji resmi, tanpa Http fake atau bypass auth/CSRF. Token dummy resmi disuplai ke komponen login; tidak mengklaim widget browser telah diuji. Kunci/provider produksi belum divalidasi. |

**Matriks 59 skenario lokal: 52 PASS / 0 FAIL / 7 BLOCKED.** PASS tetap dibatasi lapisan yang disebutkan; bukan keputusan GO. Matriks remote pada baseline tetap 48/3/8 hingga perubahan disimpan.

## Bukti pengujian akhir

| Pengujian | Hasil |
| --- | --- |
| Suite MySQL 8.0.46, kode aplikasi akhir | **269 PASS, 2.124 assertions** — [log](evidence/2026-11-03-followup/mysql-tests.txt) |
| Suite SQLite, kode aplikasi akhir | **268 PASS, 1 skipped khusus MySQL, 2.116 assertions** — [log](evidence/2026-11-03-followup/sqlite-tests.txt) |
| Regresi keamanan/bisnis terarah | **40 PASS, 662 assertions** — [log](evidence/2026-11-03-followup/security-business-tests.txt). Subset, tidak dijumlahkan dengan suite penuh. |
| Concurrency dua proses MySQL | Approval: committed + rejected, satu event; resubmit: committed + rejected, satu revisi/event; draft global: committed + rejected, satu draft — [JSON](evidence/2026-11-03-followup/concurrency.json) |
| Upgrade | 64 migration main → schema awal #32 berisi rejected approval → tiga migration baru: data/kredensial/role/histori tetap, rejected approval tetap revision 1 aktif dengan aktor/alasan/waktu lama. Tiket legacy tidak otomatis didaftarkan ke workflow. Seeder dua kali idempotent, contoh tidak auto-publish, production demo guard PASS — [log](evidence/2026-11-03-followup/upgrade.txt) |
| HTTP provider/session/CSRF | Authenticated true, dashboard 200, refresh [200,200,200], negative checks 419 — [JSON](evidence/2026-11-03-followup/provider-login.json) |
| PHP syntax / whitespace | Seluruh file PHP yang berubah lulus lint; git diff --check bersih — [lint](evidence/2026-11-03-followup/php-lint.txt) |
| Browser | BLOCKED, bukan hasil UAT — [alasan](evidence/2026-11-03-followup/browser-blocker.txt) |
| CI/build/advisory terbaru | CI GitHub baru belum dijalankan karena penyimpanan remote ditolak peninjau otomatis. Baseline punya CI/build/advisory evidence sebelumnya; itu bukan bukti CI untuk patch baru. Dependency lock/frontend source tidak berubah. |

Tes baru: `WorkOrderRevisionTest` dan `WorkOrderEstimateTest`; perluasan `WorkOrderReadinessTest`, probe concurrency dan probe upgrade. Suite existing juga menegakkan mass assignment allowlist, batas unit/role, private attachment/PDF, self-approval, akun nonaktif, gate final dan safety hold.

## Perubahan dan kontrol integritas

- Approval aktif terpisah dari history melalui `superseded_at` dan `revision`; unique key menjadi ticket/step/revision. Keputusan finalized dan langkah arsip tidak bisa dimutasi melalui model kecuali pengarsipan sah saat revisi. Revision dan estimate history tidak dapat diubah/dihapus melalui model. Proteksi model tidak melawan DBA/raw SQL.
- Revision menyimpan aktor, rejected approval ID, alasan serta snapshot before/after isi laporan atau hasil pekerjaan. Referensi workflow dan kandidat lama dipertahankan; admin harus memakai pengalihan beralasan jika assignment kandidat berubah. Versi published baru tidak dipakai diam-diam untuk tiket lama.
- Service mengunci tiket, memeriksa kembali otorisasi/status, menolak resubmit duplikat/ID lama, dan menolak perubahan reporter/unit/status/aset melalui payload revisi. Tidak ada endpoint reset approval.
- Modal persetujuan menyimpan ID approval dan ID estimasi yang diperiksa; submit tidak otomatis mengambil tahap/nominal baru yang muncul setelah modal dibuka.
- Histori keputusan, alasan penolakan/revisi dan estimasi dicetak pada PDF dan ditampilkan melalui komponen Filament native. Output string tetap di-escape dan PDF tetap privat dengan remote access nonaktif.
- Tidak ada dependency baru atau perubahan auth/CSRF/CAPTCHA produk. QA HTTP menggunakan fixture lisensi dan kredensial sintetis yang dibuat lokal, bukan data/akun produksi. Fixture/kredensial tersebut tidak dimasukkan ke perubahan repository.

## Migration dan konfigurasi saat nanti diizinkan rilis

Tiga migration additive baru:

1. `2026_11_03_120000_add_work_order_revision_history.php`: revision/superseded approval + tabel snapshot revisi.
2. `2026_11_03_121000_add_explicit_work_order_dispatch_title.php`: jabatan dispatch opsional pada workflow.
3. `2026_11_03_122000_create_work_order_estimates.php`: tabel versi estimasi + referensi estimasi yang disetujui.

Default tiket/workflow lama tidak otomatis mewajibkan estimasi atau mengizinkan dispatch baru. Published workflow immutable: buat versi baru lalu konfigurasi jabatan dispatch dan tahap approval nominal dengan pemegang jabatan yang sesuai. Demo tetap draft dan opt-in non-production; seeder tidak menimpa akun atau konfigurasi published existing.

Sebelum deploy tetap wajib backup DB+storage, write freeze terencana saat perubahan DDL, pemeriksaan konfigurasi dan smoke di staging setara produksi, serta backup/restore rehearsal. Down migration revision/estimate **menolak** penghapusan ketika history telah dipakai; jangan memaksa hapus data agar rollback lolos. Gunakan forward-fix atau restore kode+DB+storage yang konsisten. Menjalankan kode baseline yang tidak memfilter history revisi pada DB baru dapat salah membaca gate; jangan rollback kode saja ketika data revisi sudah aktif.

## Status penyimpanan dan pekerjaan berikutnya

Peninjau persetujuan otomatis menolak pembuatan GitHub tree karena menilai belum ada izin eksplisit mengunggah perubahan ke remote. Tujuan sudah diperiksa: repository publik `yusufbayuw/invman` (ID 1008496621), akun terhubung pemilik `yusufbayuw` dengan izin admin, head PR #33 cocok baseline. Payload hanya source/tes proyek, tanpa .env/kredensial/data produksi. Penolakan tetap berlaku; tidak dicoba jalur alternatif untuk melewatinya.

Hasil konkret siap ditinjau lokal. Langkah remote yang memerlukan persetujuan: simpan kode, tes dan laporan ke **branch PR #33 yang sama**, jalankan CI dan periksa hasilnya; **tanpa merge/deploy**. Setelah itu lanjutkan tujuh gate yang masih BLOCKED. Keputusan rilis harus diperbarui lagi dengan evidence gate yang tersisa; CI hijau sendiri tidak cukup.
