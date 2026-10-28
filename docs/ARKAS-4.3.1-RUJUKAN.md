# Rujukan ARKAS Desktop 4.3.1 (bahan dari laptop operator)

Dokumen ini mencatat **apa yang benar-benar ada** di dalam paket ARKAS 4.3.1 yang
diunggah ke Google Drive pada 28 Okt 2026, dan apa artinya untuk SiapBOSP.

Semua angka di bawah dihasilkan dengan membaca berkasnya langsung (bukan dikira-kira):
`resources/app/package.json`, `.webpack/renderer/main_window/index.js`,
`main/db/empty-arkas.db`, `.env`, dan daftar folder `output/`.

---

## 1. Isi paket

| Berkas / folder | Ukuran | Isi |
|---|---|---|
| `arkas_desktop.exe` | 201 MB | Electron 39 (Chromium) |
| `resources/app/package.json` | 12,7 KB | `arkas-desktop` **v4.3.1**, React 18, Tailwind **2.2.19**, react-router 7, `@wartek-id/*` (design system Kemendikbud), `better-sqlite3-multiple-ciphers`, TypeORM, `pg` |
| `.webpack/renderer/main_window/index.js` | 3,55 MB | seluruh UI (React) + CSS di-inline |
| `.webpack/renderer/main_window/index.html` | 179 B | `<div id="root">` — SPA, tanpa server lokal |
| `.webpack/main/index.js` | 3,5 MB | proses utama: database, cetak, sinkronisasi |
| `main/db/empty-arkas.db` | 1,4 MB | **skema database ARKAS (SQLite biasa, terbaca)** |
| `main/db/scripts/scripts.zip` | 26 KB | skrip migrasi |
| `main/print/report/report.zip` | 525 KB | template cetak (BKU/RKAS/LPJ) |
| `Arkas/arkas.db` | 33 MB | **database pengguna — TERENKRIPSI** (lihat §4) |
| `Arkas/.env` | 654 B | konfigurasi endpoint (nilai di-base64) |
| `Arkas/output/*.pdf` | 19 berkas | dokumen cetak ARKAS **dengan data sekolah nyata** |
| `Arkas/Local Storage`, `Network`, `Preferences`, `backup/` | — | profil Electron (sesi, cookie, cadangan) |

## 2. Yang bisa dipakai

1. **Skema data resmi** — 44 tabel di `empty-arkas.db` (daftar: `docs/arkas-ref/daftar-tabel.txt`).
2. **Referensi sumber dana resmi** — `docs/arkas-ref/ref_sumber_dana.csv`.
3. **Jenis transaksi BKU resmi** — `docs/arkas-ref/ref_bku.csv` (28 jenis + kode BBU/BPU/SAB/SAT/PBT/PBS/PSS/STS/BNU/PTS).
4. **Periode** — `docs/arkas-ref/ref_periode.csv` (Triwulan 1–4 dan Januari–Desember).
5. **CSS asli** — diekstrak dari bundle: 18 modul ≈ 118 KB, berisi Tailwind 2.2.19 +
   design token Kemendikbud (`--color-gray-90: #2f3031`, `--color-red-60: #e02d38`, 213 token).
6. **Dokumen cetak contoh** — 19 PDF di `Arkas/output/` (BKU, BKU bank, BKU pajak, RAPBS,
   RKA unit, DPA unit, APBS, BHM, RPD). Ini acuan paling akurat untuk paritas cetak.
7. **Peta rute UI** — 47 rute di bundle, mis. `/anggaran`, `/anggaran/menyusun/{create,geser,ubah}`,
   `/tata-usaha` (= Penatausahaan), `/arsip`, `/api/bku/kasumum`, `/api/kertas-kerja/pergeseran`.

## 3. Skema `kas_umum` dan `rapbs` (kolom kunci)

