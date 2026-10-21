# Spesifikasi Modul Impor ARKAS → SiapBOS (via Excel)

**Status:** spesifikasi siap dikerjakan · **Dibuat:** 15 Oktober 2026
**Tujuan:** alur kerja Excel → SiapBOS — data ARKAS yang sudah diekspor ke
`ARKAS-Lengkap-{TAHUN|SEMUA}.xlsx` (oleh `export.js` di folder `ArkasExcel`)
dimasukkan ke SiapBOS per NPSN, dan **impor ulang = update**, bukan duplikat.

---

## 1. Sumber data (fakta, bukan asumsi)

File: `ArkasExcel/ARKAS-Lengkap-SEMUA.xlsx` (atau `-2025.xlsx` untuk satu tahun).
Sheet yang dipakai: **RAPBS**, **KAS_Umum**, **Nota** (+ **Sekolah** untuk NPSN).

Kolom aktual tiap sheet (dari `export.js` + skema `arkas.db`, dicek 15 Okt 2026):

- **RAPBS** = `SELECT r.*, a.tahun_anggaran, sumber_dana` → ada kunci stabil
  `id_rapbs`. Kolom penting: `id_rapbs`, `id_anggaran`, `kode_rekening`,
  `uraian`, `volume`, `satuan`, `harga_satuan`, `jumlah`,
  `soft_delete`, `tahun_anggaran`, `sumber_dana`.
- **KAS_Umum** = kolom pilihan (18 kolom): `tanggal_transaksi`, `no_bukti`,
  `uraian`, `kode_rekening`, `jenis_bku`, `kode_bku`, `saldo`, `status_bku`,
  `volume`, `is_ppn`, `is_pph_21/22/23`, `is_pph_4`, `is_sspd`, `is_lock`,
  `tahun_anggaran`, `sumber_dana`, `id_anggaran`.
  ⚠️ **Tidak ada kunci stabil** (`id_kas_umum` / `id_kas_nota` tidak ikut
  diekspor) dan **tidak ada kolom nominal transaksi** (`saldo` = saldo
  berjalan, bukan nilai baris). Lihat §4 — wajib patch `export.js` dulu.
- **Nota** = `SELECT * FROM kas_umum_nota` → ada kunci stabil `id_kas_nota`.
  Kolom penting: `id_kas_nota`, `no_bukti`, `tanggal_nota`, `no_nota`,
  `nama_toko`, `total`, `kode_rekening`, `has_ppn`, `has_pph_22`,
  `soft_delete`.
- **Sekolah** = `SELECT * FROM mst_sekolah` → kolom `npsn`, `nama`.
  Cocokkan `npsn` → `schools.npsn`.

Target di SiapBOS (skema `database/migrations`, 17 migrasi — sudah jalan semua):

| Sumber ARKAS | Tabel SiapBOS | Kunci idempoten |
|---|---|---|
| RAPBS per tahun | `rkas_headers` (1 header/tahun) + `rkas_details` | `rkas_details.arkas_id` = `id_rapbs` (kolom baru) |
| KAS_Umum | `kas_transactions` (`type` belanja/pendapatan ikut `rekenings.tipe`) + `kas_details` | `kas_transactions.arkas_id` = `id_kas_umum` (kolom baru) |
| Nota + flag pajak | `kas_taxes` (`status` = `belum`), terhubung via `id_kas_nota` | `kas_taxes.arkas_id` = `id_kas_nota` + jenis pajak (kolom baru) |
| Sekolah.npsn | `schools.npsn` | cocok eksak; tolak bila tidak ada / `is_active` = false |

Model memakai `$guarded = []` dan trait `BelongsToSchool` (auto `school_id`
+ global scope), jadi impor yang jalan sebagai operator otomatis terisolasi
per sekolah.

---

## 2. Prasyarat (kerjakan berurutan)

