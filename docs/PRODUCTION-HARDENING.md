# Hardening Akun dan Konfigurasi Produksi

## Perubahan yang berlaku

- `UserSeeder` **tidak lagi membuat atau memodifikasi akun di production**.
- Di local/testing, user fixture hanya dibuat jika `SEED_DEMO_USERS=true` DAN `SEEDED_USER_PASSWORD` tersedia dengan minimal 16 karakter, huruf besar/kecil, angka dan simbol.
- Mengulang seeder tidak mengganti password, nama, unit, maupun role akun yang sudah ada.
- `AppServiceProvider` tidak lagi memanggil `Model::unguard()`; setiap model legacy kini punya perlindungan primary key pada mass assignment, sementara akun `User` mempertahankan whitelist `$fillable`.
- Panel `/admin` hanya dapat diakses role admin/fasilitas/Sarpras atau pengelola aset yang ditugaskan.
- `X-Forwarded-*` hanya dipercayai dari alamat reverse proxy yang tercantum di `TRUSTED_PROXIES`, bukan dari semua IP.
- Production default secure session cookie aktif; audit akan menolak konfigurasi tidak aman.

## Membuat admin pertama pada production

Lakukan **setelah** koneksi database, `APP_KEY`, migrasi, dan RoleSeeder siap:

```bash
php artisan db:seed --class=RoleSeeder --force
php artisan invman:provision-admin admin admin@example.org
```

Perintah provisioning **memerlukan terminal interaktif**, meminta password dan
konfirmasi tanpa menampilkan ketikan. Password minimal 16 karakter dan harus
memuat huruf besar/kecil, angka, dan simbol.

Perintah **menolak username/email yang sudah ada** dan tidak pernah me-reset
password admin lama. Untuk mengganti kredensial gunakan alur resmi pengelolaan
akun, bukan seeder atau perintah provisioning.

## Konfigurasi recommended di production

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://invman.example.org
APP_KEY=base64:YOUR_EXISTING_DEPLOYMENT_KEY

SEED_DEMO_USERS=false
# SEEDED_USER_PASSWORD harus tidak disetel
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
TRUSTED_PROXIES=127.0.0.1,::1
```

**Jangan mengganti APP_KEY dari sistem yang telah digunakan**: cookie,
data terenkripsi dan sesi yang terkait kunci sebelumnya dapat rusak.
Sesuaikan `TRUSTED_PROXIES` dengan IP/CIDR Nginx, aaPanel, load balancer, atau
gateway yang **sebenarnya** berhubungan langsung ke aplikasi. Daftar contoh
loopback bukan pengganti pemeriksaan topologi infrastruktur. Jangan pernah
menggunakan `TRUSTED_PROXIES=*`, `0.0.0.0/0` atau `::/0`.

Periksa file .env aktual; `.env.example` berisi default local dan bukan
konfigurasi production siap deploy. Gunakan HTTPS pada reverse proxy dan aktifkan
HSTS di level proxy setelah HTTPS terverifikasi konsisten.

## Validasi dan rollout

```bash
php artisan optimize:clear
php artisan invman:security-audit
php artisan test --filter=ProductionHardeningTest
```

Simpan backup database dan dokumentasikan akun admin pertama sebelum rollout.
Jika `APP_ENV` diubah, gunakan `php artisan config:clear` dan `php artisan
config:cache` sesuai prosedur deployment. Audit gagal dengan exit code nonzero
jika mendeteksi konfigurasi berbahaya dan tidak menampilkan rahasia.

Ini adalah peningkatan perlindungan mass assignment **dasar**, bukan pengganti
validasi payload, otorisasi resource/action, maupun audit HTTP request. Sebagian
model legacy masih menerima atribut bisnis selain primary key untuk menjaga
kompatibilitas Filament dan workflow; whitelist per-field yang lebih ketat
dapat diterapkan setelah seluruh flow ditelusuri dan diuji.
