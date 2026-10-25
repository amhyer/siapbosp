# Audit: Bisakah aplikasi desktop menarik data dari ARKAS?

Tanggal: 2026-10-25 · Lingkup: jalur "Tarik Data ARKAS — 1 Klik" di aplikasi
portabel + agen `agen-arkas/tarik-arkas.js` + `ArkasImportService`.

Pertanyaan yang dijawab: **apakah aplikasi SiapBOSP di laptop bisa membaca
data ARKAS dan memasukkannya ke database?** Jawaban singkat: **jalur kodenya
lengkap dan berfungsi, tetapi ada tiga hal yang menentukan apakah ia bekerja
di laptop tertentu** — dan satu di antaranya adalah cacat yang sudah diperbaiki
di commit ini.

## 1. Rantai pemuatan data

| # | Langkah | Berkas | Status |
|---|---|---|---|
| 1 | Tombol muncul di Utility → Profil | `resources/views/utility/profil.blade.php`, `TarikArkasService::status()` | Hanya muncul bila `tersedia()` = aplikasi portabel **dan** `agen/TarikArkas/{node.exe,tarik.cjs}` ada |
| 2 | Klik → POST `/utility/tarik-arkas` | `TarikArkasController` | Validasi tahun, cek `tersedia()`, tangkap `RuntimeException` → pesan ramah |
| 3 | Panggil agen mode `lokal` | `TarikArkasService::eksporDb()` | `node.exe tarik.cjs lokal --json --tahun N --keluar … --db <arkas.db>`; batas 5 menit |
| 4 | Agen membaca `%AppData%\Roaming\Arkas\arkas.db` | `agen-arkas/tarik-arkas.js` | SQLCipher (key tetap, `legacy=4`), read-only; butuh `better-sqlite3-multiple-ciphers` |
| 5 | Agen menulis `.xlsx` (12 sheet) | `bangunExcel()` | exceljs; **sheet `RAPBS_Periode` sebelumnya tidak ada → diperbaiki** |
| 6 | Impor ke database | `ArkasImportService::import()` | Sama persis dengan unggah web: idempoten, tanpa hapus, menghormati tutup buku |
| 7 | Laporan hasil | `$lap` → kartu di Profil | Dibuat/diperbarui/dilewati + peringatan |

## 2. Temuan

### F-1 (diperbaiki) Agen tidak mengekspor sheet `RAPBS_Periode`

`ArkasImportService` membaca `RAPBS_Periode` untuk dua hal:

- `KAS_Umum.id_rapbs_periode` → **kode kegiatan transaksi** (`kode_kegiatan`
  pada detail transaksi, baris 808);
- **volume per tahap** RKAS (`periode_volume`, baris 429–430).

Agen tidak membuat sheet itu, sehingga pada penarikan 1-klik: transaksi masuk
**tanpa kode kegiatan** dan RKAS **tanpa `periode_volume`** — laporan per
kegiatan/tahap jadi kosong walau data mentahnya ada. Tes impor
(`ArkasImportTest`) justru menguji kedua nilai itu, jadi selisih ini tidak
terlihat oleh CI (tes memakai berkas buatan PhpSpreadsheet, bukan keluaran agen).

Perbaikan: `isi('RAPBS_Periode', qOpsional('SELECT * FROM rapbs_periode LIMIT 20000'))`
dengan `qOpsional()` agar versi ARKAS tanpa tabel itu tidak menggagalkan
seluruh ekspor.

### F-2 (diperbaiki) Paket bisa dibangun tanpa agen

`ZIP agen` (`storage/app/agen/TarikArkas-v1.0.0.zip`) **tidak ikut repository**
(`*.zip` di-.gitignore). `siapbos:paket-portable` hanya memberi **peringatan**
bila ZIP itu tidak ada, sehingga paket tetap terbuat — dan di laptop sekolah
tombol Tarik Data ARKAS **tidak muncul sama sekali** (`tersedia()` = false).

Perbaikan: perintah sekarang **gagal** (exit 1) bila ZIP agen tidak ada, kecuali
dijalankan dengan `--tanpa-agen`. Sejalan dengan penjagaan aset CSS sebelumnya.

### F-3 (diperbaiki) Filter `PTK` salah tipe

`WHERE soft_delete <> '1'` membandingkan kolom (kemungkinan INTEGER) dengan
string; di SQLite `1 <> '1'` bernilai **benar**, jadi PTK yang di-soft-delete
tetap ikut. Diganti `CAST(IFNULL(soft_delete,'0') AS TEXT) <> '1'`.

### F-4 (diketahui, belum diperbaiki) Tiga sheet importer tidak diekspor agen

`ArkasImportService` juga membaca `Penjab`, `Salur`, `Aktivasi_BKU`:

| Sheet | Dampak bila tidak ada |
|---|---|
| `Penjab` | Penandatangan (KS/Bendahara/Komite) tidak terisi dari ARKAS → harus diisi manual di Utility → Penanggung Jawab |
| `Salur` | Info silang "dana salur vs cair" tidak dihitung (baca-saja, tidak menulis data) |
| `Aktivasi_BKU` | Info saldo awal bank/tunai tidak dihitung (baca-saja) |