1. **Patch `export.js`** (folder `ArkasExcel`, 1 baris query KAS_Umum):
   tambahkan `k.id_kas_umum AS id_kas_umum, k.id_kas_nota AS id_kas_nota,`
   `k.id_rapbs_periode AS id_rapbs_periode` ke SELECT sheet `KAS_Umum`
   (`export.js` ≈ baris query `S('KAS_Umum', ...)`), lalu export ulang
   (`MENU.bat` → pilihan 1). Tanpa ini impor KAS_Umum tidak bisa idempoten.
   Query juga ikutkan `k.soft_delete AS soft_delete` (baris terhapus ARKAS
   wajib diskip — 1.638/3.569 baris kas_umum ber-soft_delete=1 di DB contoh).
   Kebenaran kolom terverifikasi langsung dari `arkas.db` (SQLCipher,
   read-only, 15 Okt 2026): `kas_umum` memuat `id_kas_umum`, `id_kas_nota`,
   `id_rapbs_periode`, `soft_delete`, dan `parent_id_kas_umum` — dan
   `Pergeseran Uang di Bank.parent_id_kas_umum = Tarik Tunai.id_kas_umum`
   (tanggal+nominal sama), membuktikan Pergeseran adalah kaki kedua yang
   wajib diskip (aturan §4).
2. PHP 8.3+ + Composer terinstal (saat ini belum ada di PC ini), lalu
   `composer install` di `siapbosp-app-v12`, database SQLite sudah
   termigrasi + terseed (28 tabel, 6 user, 2 sekolah — terverifikasi).

---

## 3. Aturan impor (wajib)

1. **Identitas dulu:** baca NPSN dari sheet Sekolah → cari `schools.npsn`.
   Gagal/tidak aktif → tolak seluruh file, tanpa tulis apa pun.
2. **Idempoten:** semua tulis lewat `updateOrCreate(['school_id'=>…, 'arkas_id'=>…])`.
   Kunci: `id_rapbs` / `id_kas_umum` / (`id_kas_nota` + jenis pajak).
   Migrasi baru menambah kolom `arkas_id` nullable + unique
   `(school_id, arkas_id)` di `rkas_details`, `kas_transactions`, `kas_taxes`.
3. **Jangan duplikat RKAS:** impor RKAS yang ada (`RkasController@impor`)
   selalu bikin revisi baru — modul ini TIDAK boleh meniru itu. Cari header
   draft tahun berjalan; bila tidak ada, buat 1 header draft
   (`catatan` = `Impor ARKAS {tanggal file}`); bila header sudah
   disetujui/dikunci → tolak impor RKAS tahun itu (lapor, lanjutkan sheet lain).
4. **Tutup buku dihormati:** sebelum tulis/skrip transaksi, cek
   `TutupBuku::terkunci($schoolId, $tahun, $stageId)` (pola di
   `PengeluaranController.php:51`). Baris periode terkunci → skip + laporkan.
5. **Rekening tak dikenal:** petakan `kode_rekening` → `rekenings.kode`
   (pola `RkasController@impor:60`); yang tak dikenal → skip + laporkan,
   jangan gagalkan seluruh file.
6. **Tidak menghapus:** baris yang ada di SiapBOS tapi tidak ada di file
   TIDAK dihapus — masuk daftar selisih di laporan hasil.
7. **Satu transaksi DB** per file; `soft_delete = 1` di ARKAS = skip
   (+ hitung sebagai dilewati, bukan error).
8. **Laporan hasil wajib:** jumlah dibuat / diperbarui / dilewati per sheet,
   daftar kode rekening tak dikenal, daftar periode terkunci yang diskip,
   daftar selisih hapus — tampil di layar + simpan ke `activity_logs`.

---

## 4. Masalah terbuka — KEPUTUSAN FINAL (diisi 15 Okt 2026 berdasar `ARKAS-Lengkap-2025.xlsx`, NPSN 40313912)

> Diverifikasi dengan membaca langsung file nyata (887 baris KAS_Umum,
> 235 Nota aktif 2025, 1546 RAPBS aktif 2025, 92 Pajak_NTPN). Implementasi di
> `app/Services/ArkasImportService.php` mengikuti keputusan di bawah ini.

