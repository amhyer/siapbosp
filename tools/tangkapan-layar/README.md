# Tangkap Layar SiapBOSP (semua menu & halaman)

Alat kecil untuk menghasilkan **screenshot PNG seluruh menu dan halaman** SiapBOSP
secara otomatis — cocok untuk dokumentasi, lampiran laporan, atau bahan evaluasi
tampilan terhadap ARKAS 4.

> Dijalankan **di komputer yang menjalankan aplikasi** (laptop operator/server
> lokal), karena di situlah PHP + peramban tersedia.

## Isi

| Berkas | Isi |
|---|---|
| `foto.cjs` | program utama: login per peran, kunjungi halaman, simpan PNG |
| `halaman.json` | daftar halaman (86 entri) — tambah/ubah di sini, tanpa mengubah kode |
| `foto.bat` | klik-2x untuk Windows (memasang dependensi lalu menjalankan) |
| `foto.test.cjs` | uji bagian tanpa peramban (`npm run uji`) |

## Cara pakai

### 1. Jalankan aplikasi

```bat
cd C:\path\ke\siapbosp
php artisan serve
```

Biarkan jendela itu terbuka. Alamat default `http://127.0.0.1:8000`.

Pastikan data demo sudah ada (`php artisan migrate:fresh --seed` pada database
**uji**, bukan database produksi — perintah ini menghapus data).

### 2. Tangkap layar

**Windows (paling mudah):** klik 2x `foto.bat`.

**Terminal:**

```bat
cd tools\tangkapan-layar
npm install --no-audit --no-fund
node foto.cjs
```

Sekali jalan mengambil **86 halaman** (≈3–6 menit): 70 halaman panel sekolah
(operator), 3 halaman sampel kepala sekolah, 13 halaman panel dinas, plus
halaman login.

### 3. Lihat hasilnya

```
docs/tangkapan-layar/
  index.html          ← galeri: buka di peramban (klik 2x)
  index.md            ← daftar tabel: halaman, status, berkas
  operator/01-....png
  kepsek/...
  dinas/...
```

## Pilihan

```bat
node foto.cjs --daftar                       :: cetak rencana halaman saja (tanpa peramban)
node foto.cjs --hanya rkas,arsip             :: hanya halaman yang cocok
node foto.cjs --peran dinas                  :: satu peran saja
node foto.cjs --base http://192.168.1.10:8080
node foto.cjs --lebar 1600 --tanpa-penuh     :: hanya viewport, bukan halaman penuh
node foto.cjs --browser "C:\Program Files\Google\Chrome\Application\chrome.exe"
```

## Bagaimana halaman yang butuh id ditangani

Halaman seperti `/rkas/{id}`, `/pengeluaran/belanja/{id}/kwitansi`, dan
`/dinas/sekolah/{id}` tidak punya id tetap. Alat ini membuka halaman daftarnya
(`/rkas`, `/pengeluaran/belanja`, `/dinas/sekolah`), mengambil tautan pertama
yang cocok, lalu memotret halaman detail itu. Kalau datanya kosong, halaman
ditandai **dilewati** di `index.md` — bukan dianggap berhasil.

## Tanpa memasang apa pun (Node belum ada)

Pasang Node.js LTS dari <https://nodejs.org> (sekali saja), lalu ulangi langkah 2.
Alat ini **tidak** mengunduh Chromium: ia memakai Edge/Chrome yang sudah ada di
komputer Anda (terdeteksi otomatis), jadi hanya `puppeteer-core` (~1 MB) yang dipasang.

## Pemecahan masalah

| Gejala | Sebab / tindakan |
|---|---|
| `puppeteer-core belum terpasang` | jalankan `npm install --no-audit --no-fund` di folder ini |
| `Peramban (Edge/Chrome) tidak ditemukan` | pasang Edge/Chrome, atau pakai `--browser "…\chrome.exe"` |
| `Login gagal untuk …` | database belum di-seed, atau kata sandi bukan `password` |
| Semua halaman `HTTP 500` | aplikasi belum jalan / `APP_KEY` kosong / migrasi belum dijalankan |
| Banyak halaman **dilewati** | database sekolah demo kosong — jalankan seeder atau impor data ARKAS dulu |
| Tampilan tanpa CSS | jalankan `npm run build` lebih dulu (aset `public/build`) |
| Hasil dobel/hilang saat diulang | aman: nama berkas deterministik, dijalankan ulang menimpa yang lama |

## Catatan

- Alat ini **hanya membaca** (GET). Tidak ada tombol yang ditekan, tidak ada data
  yang berubah — kecuali proses login yang membuat entri sesi biasa.
- Menjalankan terhadap **database demo/uji** sangat dianjurkan: halaman seperti
  Log Sistem dan Backup ikut tertangkap.
