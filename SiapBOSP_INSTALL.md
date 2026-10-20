# Panduan Instalasi SiapBOSP

## A. Di komputer sendiri (Termudah: Laragon/XAMPP)
1. Install Laragon (sudah ada PHP 8.2+ & komposer).
2. Install PostgreSQL ≥ 16 (atau: edit `.env` → `DB_CONNECTION=sqlite` lalu buat file kosong `database/database.sqlite` untuk uji tanpa PG).
3. Salin folder `app` hasil ekstrak ZIP ke `C:\laragon\www\siapbosp`.
4. Di terminal folder itu: `composer install`
5. Salin `.env.example` jadi `.env`, sesuaikan DB_HOST/DB_DATABASE/DB_USERNAME, lalu:
   - `php artisan key:generate`
   - `php artisan migrate --seed`
   - `php artisan serve`
6. Buka http://localhost:8000 → login akun demo:
   - operator@sekolah.demo / password · kepsek@sekolah.demo / password · dinas@siapbosp.id / password

## B. Di server produksi (VPS Ubuntu/Debian)
- `apt install php8.3-fpm php-pgsql nginx postgresql composer`
- clone folder ini → `composer install --no-dev`, set `.env`, `migrate --seed`
- Nginx proxy ke PHP-FPM; aktifkan HTTPS (certbot). Gunakan `queue` & `schedule` untuk job.

## Struktur penting
- `app/` kode Laravel 13 (PHP 8.4) · `database/migrations` 10 migrasi · `database/seeders` akun + referensi + data demo
- `BLUEPRINT.md` (di folder Drive) = PRD & ERD rancangan
