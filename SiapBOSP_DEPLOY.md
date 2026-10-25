# Deploy SiapBOSP ke Produksi

Panduan ini harus mengikuti berkas yang benar-benar ada pada repo. **Pada branch/commit yang diverifikasi, direktori `deploy/` dan skrip `pasang.sh`, `perbarui.sh`, `backup.sh`, serta `nginx-siapbosp.conf` tidak tersedia.** Karena itu jangan menjalankan perintah otomatis yang pernah dirujuk versi lama dokumen ini. Prosedur di bawah adalah checklist manual dan harus diuji pada staging.

> Persyaratan awal: PHP 8.3+, Composer, Node.js, PostgreSQL (disarankan untuk pusat), Nginx/HTTPS, SMTP, storage persisten, queue worker, dan scheduler. Untuk satu sekolah/laptop ada prosedur portable terpisah.

## 1. Persiapan server

Gunakan OS Linux yang masih didukung. Batasi firewall ke SSH dan HTTPS; jangan buka port PostgreSQL ke internet. Pasang versi PHP dan ekstensi sesuai `composer.json`/`composer.lock` (antara lain `pgsql`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `gd`, `intl`). Siapkan domain, sertifikat HTTPS, database, akun database berhak minimum, serta kredensial SMTP dan backup sebagai secrets di luar repository.

## 2. Pasang aplikasi

```bash
sudo mkdir -p /var/www/siapbosp
sudo chown "$USER" /var/www/siapbosp
cd /var/www/siapbosp
# Salin/checkout source release yang sudah ditinjau ke direktori ini.
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci
npm run build
cp .env.example .env
```

> `npm run build` hanya perlu diulang bila `resources/css/app.css` atau `resources/js/app.js` berubah — **hasil build sudah ikut repository** (`public/build/`), sehingga pemasangan di mesin tanpa Node.js tetap mendapat CSS. Setelah build/checkout, `php artisan siapbos:cek-aset` menyebutkan sumber CSS yang dipakai halaman (harus "aset hasil build"); perintah yang sama dipakai saat aplikasi tampil polos. Bila `public/build` hilang, aplikasi memakai jalur darurat (`/tailwind.js` + gaya komponen di `layouts/_gaya-darurat.blade.php`) yang tampil mirip tetapi lebih lambat. Rincian audit CSS per halaman: `docs/AUDIT-CSS-HALAMAN.md`.

Edit `.env` sebelum melanjutkan. Minimal:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-resmi.example
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=siapbosp
DB_USERNAME=siapbosp
DB_PASSWORD=<secret-database>
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=720
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
MAIL_MAILER=smtp
BACKUP_PASSWORD=<secret-terpisah-dari-backup>
BACKUP_RETENTION_DAYS=30
```

Isi juga `TRUSTED_PROXIES` hanya dengan IP proxy/CDN yang benar-benar berada di depan aplikasi dan `TRUSTED_HOSTS` dengan domain publik yang sah. Konfigurasikan SMTP nyata. Untuk remote audit, pilih `AUDIT_REMOTE_CHANNEL=syslog` atau `socket` dan tetapkan `AUDIT_REMOTE_URL=tls://FQDN:PORT` hanya bila SIEM menerima format tersebut. Jangan memasukkan rahasia ke Git, log, atau tiket publik.

Kemudian:

```bash
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo chown -R www-data:www-data /var/www/siapbosp/storage /var/www/siapbosp/bootstrap/cache
```

**Jangan gunakan `php artisan migrate --seed` untuk produksi.** `DatabaseSeeder` membuat sekolah dan akun demo dengan kata sandi `password`. Buat akun admin awal dengan kredensial acak melalui proses provisioning yang aman, paksa perubahan kata sandi saat login, verifikasi role, dan hapus data demo bila pernah dibuat.

Konfigurasikan Nginx (root harus `/var/www/siapbosp/public`) dan HTTPS dari template yang telah ditinjau oleh operator. File konfigurasi Nginx khusus tidak disediakan di repo saat ini. Pastikan `/up` hanya mengungkap status kesehatan minimum dan endpoint aplikasi tidak dapat membaca file `.env`, source, atau storage privat.

## 3. Worker antrean — wajib

Jalankan **dua worker persisten**, dan awasi keduanya dengan systemd/Supervisor. Impor menggunakan queue `imports`; email memakai `default`. Contoh perintah (sesuaikan path PHP dan user):

```bash
cd /var/www/siapbosp
sudo -u www-data php artisan queue:work database --queue=imports --sleep=3 --tries=2 --timeout=600 --max-time=3600
sudo -u www-data php artisan queue:work database --queue=default --sleep=3 --tries=3 --timeout=90 --max-time=3600
```

