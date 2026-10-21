# Akun Demo SiapBOSP

Dokumen resmi seluruh akun demo beserta file yang berhubungan dengannya.
Sumber pembuat akun: [`database/seeders/DatabaseSeeder.php`](../database/seeders/DatabaseSeeder.php).

- Halaman login: **http://127.0.0.1:8000/login**
- Versi mesin-baca: [`AKUN_DEMO.csv`](./AKUN_DEMO.csv)

## Daftar akun

Semua akun memakai kata sandi **`password`** dan dibuat oleh seeder.

| Email | Kata sandi | Role | Nama | Sekolah (NPSN) | Panel |
|---|---|---|---|---|---|
| `superadmin@siapbosp.id` | `password` | superadmin | Superadmin | — (lintas sekolah) | `/dinas` |
| `dinas@siapbosp.id` | `password` | dinas | Admin Dinas Pendidikan | — (lintas sekolah) | `/dinas` |
| `operator@sekolah.demo` | `password` | operator | Operator Satu | SD Negeri 012345 Mamajang (60100001) | `/dashboard` |
| `kepsek@sekolah.demo` | `password` | kepsek | Kepala Sekolah Satu | SD Negeri 012345 Mamajang (60100001) | `/dashboard` |
| `operator2@sekolah.demo` | `password` | operator | Operator Dua | SD Negeri 067890 Losari (60100002) | `/dashboard` |
| `kepsek2@sekolah.demo` | `password` | kepsek | Kepala Sekolah Dua | SD Negeri 067890 Losari (60100002) | `/dashboard` |

Sekolah demo:

| NPSN | Nama | Alamat |
|---|---|---|
| 60100001 | SD Negeri 012345 Mamajang | Jl. Andi Mangerangi No. 10, Kota Makassar |
| 60100002 | SD Negeri 067890 Losari | Jl. Datu Museng No. 12, Kota Makassar |

## Hak akses per role

Ditentukan oleh middleware `role:…` di [`routes/web.php`](../routes/web.php) dan
[`app/Http/Middleware/EnsureRole.php`](../app/Http/Middleware/EnsureRole.php) (salinan: [`source/EnsureRole.php`](./source/EnsureRole.php)).

| Role | Akses | Route yang dijaga |
|---|---|---|
| `operator`, `kepsek` | Panel sekolah | `/dashboard`, `/rkas/*`, `/penerimaan/*`, `/pengeluaran/*`, `/pembukuan/*`, `/laporan/*`, `/utility/*` (`role:operator,kepsek`, routes/web.php:45) |
| `superadmin`, `dinas` | Panel dinas | `/dinas/*` — sekolah, konsolidasi, tahap, pengguna, alokasi, sah/revisi RKAS, LPJ, aktivitas, backup, pengumuman (`role:superadmin,dinas`, routes/web.php:104) |

Perilaku lain yang relevan dengan akun demo:

- Setelah login, `isDinas()` (`superadmin`/`dinas`) diarahkan ke `/dinas`, sisanya ke `/dashboard`
  (`app/Http/Controllers/LoginController.php:26`).
- Akun sekolah yang sekolahnya nonaktif **ditolak login** (`LoginController.php:22-24`), tampil pesan
  "Akun sekolah Anda nonaktif. Hubungi Admin Dinas." dan sesi tidak dibuat. Untuk mengaktifkan lagi,
  login sebagai `dinas@siapbosp.id` lalu toggle sekolah di **/dinas/sekolah** (`dinas.sekolah.toggle`).
- `superadmin` dan `dinas` identik di seluruh route; bedanya saat pembuatan/pimporan pengguna,
  akun dengan role ini selalu disimpan tanpa `school_id` (`DinasController.php:206`).
- Kata sandi disimpan ter-hash bcrypt (`User::casts['password'] = 'hashed'`); nilai `password` di
  atas adalah plaintext untuk login, bukan hash.
- Semua akun auth bisa membuat API token sekali tampil via POST `/api-token`
  (`routes/web.php`, nama route `api-token.store`); lihat [`API.md`](../API.md) dan
  [`SiapBOSP_API.md`](../SiapBOSP_API.md).

## Membuat ulang akun demo

Seeder hanya aman dijalankan **sekali** karena email unik. Untuk reset penuh:

```sh
php artisan migrate:fresh --seed   # PERINGATAN: menghapus seluruh data
```

Verifikasi isi database:

```sh
php artisan tinker --execute="App\Models\User::all(['email','role'])->each(fn(\$u) => print(\$u->email.' | '.\$u->role.PHP_EOL));"
```

## File yang berhubungan dengan akun demo

| File | Peran |
|---|---|
| [`database/seeders/DatabaseSeeder.php`](../database/seeders/DatabaseSeeder.php) | Membuat 6 akun demo + sekolah, referensi, dan data transaksi contoh |
| [`docs/source/DatabaseSeeder.php`](./source/DatabaseSeeder.php) | Salinan referensi seeder (identik dengan asli) |
| [`app/Http/Middleware/EnsureRole.php`](../app/Http/Middleware/EnsureRole.php) | Menolak akses 403 bila role tidak sesuai (salinan: [`source/EnsureRole.php`](./source/EnsureRole.php)) |
| [`routes/web.php`](../routes/web.php) | Route `GET/POST /login`, `/logout`, dan pembagian panel per role |
| [`app/Http/Controllers/LoginController.php`](../app/Http/Controllers/LoginController.php) | Proses login, pengecekan sekolah aktif, logout |
| [`app/Models/User.php`](../app/Models/User.php) | Daftar `ROLES`, `isDinas()`, hash kata sandi |
| [`database/migrations/2026_10_12_000002_add_school_and_role_to_users.php`](../database/migrations/2026_10_12_000002_add_school_and_role_to_users.php) | Kolom `role` (default `operator`) dan `school_id` pada `users` |
| [`docs/AKUN_DEMO.csv`](./AKUN_DEMO.csv) | Daftar akun format CSV (impor pengguna memakai format serupa, lihat `DinasController@penggunaImpor`) |
| [`tests/Feature/ApiTokenSecurityTest.php`](../tests/Feature/ApiTokenSecurityTest.php) | Uji keamanan token API per akun |