```
kas_umum (26 kolom)
  id_kas_umum, id_kas_nota, id_rapbs_periode, kode_rekening, id_ref_bku, id_anggaran,
  parent_id_kas_umum, tanggal_transaksi, no_bukti, uraian, uraian_pajak, saldo, status_bku,
  volume, is_ppn, is_pph_21, is_pph_22, is_pph_23, is_pph_4, is_sspd, is_lock,
  tanggal_lock, soft_delete, create_date, last_update, updater_id

rapbs (27 kolom)
  id_rapbs, sekolah_id, id_anggaran, id_ref_kode, id_ref_tahun_anggaran, kode_rekening,
  id_barang, urutan, uraian, uraian_text, volume, satuan, harga_satuan, jumlah,
  v1..v4, s1..s4, keterangan, soft_delete, create_date, last_update, updater_id

aktivasi_bku (16 kolom)
  id_anggaran, id_periode, tanggal_aktivasi, tanggal_finish,
  saldo_awal_bank, saldo_awal_tunai, saldo_akhir_bank, saldo_akhir_tunai,
  saldo_awal_*_sisa, saldo_akhir_*_sisa, ...
```

Catatan: ARKAS menyimpan **satu angka `saldo`** per baris `kas_umum` (bukan saldo
tunai & bank terpisah seperti SiapBOSP), dan status BKU per baris (`status_bku`).

## 4. Database pengguna TIDAK terbaca

`Arkas/arkas.db` (33 MB) **terenkripsi**. Buktinya: 16 byte pertamanya
`32 16 BC 8F 33 24 15 0A 67 06 8F 6B 26 61 02 99`, bukan `SQLite format 3\0`;
pembaca SQLite menjawab *"file is not a database"*. Aplikasi memakai
`better-sqlite3-multiple-ciphers` (SQLCipher). Saya **tidak mencoba** membongkar
kuncinya — data keuangan sekolah bukan sesuatu yang perlu dibuka paksa, dan
SiapBOSP tidak membutuhkannya.

Yang bisa dipelajari dari sisi data: strukturnya (dari `empty-arkas.db`) dan
bentuk dokumennya (dari PDF di `output/`).

## 5. Endpoint (dari `Arkas/.env`, nilai asli di-base64)

| Kunci | Nilai setelah didekode |
|---|---|
| `APP_ENV` | `production` |
| `BASE_URL` | `https://api-rkas4.kemendikdasmen.go.id` |
| `AUTOUPDATE_BASE_URL` | `https://rilis.arkas.kemendikdasmen.go.id/releases/RKAS4/` |
| `SIPLAH_BASE_URL` | `https://api-rkas4.kemendikdasmen.go.id` |
| `SATU_EVENT_URL` | `https://events.belajar.id/` |

Ada satu kredensial (`SATU_EVENT_PASSWORD`) yang sengaja tidak dicatat di repo.

## 6. Selisih nyata dengan SiapBOSP

`ref_sumber_dana` ARKAS 4.3.1 (9 baris, lengkap dengan kode rekening):

| id | kode | nama ARKAS | padanan di SiapBOSP |
|---|---|---|---|
| 1 | `4.3.1.01.` | BOS Reguler | `reguler` = BOS Reguler ✓ |
| 3 | `4.3.1.03.` | BOS Daerah | `bosda` = **BOSP Daerah** ✗ beda nama |
| 5 | `4.3.1.05.` | Lainnya | `lainnya` = **Sumber Dana Lainnya** ✗ beda nama |
| 11 | `4.3.1.11` | BOS Afirmasi | `afirmasi` = **BOSP Afirmasi** ✗ beda nama |
| 12 | `4.3.1.12` | BOS Kinerja | `kinerja` = BOS Kinerja ✓ |
| 33 | `4.3.1.33.` | SiLPA BOS Reguler | — belum ada |
| 34 | `4.3.1.34` | SiLPA BOS Afirmasi | — belum ada |
| 35 | `4.3.1.35` | SiLPA BOS Kinerja | `silpa_kinerja` = **SiLPA BOSP Kinerja** ✗ beda nama |
| 99 | `0` | Saldo Awal | dihitung `KasService` (bukan sumber dana) |

Ringkas: ARKAS memakai awalan **BOS** (bukan BOSP) dan memisahkan **SiLPA per sumber
dana**; SiapBOSP memakai BOSP dan satu `SILPA`.