Konfigurasikan service agar restart setelah crash/deploy, berhenti dengan graceful shutdown, dan tidak menjalankan worker sebagai root. `DB_QUEUE_RETRY_AFTER` harus lebih besar daripada timeout worker impor (contoh 720 detik). Setelah deploy, restart kedua worker agar kode terbaru dimuat. Pantau backlog dan `php artisan queue:failed`; uji retry pekerjaan gagal.

## 4. Scheduler — wajib

Daftarkan `schedule:run` setiap menit sebagai user aplikasi, misalnya cron:

```cron
* * * * * cd /var/www/siapbosp && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Scheduler menjalankan backup harian serta pembersihan run/file impor ARKAS lama. Pastikan `BACKUP_PASSWORD` tersedia sebelum jadwal backup; perintah backup akan gagal tertutup bila password/enkripsi tidak tersedia. Jangan menganggap cron backup shell lama aktif—skrip backup tersebut tidak ada pada repo ini.

## 5. Verifikasi sebelum membuka akses publik

1. Di staging dengan versi PHP dan PostgreSQL yang sama seperti produksi, jalankan `composer install` termasuk dependensi pengujian lalu `php artisan test`. CI menjalankan suite pada SQLite dan PostgreSQL 16; workflow harus hijau pada commit release. Smoke test staging tetap diperlukan untuk memeriksa konfigurasi, data, dan integrasi yang khusus produksi.
2. Jalankan `php artisan migrate:status`; periksa route/cache dan log setelah `config:cache`.
3. Uji login semua role, ganti/reset sandi, nonaktifkan akun dan sekolah, cabut/kedaluwarsakan API token, serta pastikan operator sekolah A tidak dapat membaca/menulis data sekolah B (termasuk HPP).
4. Uji ekspor CSV/XLSX dengan teks yang diawali `=`, `+`, `-`, `@`, tab, dan CR; pastikan tidak dieksekusi sebagai formula.
5. Unggah file ARKAS uji; pastikan mendapat status antrean, worker `imports` memprosesnya, status/hasil hanya terlihat oleh pemilik, dan file sumber terhapus setelah selesai/gagal. Uji juga status job macet dan cleanup scheduler.
6. Kirim email uji dan konfirmasi worker `default` memprosesnya. Verifikasi email tidak tersimpan sebagai isi sensitif di log produksi.
7. Jalankan `php artisan backup:automatic`, periksa file backup tersimpan privat, lalu **uji restore** ke database terpisah menggunakan password backup. Simpan backup terenkripsi di media/offsite terpisah dan uji prosedur pemulihan.
8. Jika memakai SIEM, kirim event uji melalui kanal yang dipilih dan konfirmasi event diterima dengan identitas/format yang benar. Belum ada klaim integrasi produksi sebelum langkah ini lolos.
9. Jalankan pilot satu sekolah: impor data ARKAS aktual, cocokkan hasil rekonsiliasi dengan sumber, uji cetak/LPJ, minta operator dan Dinas menyetujui hasilnya.
10. Periksa header CSP pada respons aplikasi (mis. `curl -sI https://domain/login | grep -i content-security-policy`): harus memuat `script-src 'self' 'nonce-…'` dan `script-src-attr 'none'`, tanpa `'unsafe-inline'` pada `script-src`. Lalu uji di browser sungguhan alur yang dulu memakai handler inline: dialog konfirmasi hapus/tutup BKU, filter tahun/bulan yang langsung memuat ulang, tombol cetak (BKU, register, SPTJM, LPJ), buka/tutup sidebar dan grup menu di layar kecil, hitung total di Pendapatan/Belanja/RKAS, serta tombol salin di "Salin Cepat ke ARKAS". Bila ada halaman yang memakai handler `on*` baru, `script-src-attr 'none'` akan memblokirnya—jangan melemahkan CSP; migrasikan handler tersebut. Catatan: `public/pendamping-arkas.html` adalah berkas statis yang disajikan langsung oleh web server, tidak melewati middleware Laravel dan tidak menerima header CSP.

## 6. Operasi dan pemantauan

Pantau `storage/logs/laravel.log`, `storage/logs/audit-*.log`, status worker, queue backlog/failed jobs, scheduler, ruang disk, kapasitas database, sertifikat HTTPS, serta hasil backup. Batasi akses log dan tentukan retensi. Sediakan kontak insiden, prosedur reset token, restore, dan pemberitahuan kepada sekolah sebelum data nyata dimasukkan.

**Kondisi rilis:** jangan buka untuk semua sekolah sebelum workflow tes pada commit kandidat berhasil, antrean/scheduler/backup diuji di staging, akun demo dipastikan tidak aktif, dan pemilik layanan menyetujui sisa risiko yang belum tertutup secara operasional. R-03 sudah ditutup pada kode (tanpa handler atribut inline, `script-src-attr 'none'`); yang masih diperlukan adalah uji browser pada langkah 10 di staging/produksi. R-12 (SIEM) tetap belum terverifikasi di produksi.