- **Nominal transaksi KAS_Umum: DIPUTUSKAN — kolom `saldo` adalah nominal baris.**
  Klaim awal "tidak ada kolom nominal" terbukti keliru untuk export ini:
  nilai `saldo` cocok 1:1 dengan `Nota.total` per `no_bukti` (235/235 cocok,
  mis. BPU02 Yusuf = 375.000 di kedua sheet) dan konsisten lintas jenis
  (BBU01 Tahap 1 = 177.300.000, Tarik Tunai = 88.650.000). Opsi A (join Nota)
  tidak diperlukan sebagai sumber nominal; `Nota.total` hanya validasi silang
  implisit via kecocokan. Opsi C (lewati + laporkan) untuk baris nominal ≤ 0
  (Saldo Awal, Bunga/Pajak Bunga 0). File lama tanpa `id_kas_umum` memakai
  kunci legacy `sha1(tanggal|no_bukti|uraian|kode_rekening|jenis_bku|kode_bku
  |saldo|tahun|sumber_dana|volume)` — TANPA `id_anggaran`, karena ARKAS
  menduplikasi baris per anggaran (mis. BBU01 ×2, BPU13 ×2 identik kecuali
  `id_anggaran`) yang harus menyatu jadi 1 transaksi.
- **`type` transaksi: DIPUTUSKAN — dari `jenis_bku` (BUKAN `kode_bku`).**
  `kode_bku` ambigu dan tak bisa dipakai: `BBU` dipakai bersama oleh
  "Terima Dana BOS" dan "Tarik Tunai"; `PBS` dipakai bersama oleh
  "Pergeseran Tunai" dan "Pajak Belanja Setor". Pemetaan: Terima Dana BOS →
  `pendapatan` (transfer); Kas Keluar → `belanja` tunai; Kas Keluar Non Tunai
  → `belanja` transfer; Tarik Tunai → `ambil_simpanan`; Pergeseran Tunai →
  SKIP (kaki kedua Tarik Tunai: tanggal+nominal selalu sama, mis. 12/02 dan
  17/04 @88.650.000 — impor keduanya akan saling meniadakan di ledger
  single-entry); Pajak Belanja Terima → `kas_taxes` status `belum` menempel
  belanja; Pajak Belanja Setor → transaksi `setor_pajak` + tandai `sudah`;
  Saldo Awal Bank/Tunai → SKIP (SILPA dihitung otomatis); Bunga Bank bernominal
  → `pendapatan`, bernilai 0 → SKIP; Pajak Bunga → SKIP (tak ada modelnya).
  File 2025 memuat 4 Terima Dana BOS, 623 belanja, 3 Tarik Tunai, 61+61
  terima/setor pajak, 64 saldo awal, 68 bunga/pajak bunga.
- **`stage_id`: DIPUTUSKAN — parse "Tahap N" dari uraian, fallback bulan.**
  Seed aktual = **5 tahap/tahun** (bukan 10 seperti perkiraan awal —
  `DatabaseSeeder.php:74`, stages 2026 = 5). Aturan: `/tahap\s*(\d)/i` pada
  uraian/no_bukti (terbukti benar: BBU01 Jan "Tahap 1" → 1, BBU03 Agu
  "Tahap 2" → 2); bila tak ada, `min(5, ceil(bulan×5/12))`. Tutup buku dicek
  pada stage hasil pemetaan ini.
- **Pajak SiapBOS vs ARKAS: DIPUTUSKAN — 1 baris `kas_taxes` per (transaksi, jenis).**
  Flag `is_ppn/is_pph_21/22/23/is_pph_4` → `ppn/pph21/pph22/pph23/pph42`
  (kelimanya ada di master; 122 baris KAS 2025 berflag, semuanya di baris
  Terima/Setor — baris belanja sendiri berflag 0). Terima menempel ke belanja
  via (tanggal SAMA + kode rekening SAMA, total ≥ nominal, terkecil);
  fallback tanggal sama kode apa pun. `has_ppn/has_pph_22` Nota 2025 semuanya
  0 sehingga tidak dipakai. NTPN via rantai
  `belanja.no_bukti → Nota.no_bukti → Pajak_NTPN.ntpn` (29 NTPN tersedia
  untuk 2025); legacy tanpa NTPN → null + dilaporkan.
