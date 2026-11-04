# Evidence lanjutan — 3 November 2026

Baseline PR #33: `182c12e`. Seluruh source/tes/evidence lanjutan tersimpan pada commit `c3dcc0916cdbbb963a7cbff5825061bd3c1506af` di branch PR #33. Hash 39 file terverifikasi cocok. Status CI tercatat di laporan lanjutan, bukan diambil dari baseline.

Lihat [laporan lanjutan](../../FOLLOWUP-READINESS-2026-11-03.md) untuk batas tiap bukti. Suite DB memakai database disposable, tidak diarahkan ke produksi.

Reproduksi suite: `php artisan test --no-ansi`. Untuk MySQL siapkan DB disposable dan set DB_CONNECTION/DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD sesuai QA. Unit khusus foreign-key MySQL memang dilewati pada SQLite.

Probe concurrency: database bersih bernama `*_tests` atau `*_ci`, APP_ENV=testing, INVMAN_CONCURRENCY_TEST=1, jalankan `php tests/Support/work-order-concurrency.php` setelah migrate. Probe upgrade: database benar-benar kosong bernama `*_upgrade`, APP_ENV=testing, INVMAN_UPGRADE_TEST=1, jalankan `php tests/Support/work-order-upgrade.php`. Probe menolak lingkungan yang tidak sesuai. Jangan memakai migrate:fresh pada DB yang berisi data pengguna.

HTTP dilakukan pada PHP web server lokal dengan SQLite terisolasi, session cookie nyata, CSRF normal, route/Livewire login normal, fixture lisensi valid seperti baseline tests, dan kredensial QA acak. Turnstile memakai test keys resmi serta dummy token dari dokumentasi Cloudflare: https://developers.cloudflare.com/turnstile/troubleshooting/testing/ . Backend menghubungi provider asli tanpa Http fake. Ini bukan UAT widget/browser, bukan bukti provider produksi; credential/license QA tidak disertakan.

source-checksums.txt mengidentifikasi tepat file source/tes yang telah diperiksa. SHA256SUMS mencakup evidence lokal ini, bukan tanda tangan tersertifikasi.
