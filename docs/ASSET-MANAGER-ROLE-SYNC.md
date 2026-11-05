# Pengelola Barang, Peran Shield, dan Penugasan Jabatan

## Tiga konsep berbeda

1. **Role teknis `pengelola_barang` (Filament Shield):** menyediakan permission untuk menu **Barang** dan **Barang Satuan**. Role ini tetap diperlukan untuk kontrol izin fitur, tetapi **tidak perlu dipilih manual dalam form Pengguna**.
2. **Pengguna → Kelompok Aset yang Dikelola:** relasi antara akun dan kelompok IT/Perpustakaan/dll; menentukan aset dan transaksi yang berada dalam kewenangannya. Keanggotaan terakhir dicabut = role teknis otomatis dicabut. Jika masih menjadi anggota kelompok lain, role tetap aktif.
3. **Penugasan Jabatan → Cakupan Jabatan: Kelompok Aset:** membatasi keputusan dalam workflow Work Order sesuai jabatan aktif, unit, dan pengelola. Pilihan ini **tidak** membuat keanggotaan inventaris atau otomatis memberikan role Shield.

## Jalur pengaturan

- Pengaturan → Pengguna: atur Peran Dasar (admin/fasilitas/sarpras) dan Kelompok Aset yang Dikelola. Role `pengelola_barang` hanya ditampilkan sebagai status otomatis, dan tidak ikut diubah ketika administrator memperbarui peran dasar.
- Data Aset → Pengelola Barang: atur daftar User Pengelola pada suatu kelompok. Sistem akan memberi role pada anggota baru, dan mencabutnya dari mantan anggota jika tidak tersisa kelompok lain.
- Pengaturan → Penugasan Jabatan: pilih jabatan beserta cakupan Work Order. Tidak mengubah izin inventaris.

Sinkronisasi menggunakan custom Eloquent pivot model agar `attach`, `detach`, `sync`, dan `syncWithoutDetaching` dari **kedua arah** menjalankan pemeriksaan role. Menghapus master kelompok melalui Eloquent juga merekonsiliasi peran anggota. Perubahan yang dilakukan langsung lewat raw SQL tidak menjalankan event Eloquent; jalankan `php artisan db:seed --class=RoleSeeder --force` untuk rekonsiliasi ulang setelah backup bila raw data diubah.

Migrasi `2026_11_05_210000_reconcile_asset_manager_roles` merekonsiliasi data existing satu kali tanpa mengubah permission Shield. Seeder role juga merekonsiliasi tanpa mengembalikan permission yang dicabut di halaman Peran.

## Batasan dan keamanan

- Hak akses inventaris harus memenuhi **permission Shield** **dan** keanggotaan kelompok. Tanpa keduanya, record tidak boleh dikelola.
- Role staf Fasilitas, Sarpras, dan Admin tidak diubah oleh sinkronisasi ini. Sarpras tetap dibatasi unit, termasuk jika kebetulan mempunyai keanggotaan Pengelola Barang.
- Penugasan Work Order mengacu kepada `work_order_assignments` dan workflow terbit, bukan keanggotaan inventaris semata.
- Pencabutan kelompok terakhir dapat langsung menghilangkan menu Barang bagi akun pengelola murni; riwayat inventaris dan tiket lama tidak dihapus.

## Pemeriksaan

Tes otomatis menguji penambahan/pencabutan melalui kedua arah, pergantian anggota, beberapa kelompok, penghapusan master kelompok, edit form Pengguna, pemisahan jabatan Work Order, dan perlindungan permission Shield yang sudah diedit manual.
