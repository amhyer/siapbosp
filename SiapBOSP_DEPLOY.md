# DEPLOY SiapBOSP ke Produksi (VPS)

> Versi dokumen 3 — sinkron sampai **Fase 23 (v22)**: menambahkan catatan folder TTD basah `storage/app/private/ttd/` (ikut `backup.sh`, tak disentuh `perbarui.sh`) dan bahwa login kini memiliki throttle 5×/5 menit bawaan aplikasi.

Rekomendasi server: **Ubuntu 24.04 LTS, 1–2 vCPU, RAM 2 GB, disk 20 GB**.
Dua cara: **(A) otomatis dengan skrip** (disarankan) atau **(B) manual** (langkah rinci).

---

## A. Cara Otomatis — `deploy/pasang.sh`

1. Unduh ZIP kode sumber (mis. `siapbosp-app-v15.zip`) ke server:
   ```bash
   scp siapbosp-app-v15.zip root@ALAMAT-IP:/root/
   ```
2. Bongkar ZIP sebentar untuk mengambil skripnya, lalu jalankan satu perintah — skrip akan memasang sendiri isi ZIP penuh:
   ```bash
   cd /root && unzip -q siapbosp-app-v15.zip -d siapbosp-app
   ```

   Dengan domain + HTTPS otomatis:
   ```bash
   sudo bash siapbosp-app/deploy/pasang.sh --zip /root/siapbosp-app-v15.zip \
        --domain bos.sekolahku.sch.id --email anda@guru.sch.id
   ```
   Tanpa domain (pakai IP server saja):
   ```bash
   sudo bash siapbosp-app/deploy/pasang.sh --zip /root/siapbosp-app-v15.zip
   ```
   Pilihan berguna: `--db-pass KATA-SANDI` (tentukan sendiri), `--tanpa-seed` (tanpa data demo),
   `--tanpa-https` (jangan pasang sertifikat).

3. Skrip memasang **semuanya**: PHP 8.3-fpm + PostgreSQL + Nginx, database & user,
   `composer install`, `.env` produksi (termasuk `APP_URL`, `APP_DEBUG=false`, `key:generate`),
   migrasi + seeder opsional, vhost Nginx, HTTPS Certbot (bila domain+email diberikan),
   firewall `ufw`, **pekerja antrean email via systemd (`siapbosp-worker`)**,
   dan **cron backup harian ke `/backup`** (retensi 30 hari).
4. Ringkasan + sandi database tersimpan di **`/root/siapbosp-kredensial.txt`**.

### Daftar wajib SETELAH pemasangan ✅
- 🔑 **Ganti password demo** (`operator@sekolah.demo`, `kepsek@sekolah.demo`, `dinas@siapbosp.id`) — atau hapus akun demo bila data sudah nyata.
- 📧 **Aktifkan email sungguhan**: sunting `/var/www/siapbosp/.env` → `MAIL_MAILER=smtp` + kredensial SMTP (contoh Gmail ada di berkas itu), lalu `php artisan config:cache`. Uji lewat dashboard Dinas → **📨 Kirim Email Uji**.
- 🌐 Pastikan `APP_URL=https://domain-anda` (tautan di email ikut dari sini).
- 🏫 Hapus sekolah contoh / tambahkan sekolah nyata lewat panel Dinas → Sekolah (atau *Impor CSV*).
- ✍️ (Fase 21+) **TTD basah** disimpan di `storage/app/private/ttd/` — pastikan izin tulis `www-data` pada `storage/` utuh (otomatis by skrip; sudah termasuk dalam `backup.sh`).

### Memperbarui ke versi baru — `deploy/perbarui.sh`
```bash
scp siapbosp-app-vXX.zip root@ALAMAT-IP:/root/
sudo bash /var/www/siapbosp/deploy/perbarui.sh /root/siapbosp-app-vXX.zip
```
Alurnya: mode perawatan → **backup DB otomatis** (`/backup/praedate-*.sql.gz`) → salin berkas baru
(`.env` & `storage/` TIDAK disentuh — berkas TTD basah ikut aman) → `migrate --force` (termasuk kolom `ttd_path` Fase 21) → cache → hidup kembali.
Rollback: pulihkan SQL pra-pembaruan + pasang ulang ZIP versi sebelumnya.

### Cadangan & pemulihan manual
- Backup sekarang: `DB_PASS='SANDI' /usr/local/bin/siapbosp-backup.sh`
- Pulihkan: `gunzip -c /backup/BERKAS.sql.gz | psql -U siapbosp -d siapbosp` (uji berkala di server terpisah!)

---

## B. Cara Manual (rinci)

### 1. Paket
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y php8.3-fpm php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-bcmath php8.3-gd php8.3-intl postgresql postgresql-contrib nginx unzip composer
```

### 2. Database
```bash
sudo -u postgres psql -c "CREATE USER siapbosp WITH PASSWORD 'KATA-SANDI-KUAT';"
sudo -u postgres psql -c "CREATE DATABASE siapbosp OWNER siapbosp;"
```

### 3. Aplikasi
```bash
sudo mkdir -p /var/www/siapbosp && sudo chown $USER /var/www/siapbosp
cd /var/www/siapbosp            # salin isi ZIP (folder app/) ke sini
composer install --no-dev --optimize-autoloader
cp .env.example .env            # sesuaikan:
#   APP_ENV=production  APP_DEBUG=false  APP_URL=https://domain-anda
#   DB_HOST=127.0.0.1  DB_DATABASE=siapbosp  DB_USERNAME=siapbosp  DB_PASSWORD='KATA-SANDI-KUAT'
php artisan key:generate
php artisan migrate --seed      # tanpa --seed untuk data bersih
php artisan storage:link
sudo chown -R www-data:www-data /var/www/siapbosp
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### 4. Nginx + HTTPS
Salin `deploy/nginx-siapbosp.conf` ke `/etc/nginx/sites-available/siapbosp`
(ganti `__ROOT__`→`/var/www/siapbosp`, `__DOMAIN__`→domain anda), tautan ke
`sites-enabled`, hapus `default`, lalu:
```bash
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d domain-anda  # HTTPS gratis
```

### 5. Backup harian (cron root)
```cron
0 1 * * * DB_PASS='KATA-SANDI-KUAT' /var/www/siapbosp/deploy/backup.sh >/dev/null 2>&1
```

### 6. Keamanan dasar
- `sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable`
- `APP_DEBUG=false` — WAJIB di produksi.
- Ganti/hapus seluruh akun demo; jangan bagikan kredensial Dinas ke sekolah.
- Login aplikasi kini **otomatis ter-throttle**: 5× kata sandi salah dalam 5 menit (per email + IP) → blokir sementara; riwayat masuk ada di menu 🔐 Keamanan (akun Dinas).

---

## Diagnosis bila bermasalah
| Gejala | Periksa |
|---|---|
| 500 kosong | `tail -50 /var/www/siapbosp/storage/logs/laravel.log` — umumnya `.env` salah / izin `storage/` |
| Nginx 502 | `systemctl status php8.3-fpm`; socket `/run/php/php8.3-fpm.sock` ada? |
| Gagal login DB | `psql -U siapbosp -h 127.0.0.1 siapbosp` dengan sandi di `.env` |
| Migrasi tertinggal | `php artisan migrate:status` lalu `php artisan migrate --force` |
| Email tak masuk | dashboard Dinas → 📨 Email Uji; lihat log; `MAIL_MAILER` masih `log`? |
| Email menumpuk di antrean | `systemctl status siapbosp-worker`; `php artisan queue:failed` menampilkan surat gagal, `php artisan queue:retry all` untuk kirim ulang |
