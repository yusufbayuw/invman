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
