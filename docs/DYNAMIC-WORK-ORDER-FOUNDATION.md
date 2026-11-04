# Work Order dinamis — fondasi tahap awal

Referensi arsitektur: SPMB `UnitConfiguration`, `UnitConfigurationService`, dan pola draft / published / version / immutable configuration.

## Konfigurasi
- Menu Pengaturan menyediakan Jabatan Work Order, Penugasan Jabatan, dan Alur Persetujuan Work Order.
- Administrator membuat draft dengan scope seluruh organisasi atau unit; mengatur urutan tahap `start` dan `final`, resolver `reporter_supervisor` atau `job_title`, kemudian mempublikasikannya.
- Versi published tidak dapat diubah atau dihapus. Tiket baru mengikat langkah dan calon approver dari versi yang berlaku saat laporan dibuat.
- Tanpa workflow published, alur Maintenance V3 lama tetap berjalan.
- Akses approve/close dapat dibatasi kepada pemegang jabatan, selain daftar approval sebelum pekerjaan/pengesahan.
- Penolakan memblokir kelanjutan; tidak ada bypass otomatis. Tindakan approval dan riwayat aktor dicatat dalam ticket events.

## Batasan versi awal
- Penyelesaian penolakan, pergantian approver pada tiket aktif, approval biaya/vendor, export/import konfigurasi, dan preview simulasi UI belum ditambahkan. Jangan aktifkan workflow organisasi produksi sebelum tahap tersebut diuji bersama staf.
- Belum ada tes CI untuk branch ini; wajib verifikasi MySQL, SQLite, PHP lint, route, dan UAT staging.
- Dashboard PR #31 tidak diubah; integrasi dan penyatuan branch dilakukan setelah lolos pengujian terpisah.

## Tambahan pengamanan
- Seeder contoh berjalan hanya di non-production dengan `SEED_DEMO_USERS=true` dan password kompleks dari environment; konfigurasi approval tetap draft, tidak publish otomatis.
- Keputusan approval hanya dapat dilakukan oleh kandidat snapshot yang masih memegang penugasan aktif. Admin dapat mengalihkan langkah pending ke pemegang jabatan sah, dengan alasan dan audit.
- Alur otomatis dari pengembalian rusak juga menempelkan versi konfigurasi Work Order jika sudah published.
- Publish menolak jabatan tanpa pemegang aktif; persetujuan atasan per pelapor masih perlu diuji per unit dan cakupan.
- Vendor, estimasi biaya, simulasi lintas unit, dan pemulihan penolakan belum lengkap. Tetap Draft, jangan deploy produksi sebelum UAT.
