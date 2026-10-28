# ARKAS 4.3.1 — cetak RAPBS, RKA Unit, DPA Unit, RPD (perbandingan dengan SiapBOSP)

Bukti: 8 PDF keluaran ARKAS asli yang diekstrak dengan `pypdf` mode layout
(`extraction_mode="layout"`), bukan tebakan. Sekolah contoh: UPT SPF SD NEGERI
UNGGULAN MONGISIDI 1, NPSN 40313912, Kota Makassar.

| Berkas acuan | Halaman | Isi |
|---|---|---|
| `rapbs-tahap-output.pdf` | 11 | Kertas Kerja RKAS **per Tahap**, TA 2026 |
| `rapbs-bulan-output.pdf` | 2 | Rincian Kertas Kerja **per Bulan**, TA 2026 (Maret) |
| `rapbs-tri-output.pdf` | 11 | Varian triwulan (susunan sama dengan per Tahap) |
| `rka-unit-output.pdf` | 1 | Lembar Kertas Kerja **Unit Kerja** (RKA), TA 2026 |
| `rka-unit-tahap-output.pdf` | 1 | Varian tahap; **sampel TA 2024** (beda tahun, jangan dianggap 2026) |
| `dpa-unit-tahap-output.pdf` | 1 | **RKAS Unit Kerja** (DPA), ringkasan + RPA per Tahap |
| `dpa-unit-22-output.pdf` | 6 | RKAS Unit Kerja 2.2 — rekapitulasi belanja langsung per program/kegiatan |
| `rpd-k7-output.pdf` | 1 | **Rekapitulasi Realisasi Penggunaan Dana BOSP** per 8 standar (K7) |

---

## 1. RAPBS per Tahap — **dibuat** (`/laporan-arkas/rapbs-cetak`)

Susunan ARKAS (literal dari PDF):

- Judul: `KERTAS KERJA RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS) PER TAHAP`
- Baris periode: `TAHUN ANGGARAN : 2026`
- Identitas: `NPSN`, `Nama Sekolah`, `Alamat`, `Kabupaten`, `Provinsi`, `Tahap : I dan II`
- Blok `A. PENERIMAAN` → `Sumber Dana :` lalu kolom `No. Kode | Penerimaan | Jumlah`,
  contoh baris `4.3.1.01.  BOSP Reguler  342.900.000`, ditutup `Total Penerimaan`.
- Blok `B. BELANJA` → kolom `No. Urut | Kode Rekening | Kode Program | Uraian |
  Volume | Satuan | Tarif Harga | Jumlah | Tahap 1 | Tahap 2`; baris bertingkat:
  program (`04.`), kegiatan (`04. 06.`), sub-kegiatan (`04. 06. 27.`), lalu rincian
  rekening; ditutup baris `Jumlah`.
- **Tanpa blok tanda tangan** pada varian per Tahap.
- Kaki halaman: `Kertas Kerja perTahap - NPSN : 40313912, Nama Sekolah : …` +
  `Halaman X dari 11` (nomor halaman dipasang `layouts.print`).

Status SiapBOSP: **dibuat** — `LaporanArkasController::rapbsCetak`,
`resources/views/laporan-arkas/rapbs-cetak.blade.php`, rute
`laporan-arkas.rapbs-cetak`, tautan dari layar Rincian RKAS per Tahap.

Selisih yang tersisa (disengaja):

- Pohon program/kegiatan dibentuk dari **kode kegiatan yang tersimpan pada
  rincian RKAS** (`kode_kegiatan`), bukan dari master kode ARKAS; kalau kode
  kegiatan kosong, hanya baris rincian yang tercetak.
- Nama baris ringkasan diambil dari `ArkasKode::peta`; bila tidak ada, kolom
  uraian dibiarkan kosong (tidak dikarang).
- Pembagian per tahap memakai aturan layar Rincian RKAS per Tahap: proporsi
  `periode_volume` bila ada, jika tidak dibagi rata. ARKAS memakai rencana
  per tahap dari basis datanya sendiri.

