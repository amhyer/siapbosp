# Paritas cetak ARKAS 4.3.1 vs SiapBOSP

Dokumen ini membandingkan **keluaran cetak** SiapBOSP dengan berkas PDF asli ARKAS
4.3.1 yang diunduh dari Google Drive sekolah (folder `ARKAS - Cetak`). Yang
dibandingkan: judul, kop/identitas, kepala kolom, urutan baris, letak angka,
tanda tangan, dan kaki halaman. **Bukan** pixel-perfect: kotak, garis, dan ukuran
huruf tidak bisa dipastikan tanpa mencetak dari peramban.

Sumber acuan (19 berkas) — yang relevan untuk empat dokumen yang diminta:

| PDF acuan | Dokumen |
|---|---|
| `rapbs-tahap-output.pdf`, `rapbs-tahap-all-output.pdf` | Kertas Kerja RAPBS per Tahap (11 halaman) |
| `rapbs-tri-output.pdf` | Kertas Kerja RAPBS per Triwulan |
| `rapbs-bulan-output.pdf` | Rincian Kertas Kerja per Bulan (Maret) |
| `rapbs-all-output.pdf` | RAPBS gabungan |
| `rka-unit-output.pdf` | RKA Unit Kerja per Triwulan |
| `rka-unit-tahap-output.pdf` | RKA Unit Kerja per Tahap |
| `rka-unit-22-output.pdf`, `rka-unit-221-output.pdf` | RKA Unit Kerja 2.2 / 2.2.1 |
| `dpa-unit-tahap-output.pdf` | DPA (RKAS) Unit Kerja per Tahap |
| `dpa-unit-22-output.pdf`, `dpa-unit-221-output.pdf` | DPA Unit Kerja 2.2 / 2.2.1 |
| `rpd-k7-output.pdf` | RPD K7 — Rekapitulasi Realisasi Penggunaan Dana |
| `apbs-all-output.pdf` | APBS |
| `bku-all-output.pdf`, `bku-bank-output.pdf`, `bku-output.pdf`, `bku-pajak-output.pdf`, `bhm-output.pdf` | BKU (semua/bank), BKU Pajak, BHM |

## Status per dokumen