Tidak diperbaiki karena nama tabelnya di `arkas.db` belum terverifikasi di sini;
menebak nama tabel berisiko membuat ekspor gagal. Kedaftar sebagai celah
dikenal di `TarikArkasTest::test_agen_mengekspor_semua_sheet_yang_dibutuhkan_impor`
supaya tidak bertambah tanpa disadari.

### F-5 (batasan) Agen tidak punya tes otomatis

CI menjalankan PHP saja. `agen-arkas/tarik-arkas.js` tidak diuji; uji
ujung-ke-ujung hanya manual pada paket portabel (dokumentasi lama). Yang bisa
dijaga otomatis: kontrak sheet + versi agen (tes baru di `TarikArkasTest`).

## 3. Verifikasi yang dijalankan

| Uji | Cara | Hasil |
|---|---|---|
| Sintaks agen | `node --check agen-arkas/tarik-arkas.js` | bersih |
| Agen `info --json` | dijalankan dengan stub `better-sqlite3-multiple-ciphers` (modul native tidak bisa dikompilasi di sandbox) + fixture tabel ARKAS | `{"ok":true,"adaDb":true,"npsnFile":"20512345","agen":"1.0.1","versi":{"version_app":"4.3.0","tahun_aktif":"2025",…}}` |
| Agen `lokal --json` | idem, `--tahun 2025` | `{"ok":true,"npsn":"20512345","kb":14,"agen":"1.0.1"}` — berkas xlsx terbentuk |
| Isi xlsx | dibaca ulang dengan exceljs | 12 sheet: Sekolah, Anggaran, RAPBS, KAS_Umum, **RAPBS_Periode**, Nota, Pajak_NTPN, Ref_Kode, Ref_Rekening, Ref_Sumber_Dana, Ref_Satuan, PTK |
| Kontrak sheet | `preg_match_all` sumber agen vs pemanggilan `sheetAssoc` importer | semua sheet wajib ada; sisanya persis 3 celah dikenal (F-4) |
| `php -l` | `TarikArkasService`, `DiagnosaArkasController`, `CekArkas`, `PaketPortable`, tes | bersih |

Yang **tidak** bisa diverifikasi di lingkungan ini dan karenanya **tidak**
diklaim: membaca `arkas.db` asli (SQLCipher + modul native win32-x64 tidak
tersedia), kecocokan nama tabel/kolom ARKAS 4 yang sebenarnya, dan eksekusi di
Windows.

## 4. Cara memastikan di laptop sekolah (tanpa CLI)

Buka di browser laptop yang menjalankan SiapBOSP:

```
http://127.0.0.1:8000/_arkas
```

Halaman ini (hanya bisa dibuka dari localhost) menampilkan: apakah aplikasi
portabel, ukuran `agen/TarikArkas/node.exe` dan `tarik.cjs`, path + keberadaan
`arkas.db`, versi agen (**1.0.1** = sudah memuat perbaikan `RAPBS_Periode`),
versi ARKAS, NPSN, dan jumlah master yang terbaca.

Lalu klik **"jalankan untuk tahun …"** (`?_uji`/`?uji=1`). Agen benar-benar
membaca ARKAS dan menulis Excel sementara, lalu halaman ini melaporkan **sheet
+ jumlah baris** — tanpa mengimpor apa pun ke database. Bila `RAPBS_Periode`
tidak ada di daftar, berarti agen di laptop itu masih versi lama.

Padanan CLI:

```bash
php artisan siapbos:cek-arkas          # status agen + database
php artisan siapbos:cek-arkas --uji    # tarik nyata + daftar sheet
```

## 5. Yang perlu dilakukan agar laptop bisa menarik data

1. **Paket aplikasi harus membawa agen.** Folder `agen/TarikArkas/` (node.exe +
   tarik.cjs) wajib ada. Paket yang dibangun tanpa ZIP agen sekarang ditolak
   oleh `siapbos:paket-portable`, tetapi paket lama yang sudah terlanjur dibuat
   tanpa agen tetap harus diganti.
2. **ARKAS harus pernah dibuka** di laptop itu sehingga `arkas.db` ada, dan
   sebaiknya ditutup saat menarik (SQLite bisa terkunci — pesan galatnya sudah
   diterjemahkan di `pesanRamah()`).
3. **NPSN profil = NPSN ARKAS.** Bila berbeda, penarikan ditolak untuk mencegah
   data tertukar.
4. **Agen versi 1.0.1+** agar kode kegiatan transaksi & volume per tahap terisi.
   Versi lama tetap bisa menarik data, tetapi dua kolom itu kosong.

## 6. Kesimpulan

- Jalur tarik data dari ARKAS **sudah ada dan utuh** dari sisi kode: baca
  `arkas.db` (read-only) → Excel → impor idempoten ke database.
- Cacat nyata yang ditemukan: sheet `RAPBS_Periode` hilang (F-1), paket bisa
  terbit tanpa agen (F-2), filter PTK salah tipe (F-3). Ketiganya sudah
  diperbaiki; F-4/F-5 dicatat sebagai batasan.
- Apakah ia berfungsi **di laptop tertentu** bergantung pada agen terbundel +
  `arkas.db` ada + versi agen. `/_arkas` dan `siapbos:cek-arkas --uji`
  menjawabnya dalam satu klik, dengan bukti (sheet + jumlah baris).