## 2. RAPBS per Bulan — **dibuat** (`?format=bulan`)

Literal ARKAS: `RINCIAN KERTAS KERJA PERBULAN`, `TAHUN ANGGARAN : 2026`,
identitas + `Bulan : Maret 2026` + `Sumber Dana`, blok penerimaan, blok belanja
dengan kolom `Volume | Satuan | Tarif Harga | Jumlah` untuk **bulan itu**, baris
`Jumlah`, lalu tanda tangan **3 kolom**: `Komite Sekolah | Kepala Sekolah |
Bendahara Sekolah` dengan `Kec. Makasar, 25 Juni 2026` di atas kolom kanan.
Kaki halaman: `Kertas Kerja perBulan - NPSN : … Halaman X dari Y`.

Status SiapBOSP: **dibuat** lewat rute yang sama dengan `?format=bulan&bulan=N`.

Catatan penting soal angka: `RkasDetail::nominalPerBulan()` dipakai apa adanya —
`bulan_volumes` bila ada, jika tidak fallback ke `periode_volume`, jika tidak ada
juga seluruh `banyak` ditaruh di Januari. Artinya dokumen bulanan **hanya
seakurat data rencana bulanan** yang tersimpan; ARKAS memakai rencana bulanan
dari basis datanya sendiri. Tanggal tanda tangan memakai tanggal cetak
(`now()`), sama seperti sampel ARKAS (dokumen Maret, ttd 25 Juni 2026).

## 3. RKA Unit Kerja — **dibuat** (`laporan-arkas.rka-unit`)

Literal: `LEMBAR KERTAS KERJA` / `UNIT KERJA`, `PEMERINTAH <KOTA/…>`,
`TAHUN ANGGARAN 2026`, baris `Urusan Pemerintahan : 1.01 - PENDIDIKAN`,
`Organisasi : <NPSN> - <nama sekolah>`, judul bagian
`Rincian Anggran Pendapatan dan Belanja` (`Anggran` memang salah ketik di ARKAS),
tabel `Kode Rekening | Uraian | Jumlah (Rp)` dengan baris bernomor `1 2 3`,
isi: `JUMLAH PENDAPATAN`, `5 BELANJA`, `5.1 BELANJA OPERASI`, … sampai
`JUMLAH BELANJA`, `DEFISIT`; lalu bagian
`Rencana Pelaksanaan Anggaran / Unit Kerja per Triwulan`
(`No. | Uraian | I | II | III | IV | Jumlah`), dan tanda tangan
`Kec. Makasar, <tgl>` / `Mengetahui,` / `Kepala Sekolah` + nama + `NIP : …`.
Kaki halaman: **tidak ada** pada sampel 1 halaman.

Status SiapBOSP: **dibuat** — rute `laporan-arkas.rka-unit`, varian baku
`format=triwulan` (kolom I–IV) dan `format=tahap`. Uji: `RkaUnitCetakTest`.

Cara angka disusun:

- Bagian 1 (Rincian Anggran) = subtotal per akun dari prefix `rekenings.kode`
  sampai **4 segmen** — batas yang sama dengan ARKAS di lembar ini (ARKAS tidak
  mencetak rincian sampai level barang). Nama akun 5 / 5.1 / 5.1.02 / 5.1.02.0x /
  5.2 / 5.2.02 / 5.2.05 diambil dari sampel acuan (`AKUN_RKA` di controller);
  `rekenings` SiapBOSP hanya menyimpan nama di level rincian. Akun di luar peta
  itu tidak dicetak.
- Bagian 2 (Rencana Pelaksanaan Anggaran) = rencana per periode dari
  `RkasDetail::nominalPerBulan()`: triwulan = 3 bulan, tahap = 6 bulan. Belanja
  operasi = rekening 5.1, modal = 5.2.