| Dokumen ARKAS | Rute SiapBOSP | Status | Selisih yang tersisa |
|---|---|---|---|
| RAPBS per Tahap | `laporan-arkas.rapbs-cetak` | **sama susunannya** | Nomor halaman berasal dari `@page` (`Halaman X dari Y`), bukan penghitung internal ARKAS |
| RAPBS per Triwulan | `…rapbs-cetak?format=triwulan` | **sama susunannya** | Sudah dibaca sampai halaman terakhir (`rapbs-tri-output.pdf` 11 hlm): hlm. 11 memuat baris `Jumlah` + tiga kolom tanda tangan (Kepala Sekolah / Komite Sekolah / Bendahara Sekolah) dan tanggal — kini ikut dicetak. Nama diambil dari master `penandatangans` |
| RAPBS per Bulan | `…rapbs-cetak?format=bulan` | **sama susunannya** | Nama Komite/Kepala/Bendahara diambil dari master `penandatangans`; ARKAS memakai data pegawai internalnya |
| RKA Unit per Triwulan | `laporan-arkas.rka-unit` | **sama susunannya** | Pendapatan triwulan: SiapBOSP hanya menyimpan **tahap**, jadi Tahap 1 → triwulan I dan Tahap 2 → triwulan III (asumsi, ditulis di lembar tidak, hanya di kode/dokumen) |
| RKA Unit per Tahap | `…rka-unit?format=tahap` | **sama susunannya** | — |
| RKA Unit 2.2 | `…rka-unit?varian=rka22` | **sama susunannya** | Tabel `Rincian Anggaran Belanja Langsung Berdasarkan Program dan Kegiatan`: 3 tingkat baris (program `03.` → sub program `03.01.` → rincian), kolom Kode Rekening / Kode Program / Kode Sub Prog / Kode Kegiatan / Uraian / Lokasi Kegiatan / Target Kinerja (Kuantitatif) / Barang & Jasa / Modal / Jumlah (9 = 7 + 8). `lokasi_kegiatan` + `target_kinerja` kini tersimpan per rincian RKAS (opsional; turunan = nama sekolah dan volume + satuan) |
| RKA Unit 2.2.1 | `…rka-unit?varian=rka221` | **sama susunannya** | Pohon per **kode rekening** (`5.` → `5.1.`/`5.2.` → 4 segmen → rekening → rincian), kolom Kode Rekening / Uraian / Volume / Satuan / Harga Satuan / Jumlah `6=(3 x 5)`, blok Program…Kegiatan, tabel `Indikator & Tolok Ukur Kinerja Belanja Langsung`, `Keterangan`/TAPD PD, ttd Kepala Sekolah |
| DPA (RKAS) Unit Kerja per Tahap | `…rka-unit?varian=dpa&format=tahap` | **sama susunannya** | Judul `LEMBAR RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS)` + isi RKA Unit |
| DPA Unit Kerja 2.2 | `…rka-unit?varian=dpa22` | **sama susunannya** | Isi sama dengan RKA 2.2, judul `LEMBAR RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS)` |
| DPA Unit Kerja 2.2.1 | `…rka-unit?varian=dpa221` | **sama susunannya** | Judul `RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS)` + tabel `Rincian Dokumen Pelaksanaan Anggaran Belanja Langsung`. Selisih: ARKAS mencetak `5.2. BELANJA LANGSUNG` dan `5.2.0.` dengan angka **total keseluruhan** (quirk), di sini subtotal modal yang benar; `Jumlah Tahun n` diisi total (ARKAS kosong) |
| RPD K7 | `laporan-arkas.rpd-k7` | **sama susunannya** (baru) | Nama 12 sub program dicetak sebagai baris teks di bawah nomor kolom (ARKAS mencetaknya vertikal); sel matriks bergantung konvensi kode kegiatan `SS.SP.KK` |
| APBS (RKAS) | `laporan-arkas.apbs` | **sama susunannya** | `A. PENERIMAAN` (No. / Kode Penerimaan `4.3.1.xx.` / Jumlah + Total Penerimaan) dan `B. BELANJA` lima tingkat baris (program → sub program → kegiatan → kegiatan+rekening → rincian `001.`), kolom `Jumlah` + 12 kolom `Sumber Dana dan Alokasi Anggaran` (6 kelompok × Belanja Operasi/Modal), ditutup baris `Jumlah` dan 3 tanda tangan. Selisih: kepala tabel disusun 3 baris (No. Urut / Kode Kegiatan / Kode Rekening / Uraian / Jumlah, lalu nama kelompok, lalu Operasi/Modal) sedangkan ARKAS menaruh `Kode Kegiatan`+`Uraian Kegiatan` sebagai judul kolom gabungan; label `BOSP …` dipakai walau `ref_sumber_dana` menulis `BOS …`; nomor rincian berulang per blok rekening; belum ada pemisahan halaman per kelompok dana |
| BKU (Kas Umum) | `pembukuan.bku-cetak` | **sama susunannya** (PR #46) | Baris `Saldo Awal Tahun (SILPA)` adalah tambahan SiapBOSP |
| BKU Pembantu Bank | `pembukuan.bku-bank` | **sama susunannya** (baru) | Lihat catatan di bawah tabel |
| BKU Pajak | `pembukuan.bku-pajak` | **sama susunannya** (PR #48) | Kolom `SSPD` tidak dicetak |
| BHM | `laporan-arkas.bhm` | **sama susunannya** (PR #48) | Kolom `ID Barang` tidak dicetak (SiapBOSP belum punya master barang) |

## Rincian yang sudah disamakan pada RAPBS (PR ini)

Diambil dari teks `rapbs-tahap-output.pdf` / `rapbs-tri-output.pdf` / `rapbs-bulan-output.pdf`:

- Judul: `KERTAS KERJA RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS) PER TAHAP`,
  `… PER TRIWULAN` (ARKAS menulis spasi ganda sebelum `(RKAS)` — dipertahankan),
  `RINCIAN KERTAS KERJA PERBULAN`.
- Kop: `TAHUN ANGGARAN : 2026`, lalu label:nilai `Nama Sekolah`, `Alamat`
  (jalan + `, Kec. …`), `Kabupaten`, `Provinsi` (`Prov. …`), dan baris
  `Tahap`/`Triwulan`/`Bulan`; `NPSN : …` di sisi kanan. Varian per Bulan
  menambah baris `Sumber Dana`.
- Label periode: `I dan II` (tahap), `I,II,III dan IV` (triwulan — tanpa spasi
  setelah koma, sama dengan ARKAS), `Maret 2026` (bulan).
- Kepala tabel belanja: `No. Urut | Kode Rekening | Kode Program | Uraian |
  Rincian Perhitungan (Volume, Satuan, Tarif Harga) | Jumlah | Tahap/Triwulan (1..n)`.
- Baris `Jumlah` menutup tabel; baris `Total Penerimaan` menutup blok A.
- Kaki halaman `Kertas Kerja perTahap|perTriwulan|perBulan - NPSN : …, Nama Sekolah : …`
  dengan `Halaman X dari Y` dari `layouts.print`.
- Varian per Bulan: tanpa kolom tahap, kolom `Jumlah` = rencana bulan itu, dan
  tanda tangan 3 kolom (`Komite Sekolah`, `Kepala Sekolah`, `Bendahara Sekolah`)
  dengan tempat/tanggal di atas kolom kanan.
- Keterangan ringkas `Jumlah Belanja / Jumlah Penerimaan / Selisih` yang dulu ada
  di bawah tabel **dihapus** — ARKAS tidak mencetaknya.

## Yang tidak bisa diverifikasi di sini

- Tidak ada PHP/peramban di sandbox: halaman hanya diuji lewat HTML yang dikirim
  (`tests/Feature/RapbsCetakTest.php`, `RkaUnitCetakTest.php`, `RpdK7CetakTest.php`).
- Berkas PDF acuan tidak disimpan di repo (berisi data keuangan sekolah nyata);
  yang masuk repo hanya susunan/labelnya.
- `@page` margin box (kaki halaman + nomor halaman) hanya didukung Chromium 131+
  dan Safari 18.2+; peramban lain mengabaikannya tanpa merusak halaman.

## BKU Pembantu Bank (`bku-bank-output.pdf` → `pembukuan.bku-bank`)

Ini **bukan** varian BKU Kas Umum: ARKAS punya dokumen tersendiri untuk rekening bank.

| Ciri | ARKAS `bku-bank-output.pdf` | SiapBOSP |
|---|---|---|
| Judul | `B U K U   K A S   P E M B A N T U   B A N K`, penanda kanan `BKU - BANK` | sama |
| Periode | `TAHUN : 2025` (satu tahun penuh, 9 halaman) | sama (`TAHUN : <tahun anggaran>`) |
| Kolom | `TANGGAL · KODE KEGIATAN · KODE REKENING · NO. BUKTI · URAIAN · PENERIMAAN · PENGELUARAN · SALDO`, bernomor 1–8 | sama, nomor 1–8 seperti BKU Kas Umum |
| Isi | **hanya** mutasi rekening bank | `KasService::efek()` kolom bank ≠ 0 |
| Baris pembuka | `01-01-2025  Saldo Bank Bulan Desember 2024  0  0  0` | sama, saldo = posisi bank akhir tahun sebelumnya |
| Baris bulanan | `Bunga Bank` dan `Pajak Bunga` di akhir **setiap** bulan, termasuk yang bernilai 0 | dari `bku_tutup_bulans`; transaksi bunga/admin sudah dibuat saat Tutup BKU, dan baris `0` disintesis untuk bulan yang ditutup tanpa bunga |
| Penutup | baris `Jumlah` + `Menyetujui,/Kepala Sekolah` + `Kec. …, 31 Desember 2025/Bendahara` + kalimat `Pada hari ini <hari> <tgl> Buku Kas Umum Ditutup … Saldo Bank : Rp. …` | sama, tanpa baris `Saldo Kas Tunai` (lembar ini bank saja) |
| Kaki halaman | `BKU Pembantu Bank Tahun 2025 - NPSN : …, Nama Sekolah : …` + `Halaman X dari 9` | sama (nomor halaman dari `layouts.print`) |

Selisih yang **tidak** bisa ditutup:

- `NO. BUKTI` di ARKAS berasal dari nomor bukti ARKAS (`BBU01`, `BNU02`, …). SiapBOSP memakai
  `kas_transactions.no_bukti`, jatuh ke `arkas_bku`, lalu `BKU#<no_bku>` bila keduanya kosong.
- Bila satu transaksi punya beberapa rincian rekening, SiapBOSP mencetak **satu baris** dengan
  kode rekening digabung `, ` — ARKAS memecah per nota.
- `Bunga Bank`/`Pajak Bunga` bernilai > 0: SiapBOSP menormalkan uraian transaksi hasil Tutup BKU
  menjadi `Bunga Bank` / `Pajak Bunga` agar sebanding dengan ARKAS.
- Sama seperti seluruh dokumen lain: belum ada verifikasi visual (sandbox tanpa PHP/peramban).
