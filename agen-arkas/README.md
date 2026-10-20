# Agen TarikArkas (satu-klik per laptop sekolah)

`tarik-arkas.js` — Node 18+, CommonJS. Membaca `arkas.db` lokal (SQLCipher,
read-only), membangun `.xlsx` di memori (sheet Sekolah/RAPBS/KAS_Umum/Nota/
Pajak_NTPN), lalu `POST {server}/api/v1/arkas-push` (Bearer token sekolah).
Server memakai ulang `ArkasImportService` — seluruh aturan impor berlaku sama
(idempoten + migrasi kunci legacy→baru, tutup buku, tanpa hapus).

## Perintah

```
node tarik-arkas.js setup --server https://... --token XXX
node tarik-arkas.js            # kirim tahun berjalan
node tarik-arkas.js kirim --tahun 2025
node tarik-arkas.js cek        # tes koneksi saja
```

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

`POST /api/v1/arkas-push` (`auth.api` + `role:operator,kepsek`) — lihat
`SiapBOSP_API.md`. Diuji di `tests/Feature/ArkasImportTest.php`.