- **Asumsi yang perlu diketahui**: ARKAS punya jadwal pencairan pendapatan
  sendiri. SiapBOSP hanya menyimpan tahap pada `allocations`, jadi pendapatan
  per triwulan dihitung Tahap 1 → triwulan I dan Tahap 2 → triwulan III; alokasi
  tanpa tahap dibagi rata. Ini bisa berbeda dari ARKAS bila jadwal pencairan
  sekolah berbeda.
- `JUMLAH PENDAPATAN` / `DEFISIT` dicetak kosong bila belum ada angka, seperti
  sampel ARKAS 2026 (sampel 2024 yang berisi angka tetap menghasilkan baris itu).

## 4. DPA Unit Kerja — **belum dibuat**

Dua varian dalam sampel:

- `dpa-unit-tahap-output.pdf`: judul `LEMBAR RENCANA KEGIATAN DAN ANGGARAN
  SEKOLAH (RKAS)` / `UNIT KERJA`; isi & tabel **sama persis** dengan RKA Unit
  (`Rincian Anggran …`, `Rencana Pelaksanaan Anggaran / Unit Kerja per Tahap`),
  berbeda pada judul dan tanggal tanda tangan.
  Status: **dibuat** lewat rute yang sama, `?varian=dpa&format=tahap`
  (`?varian=dpa` saja = DPA per triwulan, bentuk yang tidak ada di sampel tapi
  mengikuti sumbu yang sama).
- `dpa-unit-22-output.pdf` (6 halaman): judul sama, isi
  `Rekapitulasi Belanja Langsung Berdasarkan Program dan Kegiatan` dengan kolom
  `Kode Rekening | Kode Program | Kode Sub Program | Kode Kegiatan | Uraian |
  Lokasi Kegiatan | Target Kinerja (Kuantitatif) | Barang & Jasa | Modal |
  Jumlah (9 = 7 + 8) | Tahun 2023` (label tahun di header kolom memang
  `Tahun 2023` walau kop `TAHUN ANGGARAN 2026` — quirk ARKAS). Kaki halaman:
  `RKAS Unit Kerja 2.2 - NPSN : … Halaman X dari 6`.

Status: **dibuat** — `?varian=rka22` (RKA) dan `?varian=dpa22` (DPA), view
`laporan-arkas/rka-unit-22-cetak.blade.php`.

Dua kolom yang dulu menjadi penghalang kini ada di `rkas_details`:
`lokasi_kegiatan` dan `target_kinerja` (migrasi
`2026_10_28_000002_add_lokasi_target_ke_rkas_details.php`), diisi opsional pada
form Kertas Kerja. Bila kosong, saat cetak dipakai nilai turunan seperti sampel
ARKAS: lokasi = nama sekolah, target = `volume satuan` rincian.

Selisih yang masih tersisa:

- Sampel ARKAS 2.2 memakai kolom kode rekening bertahap (`5.1.02` pada baris
  ringkasan); SiapBOSP mencetak kode rekening penuh hanya pada baris rincian.
- Kepala kolom `Tahun 2023` pada `dpa-unit-22-output.pdf` adalah quirk ARKAS
  (tahun tetap); SiapBOSP mencetak tahun anggaran berjalan.
- `2.2.1` (rincian per standar harga) belum dibuat.

## 5. RPD K7 — **dibuat** (rute `laporan-arkas.rpd-k7`)