- **BKU: DIPUTUSKAN — nomor ARKAS hanya referensi.**
  `no_bku` SiapBOS via `KasService::nextNoBku` (urut per bulan) hanya saat
  baris BARU; impor ulang mempertahankan nomor lama. `no_bukti` ARKAS
  (BBU01, BPU13, …) disimpan di kolom `no_bukti`.

Penyimpangan dari spesifikasi awal (disengaja, beralasan):
1. Skema nyata: hanya `kas_transactions` yang punya `school_id`
   (`KasTax`/`RkasDetail` tanpa trait tenant). Maka unik idempoten =
   `(school_id, arkas_id)` di kas_transactions,
   `(rkas_header_id, arkas_id)` di rkas_details,
   `(kas_transaction_id, arkas_id)` di kas_taxes — ketiganya setara
   unik-per-sekolah. Migrasi: `2026_10_15_000015_*`.
2. Cakupan rekening master (14 → 56 per 16 Okt 2026, migrasi `2026_10_16_000019`) vs ARKAS (44 kode aktif 2025 di file — koreksi dari angka 34) — 2 kode tabrakan diselaraskan ke nama ARKAS
   (`5.1.02.01.01.0001` → Bahan Bangunan dan Konstruksi, `5.1.02.04.01.0004` → Perjalanan Dinas Paket Meeting). Transaksi tetap dibuat
   (total utuh), rincian hanya untuk kode dikenal; impor ulang SETELAH Dinas
   menambah master akan melengkapi rincian otomatis (updateOrCreate).

---

## 5. Struktur implementasi yang disarankan

- `app/Services/ArkasImportService.php` — seluruh logika baca Excel
  (PhpSpreadsheet sudah ada di `composer.json`) + mapping + tulis.
- `app/Http/Controllers/ImportArkasController.php` — form upload
  (`role:operator,kepsek`), validasi `.xlsx` ≤ 20 MB, panggil service,
  tampilkan laporan hasil.
- Route di grup sekolah `routes/web.php`: `GET /impor-arkas` (form),
  `POST /impor-arkas` (proses). Nama route: `impor-arkas.*`.
- Migrasi: `xxxx_add_arkas_id_to_imported_tables.php`.
- Test: `tests/Feature/ArkasImportTest.php` — (a) impor 2x tidak duplikat,
  (b) sekolah A tidak menyentuh data sekolah B, (c) periode tutup buku
  diskip, (d) NPSN tak dikenal ditolak.
- Fixture: file xlsx kecil (±5 baris/sheet) di `tests/Fixtures/`.

## 6. Cakupan penuh ARKAS → SiapBOS (inventarisasi DB lokal 15 Okt 2026)

Hasil baca langsung `arkas.db` (56 tabel, 1 sekolah 40313912). Yang ditarik:

| Menu ARKAS | Sumber | Tujuan SiapBOS | Aturan |
|---|---|---|---|
| Perencanaan RAPBS | RAPBS | `rkas_details` | idempoten `rapbs:id_rapbs`, kode asing diskip + nama dilampirkan |
| Anggaran/pagu | Anggaran | `allocations` | revisi terakhir per sumber; **hanya isi bila kosong**, pagu Dinas tak ditimpa (selisih dilaporkan) |
| Penatausahaan kas | KAS_Umum + Nota + Pajak_NTPN | `kas_*` | sesuai §3–§4 |
| Penjab (KS/bendahara/komite) | sekolah_penjab thn berjalan | `penandatangans` | tambah bila (nama+jabatan) belum ada |
| Profil sekolah | mst_sekolah | `schools` | **isi field kosong saja** (alamat); nama/data Dinas tak ditimpa |
| Salur dana | Salur | — | validasi silang vs penerimaan (lapor, tanpa tulis) |
| Saldo awal | Aktivasi_BKU Jan | — | info di laporan (SiapBOS hitung SILPA sendiri) |
| GTK | PTK | — | **tak ada padanan**: hanya dicatat jumlahnya di laporan |
| Akun ARKAS | pengguna | — | **sengaja tak diimpor** (kredensial asing) |
| Pengesahan/RKA/report | pengesahan dkk. | — | kosong / laporan baca-saja ARKAS, dilewati |

