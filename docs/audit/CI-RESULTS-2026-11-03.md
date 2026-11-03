# Bukti CI integrasi — 3 November 2026

PR: [#33](https://github.com/yusufbayuw/invman/pull/33). Status audit produksi: **NO-GO**, meskipun seluruh job CI di bawah sukses.

## Commit dan run yang diverifikasi

- Integrasi parent #31/#32: [e48157f](https://github.com/yusufbayuw/invman/commit/e48157fa7976ab09bb29596a9366a33dac7c8d2e).
- Remediasi kode + regresi/CI: [439bfb5](https://github.com/yusufbayuw/invman/commit/439bfb593857f03a41c5b9a2faffa424ccb41f48).
- Commit input CI (kode, laporan, dan bukti browser lokal): [4902304](https://github.com/yusufbayuw/invman/commit/49023044d6e220fb5f0c01cf1ca7c00217107d25).
- [InvMan Quality Guard #139](https://github.com/yusufbayuw/invman/actions/runs/41569321046): **completed / success**.

| Job | ID GitHub | Kesimpulan |
| --- | --- | --- |
| php-tests | 125698287490 | SUCCESS |
| mysql-regression | 125698287182 | SUCCESS |

Job php-tests mencakup Composer validate/install/audit produksi, konfigurasi aman sintetis, lint PHP, npm ci/audit, build Vite dan suite lengkap SQLite. Langkah peringatan vulnerable build tooling dilewati karena audit npm berhasil.

Job mysql-regression mencakup migrasi MySQL kosong, npm ci/build, seluruh suite pada MySQL, migrasi fresh **database CI disposable** sebelum probe dua proses, lalu database upgrade terpisah dengan 64 migrasi lama, data sintetis dan seeder dua kali. Tidak ada operasi terhadap database produksi.

## Ekstrak log job yang diambil melalui GitHub

### PHP / SQLite / audit

```text
2026-11-03T02:58:25.5507537Z No security vulnerability advisories found.
2026-11-03T02:58:44.1471340Z found 0 vulnerabilities
2026-11-03T02:58:44.3808583Z found 0 vulnerabilities
2026-11-03T02:58:44.7243672Z found 0 vulnerabilities
2026-11-03T02:59:12.1869118Z   Tests:    1 skipped, 256 passed (2009 assertions)
```

### MySQL / concurrency / upgrade

```text
2026-11-03T03:00:09.5946405Z   Tests:    257 passed (2017 assertions)
2026-11-03T03:00:17.7628289Z         "pass": true,
2026-11-03T03:00:17.7630819Z         "pass": true,
2026-11-03T03:00:17.7632383Z         "drafts": 1
2026-11-03T03:00:30.5016669Z     "legacy_migrations": 64,
2026-11-03T03:00:30.5016947Z     "upgrade": "PASS",
2026-11-03T03:00:30.5017290Z     "existing_rows_password_roles_history": "unchanged",
2026-11-03T03:00:30.5020933Z     "production_demo_guard": "PASS"
```

## Batas bukti

CI hanya membuktikan pekerjaan yang dijalankan. Authenticated browser UAT, polling session staging normal, infrastruktur produksi dan restore backup tetap BLOCKED. Revisi setelah rejection, vendor/biaya dan dispatch assetless yang belum didukung tetap FAIL kebutuhan bisnis pada [matriks](TEST-MATRIX-2026-11-03.md).

Dokumen ini ditambahkan setelah run #139 selesai. Perubahannya hanya dokumentasi; kode yang diuji tidak diubah. Lihat status head final pada [Checks PR #33](https://github.com/yusufbayuw/invman/pull/33/checks). Jangan merge atau deploy otomatis.
