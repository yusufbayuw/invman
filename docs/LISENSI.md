# Lisensi Offline InvMan

InvMan memverifikasi lisensi permanen bertanda tangan Ed25519 pada setiap
request aplikasi. Lisensi mengikat set domain dan IP host saja; source code,
commit Git, versi build, mesin, dan database tidak menjadi bagian lisensi.

## Konfigurasi

Tambahkan konfigurasi berikut ke `.env`:

```dotenv
LICENSE_ALLOWED_DOMAINS="*.tarunabakti.or.id,*.tbu.ac.id,tarunabakti.sch.id,*.tarunabakti.sch.id"
LICENSE_ALLOWED_HOST_IPS="127.0.0.1,::1,192.168.0.0/16"
LICENSE_TOKEN="v1.<payload>.<signature>"
```

- Domain exact hanya mencocokkan host tersebut.
- `*.domain.id` mencocokkan subdomain, tetapi tidak mencocokkan apex
  `domain.id`. Cantumkan keduanya bila keduanya dibutuhkan.
- Pola longgar seperti `*domain.id`, scheme, port, dan path ditolak.
- IP menerima IPv4, IPv6, dan CIDR. Domain dan IP diperlakukan sebagai
  alternatif (OR).
- Kapitalisasi, urutan, spasi, dan duplikasi dinormalisasi. Menambah,
  menghapus, atau mengubah entri membutuhkan token baru.

Setelah mengubah `.env`, muat ulang konfigurasi dan proses panjang:

```bash
php artisan config:cache
php artisan license:status
```

Restart PHP-FPM, `schedule:work`, atau process manager lain yang masih
menyimpan konfigurasi lama.

## Menerbitkan lisensi

Private issuer berada di `/Users/yusuf/Herd/invman-license-issuer` dan tidak
boleh dimasukkan ke source aplikasi yang dibagikan.

1. Isi domain/IP pada `.env` aplikasi.
2. Buat request kanonik:

   ```bash
   php artisan license:request > /tmp/invman-license-request.json
   ```

3. Pada mesin penerbit, buat token:

   ```bash
   cd /Users/yusuf/Herd/invman-license-issuer
   php bin/invman-license issue /tmp/invman-license-request.json
   ```

4. Salin token yang dihasilkan ke `LICENSE_TOKEN`, jalankan
   `php artisan config:cache`, lalu periksa dengan `php artisan license:status`.

Token bukan private key dan dapat diperiksa tanpa membukanya untuk diedit:

```bash
php /Users/yusuf/Herd/invman-license-issuer/bin/invman-license inspect 'v1.<payload>.<signature>'
```

Private key pada `keys/private.key` adalah rahasia utama. Simpan backup aman,
jangan commit, jangan salin ke server pelanggan, dan jangan kirim melalui log
atau tiket dukungan.

## Lock dan diagnosis

- Web, Filament, Chatify, Livewire, captive portal, dan API mengembalikan
  HTTP `423 Locked` ketika token/config/host tidak valid.
- `/up` tetap tersedia sebagai liveness probe.
- `license:request`, `license:status`, command deployment Laravel, dan operasi
  pemulihan tetap dapat dijalankan saat terkunci.
- `loans:expire-holds` dan jadwalnya berhenti jika token atau host `APP_URL`
  tidak valid.
- Detail kegagalan tersedia di log sebagai kode alasan, tanpa mencetak token
  atau daftar domain/IP.

## Hardening virtual host

Pemeriksaan offline PHP hanya melihat Host request. Web server harus menolak
Host/SNI yang tidak dikenal dan meneruskan header `Host` asli. Aplikasi sengaja
tidak mempercayai `X-Forwarded-Host`.

Contoh default server Nginx yang menolak host asing:

```nginx
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    return 444;
}

server {
    listen 80;
    server_name *.tarunabakti.or.id *.tbu.ac.id tarunabakti.sch.id *.tarunabakti.sch.id;
    root /path/to/invman/public;
    # Konfigurasi PHP-FPM aplikasi berada di sini.
}
```

Contoh default virtual host Apache:

```apache
<VirtualHost *:80>
    ServerName invalid.local
    <Location "/">
        Require all denied
    </Location>
</VirtualHost>
```

Tambahkan virtual host terpisah dengan `ServerName`/`ServerAlias` eksplisit
untuk domain berlisensi. Terapkan prinsip yang sama pada listener HTTPS dan
konfigurasi load balancer/CDN.

## Batas keamanan

Signature mencegah pembuatan atau perubahan token tanpa private key. Namun,
karena verifier offline berjalan di source PHP yang dibagikan, pihak yang bebas
memodifikasi source masih dapat menghapus pemeriksaan. Mekanisme ini adalah
tamper resistance dan kontrol operasional, bukan perlindungan anti-bypass
absolut.
