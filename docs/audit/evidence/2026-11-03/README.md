# Bukti audit 3 November 2026

Laporan utama: [A–G](../../PRODUCTION-READINESS-2026-11-03.md); [matriks](../../TEST-MATRIX-2026-11-03.md).

Screenshot 01–04 diambil dan diinspeksi pada audit ini dari aplikasi Laravel di runtime terisolasi. Screenshot mobile memakai iframe CSS 360/390/430 px; desktop jadwal 1366 px, display scale 95%. Tidak ada screenshot authenticated UAT, fixture login, atau data pegawai asli. File JPEG menyimpan byte capture asli, bukan gambar rekonstruksi.

Log `*-before` merupakan kegagalan regresi yang sengaja direproduksi sebelum patch, bukan status suite akhir. `concurrency-before.json` menunjukkan approval satu kali sudah aman, sedangkan draft global awalnya duplikat. `concurrency-after.json` adalah dua worker PHP nyata pada MySQL setelah patch. Upgrade JSON memeriksa skema lama sintetis, bukan salinan produksi.

## Reproduksi pada database disposable

Gunakan PHP 8.3 dan extension sesuai Composer/CI, MySQL 8.0 strict, Node 22, env/key khusus pengujian, lalu:

```sh
composer install --no-interaction --prefer-dist
npm ci
npm run build
php artisan test
```

Suite default memakai SQLite memory; satu FK history test khusus MySQL. Untuk MySQL, buat database dengan nama `_ci`/`_tests`, set `APP_ENV=testing`, `APP_URL=http://invman.test`, `DB_CONNECTION=mysql` dan kredensial database disposable; ikuti job `mysql-regression` di `.github/workflows/ci.yml` untuk seluruh suite.

Probe tambahan (jangan arahkan ke DB live atau shared):

```sh
# Database test terisolasi harus sudah dimigrasikan, tanpa workflow global fixture.
INVMAN_CONCURRENCY_TEST=1 php tests/Support/work-order-concurrency.php
# Database *_upgrade harus kosong; probe menolak jika tabel migrations sudah ada.
INVMAN_UPGRADE_TEST=1 php tests/Support/work-order-upgrade.php
```

Keduanya menolak non-testing, driver selain MySQL, dan suffix database yang tidak sesuai. Probe upgrade tidak menghapus tabel. Probe concurrency membuat data sintetis dan tidak menghapus data existing; gunakan database baru/fresh khusus pengujian sebagaimana CI, bukan database yang dipakai orang lain.

`http-session.txt` adalah black-box Guzzle HTTP ke web server nyata, bukan test kernel yang menonaktifkan CSRF. Checksum palsu menghasilkan 419 native Livewire pada APP_DEBUG=false. CAPTCHA provider failure dicatat terpisah sebagai BLOCKED. Hasil auth komponen tidak boleh disebut login browser berhasil.

Audit dependency merupakan hasil saat audit, bukan jaminan keamanan absolut. `production-config-audit.txt` memakai nilai env produksi sintetis; tidak menginspeksi server live. Cache-build hanya compile config/route/view; cache-clear menggunakan store array pada host tanpa DB default.
