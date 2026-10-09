# Mass Assignment Hardening — InvMan

## Tujuan

Mencegah payload tak terduga mengubah kolom yang tidak dimaksudkan, tanpa
melewati validasi dan state machine proses peminjaman.

### Perlindungan model

- Seluruh model Eloquent persisten memiliki `$fillable` **eksplisit**.
  Tidak ada lagi `$guarded = []` pada model persisten ataupun
  `$guarded = ['id']` yang membuka semua kolom selain primary key.
- Primary key `id`, `created_at`, `updated_at`, dan atribut tidak dikenal
  tidak dapat diisi massal.
- `LoanRequestNeed` adalah model sintetik baca-saja (`$guarded = ['*']`);
  tidak ada operasi tulis terhadap UNION view ini.
- `Model::preventSilentlyDiscardingAttributes(true)` berlaku **juga di
  production**. Upaya mengisi atribut yang tidak ada dalam daftar izin
  akan menghasilkan exception, bukan diam-diam disimpan/dibuang.
- Field audit reservasi `decision_by`, `decision_at`,
  `status_changed_by`, `status_changed_at`,
  dan `overdue_notified_at` tidak berada di `$fillable`.
  Observer dan service tepercaya menuliskannya melalui assignment atribut
  langsung setelah otorisasi, bukan lewat payload input.
- `LoanReservationStatusHistory`, `LoanReservationCorrection`, dan
  `VehicleAssignmentHistory` menjadi **append-only melalui event Eloquent**:
  tidak dapat diedit atau dihapus memakai instance model. Koreksi harus
  dicatat sebagai entri baru.

### Atribut yang tetap dapat diisi demi kompatibilitas

Atribut `status` pada kegiatan dan reservasi tetap masuk allowlist karena
transisi internal menggunakan Eloquent `create/update`. Hal ini **bukan**
izin bagi pemohon untuk memilih status sesuka hati. Alur harus tetap
menggunakan `LoanRequestService` dan observer dengan pengecekan unit,
pengelola, waktu dan state transition, bukan memanggil
`$model->update($request->all())`.

Kolom hubungan seperti `user_id`, `g001_m001_unit_id`, `item_id` pada
beberapa model juga dibutuhkan pengelolaan master dan pembuatan reservasi.
Nilainya **wajib** berasal dari aktor yang sudah diotorisasi dan/atau
pemilihan valid yang diperiksa server. Ini adalah pertahanan berlapis,
bukan pengganti authorization policies dan input validation.

### Batas jaminan

Larangan pembaruan histori menggunakan **model events**; operasi langsung
melalui `DB::table(...)->update/delete` tidak memicu event Laravel.
Foreign key `RESTRICT` dari hardening sebelumnya tetap melindungi
referensi transaksi tetapi bukan mekanisme immutable SQL. Jika diperlukan
jaminan append-only terhadap akses SQL langsung, gunakan izin database
khusus/trigger setelah audit operasional.

## Pengujian regresi

```bash
php artisan test --filter=MassAssignmentHardeningTest
php artisan test
```

Tes memverifikasi allowlist model, penolakan metadata persetujuan, ID yang
dipalsukan, ownership unit saat penyimpanan draf, dan histori Eloquent
yang immutable. CI menambahkan pemeriksaan sintaks PHP, Laravel suite,
Vite build dan migrasi MySQL.

## Pedoman untuk fitur baru

1. Definisikan `$fillable` spesifik untuk setiap model baru.
2. Jangan menggunakan `Model::unguard()` atau `$guarded = []`.
3. Jangan mengalirkan `request()->all()`, payload Livewire mentah, atau
   `$data` yang tidak divalidasi langsung ke `create/update/fill`.
4. Atribut status, pemilik, aktor persetujuan dan audit harus diperoleh
   dari keputusan service yang memiliki pemeriksaan otorisasi.
5. Pastikan uji negatif (payload berbahaya) dan uji positif (alur bisnis)
   ditambahkan bersama perubahan schema.
6. Untuk merevisi bukti audit, tambahkan catatan koreksi baru, jangan
   menghapus atau mengedit histori lama.

## Deployment

Tidak diperlukan perubahan schema tambahan untuk hardening ini.
Tetapi deployment harus bersama seluruh kode service/observer/resource
yang ada di `main`. Periksa log setelah rollout untuk
`MassAssignmentException`—itu adalah sinyal pemanggil yang masih
menulis atribut tidak diizinkan. **Jangan** mematikan kembali guard
global untuk mengatasi exception; perbaiki input mapping atau masukkan
atribut yang terverifikasi dalam allowlist spesifik.
