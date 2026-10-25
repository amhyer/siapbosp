# Agen TarikArkas (satu-klik per laptop sekolah)

`tarik-arkas.js` — Node 18+, CommonJS (versi agen saat ini **1.0.1**, dilaporkan
lewat `info --json` sebagai `agen`). Membaca `arkas.db` lokal (SQLCipher,
read-only), membangun `.xlsx` di memori — sheet: Sekolah, Anggaran, RAPBS,
**RAPBS_Periode**, KAS_Umum, Nota, Pajak_NTPN, Ref_Kode, Ref_Rekening,
Ref_Sumber_Dana, Ref_Satuan, PTK — lalu `POST {server}/api/v1/arkas-push`
(Bearer token sekolah).

`RAPBS_Periode` WAJIB ada: dipakai `ArkasImportService` untuk kode kegiatan
transaksi dan `periode_volume` RKAS. Tanpa sheet itu data tetap masuk, tetapi
dua kolom tersebut kosong (cacat agen ≤1.0.0).
Server menyimpan unggahan di disk privat lalu memprosesnya melalui antrean `imports`
dengan `ArkasImportService` — seluruh aturan impor berlaku sama (idempoten +
migrasi kunci legacy→baru, tutup buku, tanpa hapus). Agen menunggu status melalui
`GET /api/v1/arkas-import/{import_id}`; pekerjaan yang tetap berjalan setelah 25 menit
tidak diunggah ulang otomatis dan harus diperiksa menggunakan ID tersebut.

## Perintah

```
node tarik-arkas.js setup --server https://... --token XXX
node tarik-arkas.js            # kirim tahun berjalan
node tarik-arkas.js kirim --tahun 2025
node tarik-arkas.js cek        # tes koneksi saja
node tarik-arkas.js lokal --json --keluar out.xlsx --tahun 2025
node tarik-arkas.js info --json                # cek ARKAS laptop ini
node tarik-arkas.js lokal --json --db C:\...\arkas.db --keluar out.xlsx
```

`lokal` hanya membaca ARKAS laptop ini dan menulis berkas Excel — tanpa
server/token, dipakai tombol "Tarik Data ARKAS" di dalam aplikasi SiapBOSP
portabel (`TarikArkasService`), yang mengimpornya sendiri lewat
`ArkasImportService`. `--db` menggantikan path ARKAS bila env proses
pemanggil kosong (`php artisan serve` mewarisi `$_ENV` yang bisa kosong).

Konfigurasi: `%APPDATA%\SiapBOS-Agen\config.json` (server + token).

## Membuat paket portable (`dist-portable/TarikArkas/`)

Single-`.exe` murni tidak memungkinkan: modul SQLite-native (SQLCipher)
wajib berupa file `.node` di samping executable (`pkg` gagal resolve
dependency exceljs; SEA/postject gagal sentinel di Node 24). Kemasan resmi:

```
TarikArkas/
  node.exe            (runtime, tanpa perlu install Node)
  tarik.cjs           (bundel esbuild: agen + exceljs)
  lib/better-sqlite3-multiple-ciphers/   (hanya prebuild win32-x64)
  TarikArkas.bat      (klik 2x)
  BACA-DULU.txt       (panduan operator)
```

Bangun ulang (dari folder ini):

```bat
npm install --no-audit --no-fund
npx esbuild tarik-arkas.js --bundle --platform=node --format=cjs --external:better-sqlite3-multiple-ciphers --outfile=dist/tarik.cjs
mkdir dist-portable\TarikArkas\lib ...
```

## Endpoint server terkait

`POST /api/v1/arkas-push` mengembalikan HTTP 202 dan `import_id`; poll
`GET /api/v1/arkas-import/{import_id}` untuk hasil. Keduanya memerlukan
`auth.api` + `role:operator,kepsek`, dan status hanya dapat dibaca pemilik pekerjaan.
Batas unggahan 20 MB; polling dibatasi 25/menit per akun atau IP. Lihat `SiapBOSP_API.md` dan
`docs/AUDIT-KEAMANAN.md` untuk pengoperasian queue worker. Diuji di
`tests/Feature/ArkasImportTest.php`.