**Status 2026-10-28:** ejaan BOS sudah *diterima* di pemetaan tab
(`RkasHeader::TAB_SUMBER_DANA`) — impor berkas ARKAS yang menulis "BOS Daerah",
"SiLPA BOS Kinerja", atau "BOS Afirmasi" kini jatuh ke tab yang benar
(`tests/Feature/SumberDanaArkasTest.php`). Nama tampilan tetap "BOSP …" karena
itu yang dipakai **layar** ARKAS 4 dan dokumen sekolah. Yang sengaja belum
diubah: mengganti nama master `BosType` (karena itu migrasi data + mengubah
nama dokumen lama) dan membuat tab baru untuk SiLPA Reguler/Afirmasi (pita tab
ARKAS juga tidak punya tab itu; keduanya ikut sumber induknya).

## 6a. Yang sudah disamakan dengan keluaran cetak ARKAS 4.3.1

Acuan: `Arkas/output/bku-output.pdf` (BKU resmi sekolah, NPSN 40313912,
Januari 2026, BOSP Reguler Perubahan Revisi Ke 1) — teksnya dibaca dengan
`pypdf` mode layout, bukan ditebak.

| Butir | Sebelum | Sekarang (`pembukuan/bku-cetak`) |
|---|---|---|
| Kepala kolom | Tanggal · Kegiatan · Rekening · No. Bukti · Uraian · Penerimaan (Rp) · Pengeluaran (Rp) · Saldo (Rp) | nomor kolom **1–8** + Tanggal · Kode Kegiatan · Kode Rekening · No. Bukti · Uraian · Penerimaan · Pengeluaran · Saldo |
| Angka kolom | `Rp 10.000.000` | `10.000.000` (tanpa awalan Rp, pemisah titik) |
| Baris rekap | `JUMLAH` | `Jumlah` |
| Blok penutup | `Rp 14.000.000` | `Rp. 14.000.000`, rincian ditulis `- Saldo Bank` / `- Saldo Kas Tunai` |
| Tanda tangan | 3 kolom (+ Komite Sekolah), tempat = kabupaten/kota | 2 kolom: Menyetujui/Kepala Sekolah dan Bendahara; tempat = **Kec. <kecamatan>** |

Belum bisa ditiru dan sebabnya:
- **Dua baris saldo awal** (`Saldo Bank Bulan Desember 2025` + `Saldo Tunai ...`)
  — ARKAS memisah saldo bank/tunai karena punya tabel `aktivasi_bku` dengan
  saldo awal per jenis kas. `KasService::ledger` SiapBOSP mencampur SILPA tahun
  lalu ke satu angka `saldoAwalTahun`, sehingga memecahnya jadi dua baris akan
  memunculkan selisih palsu. Rincian bank/tunai tetap ada di blok penutup.
- **`Halaman 1 dari 2` di kaki tiap halaman** — butuh counter halaman cetak;
  CSS `@page` di Chromium tidak mendukung isi margin box, jadi tidak dipaksakan
  (footer jejak cetak "bukan dokumen resmi ARKAS" tetap ada).

## 7. Privasi

- `Arkas/arkas.db` dan PDF di `Arkas/output/` berisi data keuangan sekolah nyata.
  **Tidak ada** yang disalin ke repo ini; yang masuk repo hanya tabel referensi
  (kode & nama sumber dana, jenis BKU, periode) yang bukan data pribadi.
- Saran: setelah selesai dipakai sebagai rujukan, hapus folder `ARKAS` dari Drive
  (atau pindahkan ke folder terbatas), karena isinya database + kunci sesi.

## 8. Batas yang tetap ada

- Aplikasinya sendiri **tidak bisa dijalankan** di lingkungan kerja agen (Windows/Electron,
  tanpa GUI), jadi tampilan hidup belum pernah dilihat di sini; bukti = hasil bedah berkas.
- UI ARKAS tidak bisa dibuka di browser biasa: semua layar butuh jembatan IPC Electron
  ke database terenkripsi di atas.