`ref_rekening` dipakai untuk melampirkan NAMA kode asing agar Dinas mudah
menambah master. `RAPBS_Periode` (rincian triwulan) diskip — RKAS SiapBOS tahunan.

---

## 7. Referensi silang

- Skema ARKAS: §1 di atas (hasil baca langsung `arkas.db`, 5595 rapbs /
  3569 kas_umum / 1456 nota).
- Skema SiapBOS: `BLUEPRINT_SiapBOSP.md` §5, `SiapBOSP_API.md`.
- Pola impor yang ada: `RkasController@impor` (`:51-79`),
  `DinasController@imporSekolah` (`:70`), `@penggunaImpor` (`:185`).
- Perintah siap tempel untuk agen: `docs/PERINTAH-AGEN-IMPOR-ARKAS.md`.

---

## 8. HPP, pajak otomatis, rekomendasi revisi (15 Okt 2026)

- **HPP (Halaman Pemetaan Pembelanjaan)**: `GET/POST /hpp` (menu Perencanaan).
  Tiap rincian belanja dipetakan ke rincian RKAS (kolom `kas_details.rkas_detail_id`)
  + sumber `langsung/siplah`. Impor menandai otomatis dari
  `Nota.is_beli_di_siplah` via `no_bukti` (2025: 1 nota, BPU71 — tanpa kode
  rekening sehingga tanpa rincian, sesuai aturan kode asing).
- **Pajak otomatis**: form belanja mengisi tarif baku (PPN 11%, PPh22 1,5%,
  PPh23 2%, PPh4(2) 10%, PPh21 5%) × total belanja; bisa diubah manual.
- **Rekomendasi revisi**: form Susun RKAS menampilkan peringatan untuk tiap
  rekening yang sudah terealisasi (label SIPLah bila ada) + tombol + Bawa
  yang mengisi baris revisi (pergeseran/perubahan).

---

## 9. Modul presisi & rekonsiliasi (15 Okt 2026)

- Setiap transaksi menyimpan **nomor BKU ARKAS** (`arkas_bku` = `no_bukti`
  mis. BPU13, `arkas_jenis_bku`) di samping nomor BKU SiapBOS — kolom
  migrasi `2026_10_15_000017` + backfill 557 baris lama.
- Halaman **Pembukuan → Rekonsiliasi ARKAS** (`GET/POST /rekonsiliasi`,
  baca-saja): unggah file → cocok/selisih-nominal/hilang/lebih per baris,
  saldo berjalan per bulan ARKAS vs SiapBOS, dan **peta BKU SiapBOS ↔ ARKAS**.
  Verifikasi file nyata 2025: **690/690 cocok, 0 selisih**.


---

## 10. Modul Laporan ARKAS — laporan resmi format workbook ARKAS v1.4 (18 Okt 2026)

Workbook pendamping **EXARKAS_1.4_1.xlsm** (referensi eksternal lama) berisi lembar
kertas kerja resmi. Nilai kolom/urutan disalin persis dari workbook itu
(terbaca via PhpSpreadsheet), bukan dari perkiraan.

### 10.1 Tambah kolom & tabel (migrasi 2026_10_16_000021)

| Objek | Kolom/isi | Keterangan |
|---|---|---|
| rkas_kodes (tabel baru) | school_id, tahun, kode ('03.'), uraian, level 1–3 | master kode kegiatan → nama Program/Kegiatan format ARKAS |
| kas_notas (tabel baru) | 
o_bukti, tanggal_nota, no_nota, nama_toko, alamat_toko, no_telp, npwp, total, lunas_bayar | Daftar Nota + penerima |
| kas_transactions.arkas_nota_id | id_kas_nota mentah ARKAS | penghubung BKU ↔ nota |
| kas_details.volume | kolom olume KAS_Umum | volume transaksi (harga satuan = nilai/volume) |
| kas_details.periode_volume | JSON {"1": v1, ...} dari RAPBS_Periode (v1..v4, fallback urutan baris) | alokasi Tahap 1..N |

