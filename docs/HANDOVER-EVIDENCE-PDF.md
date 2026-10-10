# Bukti Serah Terima Digital (PDF)

## Mengakses dokumen

Pada **Peminjaman Saya** pilih kebutuhan barang / ruangan / kendaraan lalu klik
**Bukti PDF**. Tombol yang sama tersedia pada halaman **QR Serah Terima**.
URL internal untuk peminjaman yang berhak dilihat:

```text
GET /pinjam/bukti/{item|room|vehicle}/{reservation-uuid}/pdf
```

Tautan membutuhkan login, dibatasi `throttle:20,1`, dan **setiap unduhan**
memeriksa kembali unit pemohon atau hak pengelolaan aset melalui
`LoanHandoverQrService::canView`. Mengetahui UUID tidak memberi hak akses.
Akun di luar kewenangan menerima HTTP 404. Dokumen dikirim sebagai
`application/pdf` attachment dengan `Cache-Control: private, no-store`.

## Isi dokumen

PDF A4 mencakup identitas master kegiatan dan pengajuan, unit dan pemohon,
jenis / nama aset, nomor inventaris barang satuan, jadwal serta status.
Untuk kendaraan dicatat nomor polisi, pengemudi yang ditugaskan, dan kilometer
awal jika ada; **kenek internal tidak ditampilkan kepada peminjam**.

Bagian *penyerahan keluar* bersumber dari receipt `OUT-...` dan checklist
`loan_checkout_checklists`, sedangkan bagian *pengembalian* bersumber dari
receipt `RTN-...` dan `loan_reservation_checklists`. Keduanya terpisah,
dengan kondisi per unit barang, catatan kerusakan, nama akun dan waktu
konfirmasi. Foto yang tersimpan dan memenuhi pembatasan dimasukkan sebagai
gambar kecil di PDF; file yang besar / tak tersedia hanya diberi penanda
ada lampiran di sistem dan tidak pernah dimuat dari URL luar.

**Kejujuran bukti:** PDF tidak pernah mengisi identitas konfirmasi yang
tidak ada. Penyerahan khusus dan jalur legacy tercetak sebagai pengecualian
beralasan, **bukan** konfirmasi peminjam. Jika penyerahan atau pengembalian
belum selesai, dokumen diberi label **DOKUMEN BELUM LENGKAP**. Dokumen
lengkap bukan tanda tangan elektronik tersertifikasi.

## Teknis dan keamanan

- Menggunakan `Barryvdh\DomPDF\Facade\Pdf` yang sudah digunakan untuk
  mencetak QR ruangan; tidak ada paket baru.
- PDF dihasilkan langsung dari database saat diminta. Tidak ada salinan PDF
  yang dapat diakses secara publik di storage, migrasi baru, atau perubahan
  status transaksi.
- Semua string termasuk catatan bebas di-escape oleh Blade. Renderer tidak
  mengizinkan remote resource; hanya foto lokal JPEG / PNG dari direktori
  serah-terima yang sesuai, masing-masing maksimum 1,5 MB, yang dapat di-inline.
- Waktu cetak tercantum dengan `APP_TIMEZONE`; gunakan konfigurasi zona
  waktu server yang benar.
- Karena PDF dibuat ulang saat diunduh, jika histori berubah secara sah
  setelah pencetakan, PDF lama adalah snapshot keadaan saat dicetak. Untuk
  kepentingan audit permanen simpan hasil unduhan di sistem arsip organisasi.

## Pengujian dan operasional

```bash
php artisan test --filter=LoanHandoverPdfTest
php artisan test
php artisan route:list --name=loans.handover.pdf
```

Pastikan `APP_KEY` dan konfigurasi DomPDF tersedia; lakukan uji unduh dan
cetak pada staging dengan peran sarpras, pengelola aset dan akun tidak
berwenang. Verifikasi PDF dari aset barang satuan, kendaraan, dan ruangan,
serta skenario belum lengkap dan fallback. Fitur ini **tidak memerlukan
migrasi database tambahan**, tetapi seluruh migrasi penyerahan/pengembalian
yang terdahulu harus sudah berhasil dijalankan.
