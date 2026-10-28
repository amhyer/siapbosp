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
| RAPBS per Triwulan | `…rapbs-cetak?format=triwulan` | **sama susunannya** (baru) | Sampel terpotong sebelum blok tanda tangan, jadi lembar ini **tidak** mencetak ttd. Kalau ARKAS ternyata menandatangani, tinggal ikutkan varian 3 kolom |
| RAPBS per Bulan | `…rapbs-cetak?format=bulan` | **sama susunannya** | Nama Komite/Kepala/Bendahara diambil dari master `penandatangans`; ARKAS memakai data pegawai internalnya |
| RKA Unit per Triwulan | `laporan-arkas.rka-unit` | **sama susunannya** | Pendapatan triwulan: SiapBOSP hanya menyimpan **tahap**, jadi Tahap 1 → triwulan I dan Tahap 2 → triwulan III (asumsi, ditulis di lembar tidak, hanya di kode/dokumen) |
| RKA Unit per Tahap | `…rka-unit?format=tahap` | **sama susunannya** | — |
| RKA Unit 2.2 / 2.2.1 | — | **belum** | Butuh kolom `Lokasi Kegiatan` + `Target Kinerja (Kuantitatif)`; tidak ada di `rkas_details` |
| DPA (RKAS) Unit Kerja per Tahap | `…rka-unit?varian=dpa&format=tahap` | **sama susunannya** | Judul `LEMBAR RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS)` + isi RKA Unit |
| DPA Unit Kerja 2.2 / 2.2.1 | — | **belum** | Sebab sama dengan RKA Unit 2.2 (lokasi + target kinerja) |
| RPD K7 | `laporan-arkas.rpd-k7` | **sama susunannya** (baru) | Nama 12 sub program dicetak sebagai baris teks di bawah nomor kolom (ARKAS mencetaknya vertikal); sel matriks bergantung konvensi kode kegiatan `SS.SP.KK` |
| APBS | — | **belum diperiksa** | Belum dibaca pada sesi ini |
| BKU / BKU Bank / BKU Pajak / BHM | `pembukuan.bku-cetak`, `…bku-pajak`, `laporan-arkas.bhm` | **sama susunannya** (PR #46–#48) | Kolom `SSPD` (BKU Pajak) dan `ID Barang` (BHM) tidak dicetak; baris `Saldo Awal Tahun (SILPA)` adalah tambahan SiapBOSP |

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