### 10.2 Halaman baru (menu **📋 Laporan ARKAS**, semua baca-saja)

- **RKAS Bulanan** — No|Program|Kegiatan|Rekening Belanja|Uraian|Anggaran|Belanja|Satuan|Harga Satuan|Total|Status - Bulan
- **Rincian RKAS per Tahap** — No|Kode Rekening|Kode Program|Uraian|Volume|Satuan|Tarif|Jumlah|Tahap 1..N
- **Realisasi Anggaran** — Kode Rekening|Uraian|Anggaran|Realisasi s.d. Bulan Lalu|Realisasi Bulan Ini|s.d. Bulan Ini|Selisih/Kurang
- **Realisasi Penerimaan & Belanja** — 3 kategori ARKAS: Operasional (5.1.02), Modal Peralatan+Mesin (5.2.02), Modal Aset Tetap Lainnya (5.2.05) + Jumlah
- **Realisasi Habis Pakai** (5.1.02.01) & **Realisasi Modal** (5.2) — Kode Rekening|Uraian|Tanggal Transaksi|Volume|Harga Satuan|Jumlah
- **Daftar Kas Umum** — 
o, tgl_transaksi, no_bukti, tanggal_nota, no_nota, uraian, kode_rekening, uraian_rekening, volume, nominal, kegiatan, penerima
- **Daftar Nota** — 
o, no_bukti, tanggal_nota, no_nota, pembayaran, nama_toko/penerima, alamat, total, lunas_bayar, tgl_transaksi
- **Register Penutupan Kas** (+ **cetak**: Register + **Berita Acara Pemeriksaan Kas**, denominasi lembar/keping otomatis, saldo bank dipisah)

### 10.3 BKU memakai kolom format ARKAS

Halaman + cetak + CSV BKU diubah ke kolom format ARKAS:
Tanggal|Kegiatan|Rekening|No. Bukti|Uraian|Penerimaan|Pengeluaran|Saldo,
kaki tabel Jumlah + Saldo Buku Kas Umum.

### 10.4 Faktor penyelamat data (root cause lama)

1. **Header sheet ganda** — file ekspor ARKAS terbaru memuat dua baris header
   di Sekolah. sheetAssoc() kini memilih baris dengan skor kunci tertinggi
   (seri → baris paling bawah).
2. **keg-kas:0 terpecahkan** — file ARKAS-Lengkap-2025.xlsx lama
   (pra-patch) tidak punya kolom id_kas_umum|id_kas_nota|id_rapbs_periode.
   Ekspor ulang via 
ode export.js 2025 lalu impor ulang ⇒
   kas_details.kode_kegiatan terisi penuh (328/328 versi 18 Okt 2026).
3. **Normalisasi artefak** — baris turunan impor versi lama (621) dihapus
   sekali (bukan data operator) lalu impor ulang ⇒ rekonsiliasi
   **362/362 cocok, 0 selisih, 0 hilang, 0 lebih**; master_kode 905;
   235 nota; volume 328; periode_volume 1546/1546.

### 10.5 Laporan ARKAS rujukan data

- Bulan dipilih via ?bulan=1..12 (atau Y-m untuk penutupan kas).
- RKAS sumber: revisi non-draft terakhir; bila belum ada, RKAS hasil impor (draft).
- Tahap N = jumlah × v(N)/Σv dari periode_volume; rata dirata bila tak ada.

### 10.6 Hormati kunci bulan BKU (Okt 2026)

- Baris impor dengan tanggal pada **bulan BKU yang sudah ditutup**
  (menu BKU → Tutup BKU bulan ini) dilewati: tercatat di laporan impor
  pada bagian `periode_terkunci` sebagai "BKU bulan Y-m tertutup".
- Sama juga berlaku untuk baris Setor Pajak (laporan `dilewati.pajak`).
- Kunci per tahap (`tutup_bukus`) dan per bulan (`bku_tutup_bulans`) keduanya dihormati.