Literal: `REKAPITULASI REALISASI PENGGUNAAN DANA BOSP`,
`PERIODE TANGGAL : 01 Januari 2023 s/d 31 Desember 2023`, `TAHUN 2023`,
identitas `NPSN / Nama Sekolah / Kecamatan / Kabupaten/Kota / Provinsi`,
lalu matriks **8 standar × 12 kolom sub-program** (Pembiayaan Penerimaan Peserta
Didik Baru, Pengembangan Perpustakaan, Pembiayaan Kegiatan Pembelajaran dan
Ekstrakurikuler, … Pembiayaan Pembayaran Honor) dengan baris
`1 Pengembangan Standar Isi` … `7 Pengembangan dan implementasi sistem
penilaian` + `JUMLAH`; ringkasan saldo 4 baris
(`Saldo periode sebelumnya`, `Total penerimaan…`, `Total penggunaan…`,
`Akhir saldo…`); tanda tangan 2 kolom:
`Menyetujui, Kepala Sekolah` dan `Bendahara / Penanggungjawab Kegiatan`.
Kaki halaman: **tidak ada** pada sampel.

Status: **dibuat** di `LaporanArkasController::rpdK7()` dengan matriks
8 standar x sub-program, ringkasan saldo, dan dua kolom tanda tangan; uji
`RpdK7CetakTest`. Dokumen cetak `pembukuan/rekap-realisasi` yang ada tetap
**bukan** RPD K7: itu rekapitulasi per rekening per semester (konsep SiapBOSP
sendiri, dipertahankan).

---

## Ringkasan status

| Dokumen ARKAS | di SiapBOSP | Catatan |
|---|---|---|
| Kertas Kerja RAPBS per Tahap | **ada** | rute baku, uji `RapbsCetakTest` |
| Kertas Kerja RAPBS per Triwulan | **ada (baru)** | `?format=triwulan`; uji `RapbsCetakTest` |
| Kertas Kerja RAPBS per Bulan | **ada** | `?format=bulan`; uji `RapbsCetakTest` |
| RKA Unit Kerja (triwulan) | **ada (baru)** | `laporan-arkas.rka-unit`; uji `RkaUnitCetakTest` |
| DPA / RKAS Unit Kerja (per Tahap) | **ada (baru)** | `?varian=dpa&format=tahap` |
| RKA Unit Kerja 2.2 | **ada (baru)** | `?varian=rka22`; uji `RkaUnit22CetakTest` |
| DPA Unit Kerja 2.2 (rekap) | **ada (baru)** | `?varian=dpa22`; judul tabel `Rekapitulasi …` |
| RKA/DPA Unit Kerja 2.2.1 | **ada (baru)** | `?varian=rka221` / `?varian=dpa221`; uji `RkaUnit221CetakTest` |
| RPD K7 (8 standar) | **ada** | `laporan-arkas.rpd-k7`; uji `RpdK7CetakTest` |
| APBS / RKAS semua sumber dana | **ada (baru)** | `laporan-arkas.apbs`; uji `ApbsCetakTest` |

Tidak ada satu pun angka di dokumen ini yang diverifikasi lewat peramban: yang
diuji CI adalah HTML yang dikirim (judul, kolom, baris, total, kaki halaman).
Berkas PDF acuan tidak disimpan di repo — isinya data keuangan sekolah nyata;
yang masuk repo hanya susunan/labelnya.

---

## Pembaruan: banding langsung dengan PDF acuan

Banding baris-per-baris dengan 13 PDF ARKAS (RAPBS per Tahap/Triwulan/Bulan,
RKA Unit 2 varian, DPA Unit 3 varian, RPD K7) dicatat terpisah di
[`ARKAS-CETAK-PARITAS.md`](ARKAS-CETAK-PARITAS.md) — termasuk daftar selisih yang
masih tersisa dan sebab dokumen yang belum dibuat.

Perubahan susunan RAPBS yang mengikuti hasil banding itu: kop memakai label ARKAS
(`Nama Sekolah` / `Alamat` / `Kabupaten` / `Provinsi` / `Tahap|Triwulan|Bulan`,
`NPSN` di kanan), kepala tabel memakai blok `Rincian Perhitungan` di atas
Volume/Satuan/Tarif Harga + `Tahap`/`Triwulan` di atas kolom periode, label
periode memakai angka Romawi, dan keterangan tambahan di bawah tabel dihapus
karena ARKAS tidak mencetaknya.
