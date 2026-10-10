# Serah Terima Keluar V2

## Tujuan

Kondisi fisik aset kini dicatat **sebelum** diserahkan. Penerimaan normal
membutuhkan dua aktor berbeda: pengelola dan peminjam dari unit pengajuan.
QR tetap hanya tautan berizin, bukan tanda tangan otomatis.

## Alur baru

1. Pada **Peminjaman Dikelola / Peminjaman Saya / widget tindakan cepat**
   klik **Pinjamkan** → diarahkan ke halaman **QR Serah Terima**.
2. Pengelola mengisi checklist kondisi awal. Untuk barang satuan, checklist
   mengikuti unit inventaris spesifik; untuk kendaraan ada opsi kilometer awal.
   Foto dan bukti penyerahan opsional, namun catatan kondisi wajib jika rusak.
3. Klik **Catat Kondisi Awal & Serahkan**. Sistem menyimpan receipt nomor
   `OUT-...` dan checklist awal, sementara status reservasi tetap
   **Disetujui** (belum dianggap dipinjam).
4. Peminjam login lewat QR/tautan dan memilih **Konfirmasi Penerimaan**.
   Sistem mencatat aktor dan waktu lalu menjalankan proses `checked_out`
   yang sama seperti sebelumnya (termasuk alokasi barang satuan dan
   pemeriksaan kru kendaraan). Satu orang tidak dapat mengonfirmasi dua sisi.
5. Pada pengembalian, alur receipt `RTN-...` dan checklist lama tetap
   mandiri; bukti awal dan bukti akhir ditampilkan terpisah.

## Pengecualian

Pengelola dapat memakai **Penyerahan Khusus** hanya dengan alasan wajib
10–2.000 karakter. Sistem mencatat alasan, aktor dan waktu di receipt
`direction=checkout`; kolom konfirmasi peminjam tetap kosong dan **tidak
dipalsukan**. Jika hanya akses service legacy yang tersedia (misalnya kode
lama atau integration task yang masih memanggil `processReservation()`),
checkout otomatis ditandai sebagai **jalur legacy belum diverifikasi**.
Jalur tersebut disediakan untuk kompatibilitas dan perlu diaudit/dipindahkan
ke alur dua pihak; tidak dianggap sebagai konfirmasi penerimaan peminjam.

## Penyimpanan

Migration `2026_10_10_000002_add_outbound_loan_handover_evidence.php`
menambahkan tabel `loan_checkout_checklists` untuk kondisi awal serta
`checkout_odometer` dan `fallback_reason` pada
`loan_handover_receipts`. Tabel bukti pengembalian tidak dihapus atau
ditimpa dan histori status tetap tersimpan.

## Pengujian dan deployment

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan test --filter=OutboundHandoverV2Test
php artisan test
```

Backup database dan verifikasi restorasi sebelum menjalankan migration
pada staging. Uji dengan dua akun terpisah dan kamera HP untuk barang
satuan, ruangan, dan kendaraan, termasuk kasus kondisi rusak dan fallback.
Jangan menyatakan lulus uji operasional hanya berdasarkan CI.
