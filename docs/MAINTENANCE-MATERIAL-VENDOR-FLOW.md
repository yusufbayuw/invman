# Alur Laporan Kerusakan: Keputusan, Material, dan Servis Eksternal

## Satu tiket, beberapa keputusan

Laporan boleh terkait aset terdaftar maupun bangunan (plafon, dinding, instalasi) tanpa identitas inventaris. Lokasi ruangan bukan otomatis aset rusak. Menautkan ruangan sebagai aset dapat mengaktifkan *safety hold* terhadap peminjaman ruangan; petugas perlu menilai risiko fisik secara tepat.

Pada halaman detail tiket, pejabat dengan kewenangan penugasan memilih **Keputusan Penanganan** sebelum menyetujui pekerjaan:
- **Diperbaiki:** pelaksana internal, eksternal atau hybrid. Eksternal/hybrid mewajibkan nama serta lingkup pekerjaan vendor.
- **Direkomendasikan Diganti:** keputusan administratif, pembelian aset baru belum otomatis dilakukan.
- **Tidak Dapat Diperbaiki:** keputusan administratif tanpa memalsukan hasil kerja teknisi.
- **Ditunda:** rencana belum dapat diproses sebagai pekerjaan hingga keputusan diubah.

Untuk penggantian/tidak diperbaiki, pelapor wajib memilih **Ketahui Keputusan**; langkah persetujuan awal dan akhir yang berlaku tetap harus disahkan; pejabat berwenang lalu **Sahkan Penutupan Tanpa Perbaikan** dengan alasan. Tidak ada pengisian tanggal pekerjaan atau verifikasi pekerjaan palsu, dan safety hold yang aktif tidak dilepas otomatis. Tiket legacy tanpa rencana tetap memakai alur lama.

## Pelaksana internal dan eksternal

**Internal:** PIC menerima tugas, mengerjakan, mencatat hasil, pelapor verifikasi, pejabat final mengesahkan.

**Eksternal:** PIC internal tetap bertanggung jawab, mencatat penyerahan ke vendor, menerima hasil servis dan referensi bukti, pihak berwenang berbeda memeriksa hasil, PIC internal mencatat penyelesaian, pelapor verifikasi, pejabat final mengesahkan.

**Hybrid:** sebagian pekerjaan internal dan sebagian vendor. Hasil vendor yang ditolak memerlukan servis ulang dan pemeriksaan kembali. Vendor tidak otomatis mendapat akun aplikasi. Foto/PDF dapat dilampirkan lewat Percakapan tiket privat.

Jika rencana sudah dicatat, jenis pelaksana dan nama vendor pada estimasi biaya tidak boleh bertentangan dengan rencana yang akan disetujui.

## Kebutuhan material: tersedia sebagian

Pada detail tiket terdapat tab **Kebutuhan Material** dan **Pembelian dan Pemeriksaan Material**. Petugas mengisi kebutuhan per baris, jumlah tersedia yang dikonfirmasi secara fisik, dan kekurangan.

Contoh: pekerjaan membutuhkan 10 papan plafon. Stok tersedia/dialokasikan 4, maka diminta pembelian 6. Barang datang 6 tetapi QC hanya menerima 5; kekurangan menjadi 1. Ajukan pembelian pengganti 1 dan lakukan penerimaan serta pemeriksaan lagi.

Transaksi setiap material:
1. Kebutuhan dibuat oleh PIC atau pejabat berwenang.
2. Stok yang tersedia dialokasikan dengan konfirmasi fisik; ketersediaan belum mengubah master inventaris global.
3. Kebutuhan kurang diajukan melalui **Ajukan Pembelian**.
4. Pemohon dan penyetuju pembelian harus berbeda. Setelah disetujui, catat pemasok, nominal, referensi pesanan.
5. Penerima mencatat jumlah dan referensi surat jalan. Pemeriksa yang berbeda menilai jumlah sesuai/ditolak; barang yang ditolak tidak dapat dipakai.
6. Material yang sudah siap diserahkan kepada petugas. Pekerjaan yang menunggu material tidak dapat dilanjutkan atau dinyatakan selesai bila kebutuhan wajib belum diserahkan.

Seluruh perubahan dilakukan dalam transaksi yang mengunci tiket/baris dan menulis riwayat audit append-only.

## Kewenangan

| Peran | Wewenang |
|---|---|
| Pelapor / Sarpras | Lapor, ketahui keputusan administratif, verifikasi hasil |
| Teknisi / PIC | Diagnosis, usul kebutuhan/pembelian, pekerjaan, hasil |
| Pemegang kewenangan penugasan Work Order | Keputusan awal, alokasi stok, approval dan eksekusi pembelian, penerimaan/QC, pengelolaan vendor |
| Pemegang kewenangan pengesahan akhir | Penutupan administratif atau penutupan perbaikan |
| Approver berjenjang | Persetujuan tahap awal dan akhir sesuai penugasan jabatan |

Seluruh perubahan status dicek kembali pada server. Tombol yang tersembunyi bukan pengganti pemeriksaan hak akses.

## Batasan eksplisit

- **Alokasi stok manual:** kebutuhan Work Order belum terhubung ke buku stok material habis pakai global. Tidak mengurangi ketersediaan aset yang bisa dipinjam.
- **Pembelian terbatas Work Order:** pencatatan otorisasi, pemasok, nominal, referensi, penerimaan dan QC. Belum terhubung sistem pembayaran/keuangan atau master pemasok.
- **Penggantian aset:** keputusan penggantian tidak menghapus aset lama, memesan aset baru, atau melepas safety hold secara otomatis.
- **SLA:** menunggu material masih menghitung SLA existing; tidak berhenti otomatis.
- **Data existing:** tiket tanpa rencana/material baru tetap mengikuti alur lama.

## Pengujian dan deployment

Uji tanpa aset, stok lengkap/sebagian/nol, pembelian ganda, approval sendiri, QC salah spesifikasi, penerimaan parsial, stok melebihi kebutuhan, pekerjaan belum diserahkan, vendor ditolak lalu servis ulang, internal/hybrid, keputusan tanpa perbaikan dan approval dinamis. Uji juga pemisahan unit, peran, dan tiket legacy.

Perubahan skema bersifat aditif. Backup database lebih dahulu, jalankan migrasi serta tes di staging, Quality Guard MySQL dan browser UAT. Deploy hanya setelah PR disetujui, dengan migrasi berizin. Jangan mereset data produksi.
