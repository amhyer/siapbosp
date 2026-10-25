# Masukan untuk SiapBOSP dari ARKAS 4 — Menu, Tampilan, dan Kosakata

**Tanggal:** 26 Oktober 2026
**Lingkup:** inventaris menu + anatomi tampilan ARKAS 4 (v4.2.x–4.3.0), dibandingkan dengan SiapBOSP `main@8eb212c`, lalu diterjemahkan menjadi masukan konkret.
**Sifat dokumen:** masukan (belum ada kode yang diubah). Implementasi menunggu pilihan prioritas.

---

## 0. Ringkasan eksekutif

1. **SiapBOSP sudah unggul secara cakupan.** 47 item menu vs ±25 layar ARKAS 4. Yang kurang **bukan modul**, tapi **kosakata status**, **gerbang pra-pengajuan**, dan **penyatuan arsip**.
2. **Tiga masukan P0:** (a) peta kosakata status RKAS agar sama dengan istilah Dinas, (b) checklist pemblokir sebelum "Ajukan", (c) satu halaman Arsip dengan filter *jenis dokumen × sumber dana × tahun* + mode *Akumulasi* vs *per Sumber Dana*.
3. **Tiga masukan P1 tampilan:** kartu per sumber dana sebagai halaman depan Penganggaran/Penatausahaan, strip tab Jan–Des, dan dropdown **Format + Periode** pada cetak BKU.
4. **Yang sudah setara dan tidak perlu dikerjakan ulang:** watermark **DRAFT** (`pembukuan/bku-cetak.blade.php`), Aktivasi Kertas Kerja (`RkasController`), Grafik Proporsi, badge "wajib lapor" (`pengeluaran/belanja.blade.php`), Tutup BKU, Buku Pembantu/SPTJM semester, Riwayat Import/Ekspor.
5. **Jangan tiru 4 hal dari ARKAS 4:** logout otomatis saat jendela ditutup, jendela data 3 tahun, `Tutup BKU` yang **wajib internet**, dan harga satuan yang dikunci server Dinas — SiapBOSP justru menang di situ.

> **Batas bukti:** artikel portal resmi berisi teks + tangkapan layar. **Tangkapan layar tidak dapat saya buka**, jadi detail tata letak di bawah **direkonstruksi dari daftar langkah beranotasi** (posisi tombol, urutan field), bukan dari gambar. Bagian yang murni dugaan ditandai *(dugaan)*.

---

## 1. Metode & tingkat keyakinan sumber

| Tingkat | Sumber | Dipakai untuk |
|---|---|---|
| **A — Resmi** | Portal Pusat Informasi Kemendikdasmen (`pusatinformasi.kemendikdasmen.go.id`), artikel ID | Nama menu, tombol, status, alur, format cetak |
| **A — Regulasi** | Persesjen 13/2022 (daftar jenis dokumen) via salinan disdikbud | Daftar dokumen wajib (BKU, Buku Pembantu, SPTJM, dst.) |
| **B — Sekunder** | blog operator/guru (`websiteedukasi`, `e-ujian`, `informasiguru`, `opssekolah`, `fnanofficial`, `kiakrikil`) | Hanya konfirmasi silang; **tidak** dipakai untuk klaim angka |
| **C — Tidak terverifikasi** | klaim "28 format RKAS", salinan PDF panduan v4.1.9/4.2.2 untuk fliphtml5/slideshare | Tidak dipakai |
| **Blokir** | `arkas.kemendikdasmen.go.id/download/arkas4` | Tertahan WAF SafeLine → changelog hanya dari cuplikan mesin pencari |

**Konsekuensi:** angka "28 format" **tidak** saya pakai. Yang resmi menyebut **14 format RKAS**, dan daftar dokumen Persesjen 13/2022 memberi 9–10 jenis dokumen cetak.

---

## 2. Inventaris menu ARKAS 4

Navigasi global hanya **5 elemen** (sangat sedikit dibanding 7 grup SiapBOSP):

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ ARKAS 4        Penganggaran   Penatausahaan   Arsip      ⟳ Perbarui Aplikasi │
│                                                     ● (merah = ada update)   │
│                                                      Profil ▾  →  Keluar     │
└──────────────────────────────────────────────────────────────────────────────┘
```

| Area | Isi | Catatan tampilan |
|---|---|---|
| **Penganggaran** | Daftar **kartu per sumber dana** (BOSP Reguler, Kinerja, SiLPA Kinerja, BOSP Daerah, Afirmasi/Sumber Dana Tambahan, Lainnya) | Riwayat **3 tahun**; tiap kartu punya status sendiri + 4 ikon aksi |
| **Penatausahaan** | Daftar sumber dana → status BKU, **Aktivasi BKU**, 12 tombol bulan | Butuh KK **Disahkan** + dana salur + tahun lalu tertutup |
| **Arsip** | Filter **jenis dokumen × sumber dana × tahun** | **Hanya** dokumen sah; dua mode: *Akumulasi* / *per Sumber Dana* |
| **Perbarui Aplikasi** | Indikator versi | Titik merah bila versi lawas |
| **Profil ▾** | Data sekolah, Penjab, Keluar | Tahun anggaran ikut di sini *(dugaan)* |

### 2.1 Status per kartu sumber dana (kosakata resmi)

```
Belum Aktif → Draf → [terkirim] Menunggu Review Dinas → Disahkan
                 ↖ Perlu Revisi ↙        ↖ Perlu Diperbaiki ↗
                 Perlu Diajukan Ulang
```

| Status | Arti operasional |
|---|---|
| Belum Aktif | kartu ada, KK belum diaktivasi |
| Draf | masih disusun sekolah |
| Perlu Revisi | dikembalikan Dinas |
| Perlu Diajukan Ulang | sudah diperbaiki, belum dikirim lagi |
| Menunggu Review Dinas | terkirim |
| Disahkan | boleh lanjut ke BKU |
| Perlu Diperbaiki | catatan Dinas pada dokumen/anggaran |

Aksi per kartu: **Cek Status Terbaru**, **Kirim Pengajuan**, **Revisi Kertas Kerja**, **Perbarui Data Kegiatan**, **Pergeseran**, **Perubahan** (draf bisa dibatalkan lewat `(x)`).

### 2.2 Halaman Kertas Kerja (paling padat informasinya)

```
┌───────────────────────────────────────────────────────────────────────────┐
│ ← Kertas Kerja   BOSP Reguler 2026        [Panduan]        [Review] [⋯]   │
├───────────────────────────────────────────────────────────────────────────┤
│ Pagu Rp 1.234.000.000   Sudah Dianggarkan Rp 1.180.000.000                │
│                         Belum Dianggarkan Rp 54.000.000  ← merah, harus 0 │
├───────────────────────────────────────────────────────────────────────────┤
│ Jan │ Feb │ Mar │ Apr │ Mei │ Jun │ Jul │ Agu │ Sep │ Okt │ Nov │ Des     │
├───────────────────────────────────────────────────────────────────────────┤
│ 🔎 Cari kegiatan…                       [+ Tambah Kegiatan] [Perbarui]    │
│ ▸ Komponen  Kode  Uraian                 Vol  Sat   Harga   Jumlah  Aksi  │
│   ▸ Kegiatan …                                     …              [⋯]      │
│       rincian objek belanja …                                            │
└───────────────────────────────────────────────────────────────────────────┘
```

Detail perilaku yang penting ditiru:
- **`Belum Dianggarkan` harus Rp 0** sebelum "Ajukan Pengesahan"; kalau tidak, tombol pengajuan menolak.
- **Uraian maksimal 500 karakter**.
- **Harga satuan dibatasi** harga dari Dinas — input di atas batas ditolak.
- Tiap rincian punya **bulan** (Jan–Des); `+ Tambah Bulan` memperbanyak baris bulan.
- Aksi `(⋯)` → **Edit Detail Anggaran**, **Sisip Uraian**, **Hapus Semua**, **Batal**; ada `+ Masukkan Ke Anggaran`.
- **Autosave** hanya ke perangkat, bukan ke server.
- Kolom **Panduan** (ikon Juknis) di tiap baris.

### 2.3 Review

- Dikelompokkan **per periode salur** (2 tahap), komponen bisa **dilipat**.
- Tiap komponen punya **warna** konsisten.
- **Grafik Proporsi** membandingkan persentase komponen dengan **Juknis tahun berjalan** (bukan tahun lalu).

### 2.4 Penatausahaan → BKU

```
┌───────────────────────────────────────────────────────────────────────────┐
│ BOSP Reguler 2026 · BKU      Dana Tersedia Rp …   [Tarik Tunai][Setor Tunai]│
│ [Jan][Feb][Mar][Apr][Mei][Jun][Jul][Agu][Sep][Okt][Nov][Des]              │
│ [+ Tambah Pembelanjaan]  [🔎 Cari]  [🖨 Cetak]  [🔒 Tutup BKU]  [🗑 Hapus]│
├───────────────────────────────────────────────────────────────────────────┤
│ Tgl │ Uraian │ Penerimaan │ Pengeluaran │ Saldo │ Pajak                  │
│ ▓▓▓ baris baru = kilat kuning sesaat                                      │
│                                     ⚠ Pajak Wajib Lapor (merah)           │
└───────────────────────────────────────────────────────────────────────────┘
```

- Jan–Jun ada **progress bar** realisasi.
- **Hapus BKU** tidak langsung hapus → **Pengajuan hapus BKU** (jejak audit).
- **Tutup BKU** wajib internet; ada syarat Bunga Bank & Pajak Bunga ≤ bunga.
- Status **Belum Lapor Pajak** merah → hitam setelah isi **Tanggal Lapor + NTPN + Kirim Bukti Lapor**; jatuh tempo **tanggal 20 bulan berikutnya**.
- `Perubahan RKAS` **diblokir** bila ada bulan yang BKU-nya belum tutup.

### 2.5 Wizard belanja 3 langkah

```
(1) Detail Transaksi → (2) Kegiatan & Rincian → (3) Perhitungan Pajak
```
- Langkah 1: centang **SIPLah**, **tanggal transaksi = bulan pelunasan** (bukan tanggal nota), tunai/non-tunai, centang **usaha perseorangan**, nama/alamat/telepon toko, **NPWP 15 digit / NIK 16 digit**, centang **tanpa NPWP**.
- Jalur SIPLah menarik invoice/marketplace otomatis, lalu **Pajak Wajib Lapor langsung nol**.
- Jalur manual: isi pajak di langkah 3, ada centang "belanja di SIPLah".

### 2.6 Cetak & format

- Ada **dropdown Format** dan **dropdown Periode**; periode hanya terisi bila dokumen ada di **BKU Tahunan/Arsip**.
- **Watermark DRAFT** pada bulan yang belum ditutup.
- Sumber dana non-reguler **tidak punya** format "Tahapan" → harus pakai **BKU Tahunan/Arsip**.
- Format rujukan: RKAS (**14 format** resmi), BKU Bulanan/Tahunan/Tahapan, Rekapitulasi Realisasi (Semester), Buku Pembantu Kas/Bank/Pajak/Rincian Objek Belanja, Laporan Realisasi Penerimaan & Belanja (Semester), SPTJM, Rekapitulasi BMD.

---

## 3. Perbandingan ARKAS 4 vs SiapBOSP (berbasis kode)

| # | Aspek | ARKAS 4 | SiapBOSP sekarang (bukti) | Selisih |
|---|---|---|---|---|
| 1 | Navigasi | 5 elemen top bar | **7 grup / 47 item** (`layouts/app.blade.php`) | Terlalu dalam; butuh "pintu masuk" kartu |
| 2 | Halaman depan Penganggaran | kartu per **sumber dana** + status | daftar KK (`rkas.index`) + `datasekolah.sumber-dana` terpisah | **Belum ada kartu status** |
| 3 | Status KK | 7 status Dinas | 7 status internal (`RkasHeader::LABEL_STATUS`: draft, diajukan, disetujui, dikirim_dinas, revisi, disahkan, dikunci) | **Kosakata beda**, ada 2 tahap internal (Kepsek) |
| 4 | Gerbang "Ajukan" | Belum Dianggarkan = Rp 0, Uraian ≤500, harga ≤ batas Dinas | "Belum Dianggarkan" **ditampilkan**; pembatas 500 char & batas harga belum ada | **Belum memblokir** |
| 5 | Tab bulan KK | 12 tab | tab **Per Tahapan / Per Tahun** + select bulan | beda model, bukan kekurangan |
| 6 | Review | per periode salur, lipat, warna | `penganggaran.review` + Grafik Proporsi ✔ | setara |
| 7 | BKU | 12 tab bulan, aksi inline, kilat kuning | `<input type="month">`, aksi belanja di menu lain | **Aksi terpencar** |
| 8 | Tarik/Setor Tunai | tombol di lembar BKU | menu terpisah (`ambil.index`, `mutasi.index`) | setara fungsi, beda letak |
| 9 | Badge pajak | merah di BKU + pengingat tgl 20 | badge "wajib lapor" **hanya di daftar belanja** | **Belum di BKU/dashboard** |
| 10 | Hapus BKU | via pengajuan | 🔒 Tutup BKU ada; alur hapus belum berjejak | risiko audit |
| 11 | Cetak BKU | dropdown Format + Periode | **satu format** (`bku-cetak.blade.php`) | **kurang 3 format** |
| 12 | Watermark DRAFT | semua cetak bulan berjalan | **ada**, tapi hanya BKU | perlu diperluas |
| 13 | Arsip | 1 halaman, 3 filter, 2 mode | 5 halaman terpisah; filter `tahun` saja (`rkas`, `pajak`), `bku` tanpa filter | **perlu disatukan** |
| 14 | Wizard belanja | 3 langkah berlabel | 1 form (`belanja-form.blade.php`) + rute `hitung-pajak` terpisah | **tanpa stepper** |
| 15 | Sumber dana | 6–7 kartu, PAUD/Kesetaraan dikurangi | `RkasHeader::SUMBER_DANA_ARKAS` 7 nilai ✔ | setara |
| 16 | Riwayat | 3 tahun | semua tahun ✔ | **SiapBOSP lebih baik** |
| 17 | Tutup BKU | wajib online | offline ✔ | **SiapBOSP lebih baik** |

---

## 4. Masukan konkret

### P0-1 — Peta kosakata status (pekerjaan: kecil, dampak: besar) · ✅ sudah diimplementasikan

**Masalah.** Operator mengetik status ke Dinas dengan istilah ARKAS; di SiapBOSP ia melihat "Diajukan ke Kepsek" dan "Disetujui Kepsek" yang tidak ada padanannya.

**Usulan.** Jangan hapus alur internal (Kepsek), tapi tambah label ARKAS sebagai chip kedua:

| SiapBOSP `status` | Chip utama (tetap) | Chip ARKAS (baru) |
|---|---|---|
| `draft` | Draf | Draf |
| `diajukan` | Diajukan ke Kepsek | Draf (belum ke Dinas) |
| `disetujui` | Disetujui Kepsek | Siap Diajukan |
| `dikirim_dinas` | Menunggu Tinjauan Dinas | Menunggu Review Dinas |
| `revisi` | Perlu Revisi (Dinas) | Perlu Revisi |
| `disahkan` | Disahkan Dinas | Disahkan |
| `dikunci` | Dikunci | Diarsipkan |

**Yang sudah jadi:** `RkasHeader::LABEL_STATUS_ARKAS` (+ `labelStatusArkas()`, `nadaStatusArkas()`, `catatanStatusArkas()`, `statusArkasBerbeda()`) dan partial `resources/views/rkas/partials/status-arkas.blade.php` yang dipakai di `rkas/index` dan `rkas/show`. Chip internal tetap ditampilkan, chip ARKAS muncul di sampingnya. Tanpa migrasi DB.

### P0-2 — Gerbang pra-pengajuan (blocking checklist) · ✅ sudah diimplementasikan

**Masalah.** Sekarang "Ajukan" bisa ditekan walau ada sisa belum dianggarkan; ARKAS menolak.

**Yang sudah jadi** (`App\Services\PraAjukanRkasService::periksa()`):

| Pemeriksaan | Level | Efek |
|---|---|---|
| `Belum Dianggarkan` ≠ Rp 0 (kurang **atau** lebih dari pagu) | ⛔ blokir | pengajuan ditolak |
| Belum ada rincian sama sekali | ⛔ blokir | pengajuan ditolak |
| Rincian tanpa volume/harga/jumlah (> 0) | ⛔ blokir | pengajuan ditolak |
| Harga satuan > batas SSH/SBU Dinas | ⚠️ peringatan | tetap boleh, dicatat |
| Temuan proporsi Juknis (buku/sarpras/honor/tahap) | ⚠️ peringatan | blokirnya tetap di "Kirim ke Dinas" |

Tombol **Ajukan ke Kepsek** otomatis nonaktif (`@disabled`) selama ada blokir. Panel *Kelayakan Pengajuan* menampilkan tiap temuan beserta tautan langsung ke baris rincian (`#baris-{id}`), ada tombol **Periksa Ulang**, dan endpoint `GET /rkas/{id}/pra-ajukan` mengembalikan hasil yang sama dalam JSON. Aturan penolakan juga ditegakkan di sisi server pada `RkasController::aksi()` — bukan hanya di tampilan.

**Perbaikan ikutan (penting):** batas uraian ARKAS 4 adalah **500 karakter** dan form SiapBOSP sudah memakainya (`maxlength=500`, `max:500`), tetapi kolom `rkas_details.uraian` masih **VARCHAR(255)** — isian 256–500 karakter lolos validasi lalu terpotong (MySQL longgar) atau gagal simpan (PostgreSQL). Migrasi `2026_10_26_000001_widen_rkas_details_uraian.php` melebarkannya ke 500. Karena setelah itu uraian > 500 tidak mungkin tersimpan, pemeriksaan uraian tidak dijadikan item gerbang.

### P0-3 — Satu halaman Arsip

**Masalah.** 5 halaman arsip, dan `arsip/bku` bahkan tanpa filter.

**Usulan.** Gabung jadi `/arsip` dengan 3 dropdown + 1 toggle:

```
Jenis dokumen: [RKAS disahkan ▾]   Sumber dana: [Semua ▾]   Tahun: [2026 ▾]
Tampilan: (•) Akumulasi   ( ) per Sumber Dana        [🖨 Cetak daftar]
```

Bobot pekerjaan: sedang. Rute lama (`arsip.rkas`, `arsip.bku`, `arsip.pajak`) tetap hidup sebagai filter-preset agar tautan lama tidak mati.

### P1-4 — Kartu per sumber dana sebagai halaman depan

Ganti `rkas.index` dari tabel menjadi grid kartu (contoh visual: `docs/mockup-paritas-arkas4.html`). Tiap kartu: nama sumber dana, tahun, pagu, terpakai, **status**, dan 4 ikon: 👁 lihat · 🖨 cetak · 🗑 hapus (**nonaktif bila `disahkan`/`dikunci`**) · ⚙ aksi (Ajukan/Revisi/Pergeseran/Perubahan). Riwayat tahun di sisi kanan (dropdown tahun).

### P1-5 — Strip tab Jan–Des + aksi inline di BKU

- Ganti `<input type="month">` dengan **12 tab** (pertahankan input bulan di kanan sebagai lompat cepat).
- Tambah `+ Tambah Pembelanjaan` **di dalam** lembar BKU (dewasa ini harus pindah menu).
- Tambah **Tarik Tunai / Setor Tunai** sebagai tombol di lembar (rute `ambil`/`mutasi` sudah ada).
- **Kilat kuning** pada baris yang baru disimpan (`?baru={id}` + CSS animation).
- Badge **Pajak Wajib Lapor** merah pada baris BKU + kartu pengingat di dashboard: *"Pajak bulan lalu belum dilaporkan — jatuh tempo tgl 20"*.

### P1-6 — Dropdown Format + Periode pada cetak BKU

Tambahkan `?format=` pada `pembukuan.bkuCetak`:

| Format | Isi | Status |
|---|---|---|
| Bulanan | bulan terpilih | **sudah ada** |
| Tahunan | Jan–Des satu tahun | baru |
| Tahapan | per tahap salur | baru |
| Akumulasi | seluruh transaksi s/d bulan terpilih | baru |
| Rekapitulasi Realisasi (Semester) | `pembukuan.rekapRealisasi` | sudah ada (rute lain) |
| Buku Pembantu Rincian Objek Belanja | `pembukuan.bukuPembantu` | sudah ada (rute lain) |
| SPTJM | `pembukuan.sptjm` | sudah ada (rute lain) |

Sekaligus: **watermark DRAFT** dipindah jadi komponen bersama (`partials.watermark-draft`) yang dipakai **semua** cetak, bukan hanya BKU.

### P1-7 — Wizard belanja berlabel 3 langkah

Susun ulang `belanja-form.blade.php` jadi stepper: **Detail Transaksi → Kegiatan & Rincian → Perhitungan Pajak**, dengan urutan field mengikuti ARKAS (SIPLah dulu, lalu tanggal = bulan pelunasan, NPWP 15/NIK 16, centang tanpa-NPWP, jenis usaha perseorangan). Rute `hitung-pajak` menjadi langkah 3 (bukan halaman lepas). Ini perubahan murni tampilan + validasi; skema DB tetap.

### P2 — Kecil tapi murah

| Usulan | Alasan |
|---|---|
| Badge "versi ARKAS terbaru" di topbar (pakai `pengaturan.versi` yang sudah ada) | meniru "Perbarui Aplikasi" |
| Tombol tahun anggaran di topbar | ARKAS taruh di Profil; SiapBOSP punya `datasekolah.tahun` yang terkubur |
| Footer cetak: *"Dicetak dari SiapBOSP — bukan dokumen resmi ARKAS"* | mencegah salah pakai di Dinas |
| Kolom **Panduan** (tautan Juknis) per baris rincian | dikerjakan sekali di partial |


---

## 4a. Gambar tampilan usulan

Berkas PNG di `docs/mockup/` (digambar manual dengan Pillow, teks & angka identik dengan dokumen ini — **bukan** hasil AI generator). Sumber: `docs/mockup/render_mockup.py`.

| Gambar | Menunjukkan | Terkait |
|---|---|---|
| ![Top bar & kartu sumber dana](mockup/01-penganggaran-kartu.png) | Top bar + **kartu per sumber dana** dengan chip status Dinas, ikon hapus nonaktif saat disahkan | P0-1, P1-4 |
| ![Kertas kerja & gerbang](mockup/02-kertas-kerja-gerbang.png) | **Gerbang pra-pengajuan**: tombol *Ajukan* nonaktif, 3 pelanggaran disorot, baris baru kilat kuning | P0-2 |
| ![Lembar BKU](mockup/03-bku.png) | **BKU**: tab Jan–Des, tarik/setor tunai di lembar, badge **wajib lapor** | P1-5 |
| ![Wizard belanja](mockup/04-wizard-belanja.png) | **Wizard 3 langkah** mengikuti urutan ARKAS | P1-7 |
| ![Arsip & cetak](mockup/05-arsip-cetak.png) | **Arsip terpadu** (3 filter + 2 mode) & pratinjau cetak ber-watermark **DRAFT** | P0-3, P1-6 |

Lembar gabungan semua panel: [`mockup/00-semua-tampilan.png`](mockup/00-semua-tampilan.png). Cara regenerasi ada di [`mockup/README.md`](mockup/README.md).

---

## 5. Yang sebaiknya *tidak* ditiru

| Perilaku ARKAS 4 | Alasan tidak ditiru |
|---|---|
| Logout otomatis saat jendela ditutup | menyulitkan operator yang membuka SiapBOSP di tab lain |
| Riwayat dibatasi 3 tahun | SiapBOSP arsip lokal = nilai jual; tetap simpan semua |
| `Tutup BKU` wajib internet | SiapBOSP bisa tutup offline — pertahankan, cukup beri catatan |
| Harga satuan dikunci server Dinas | SiapBOSP harus jalan tanpa server; cukup **peringatan** batas |
| Hapus BKU lewat pengajuan ke pusat | untuk SiapBOSP cukup **soft-delete + alasan + jejak** di `Log Sistem` |

---

## 6. Urutan pengerjaan yang disarankan

| Sprint | Isi | Risiko | Alasan urutan |
|---|---|---|---|
| **S1** | ✅ P0-1 kosakata status + P0-2 gerbang ajukan (selesai) | rendah | mencegah salah kirim ke Dinas |
| **S2** | P0-3 arsip terpadu | sedang (rute baru) | memperbaiki temuan operator paling sering |
| **S3** | P1-5 tab BKU + badge pajak + P1-6 format cetak | sedang | nilai tampilan tertinggi |
| **S4** | P1-4 kartu sumber dana | sedang | mengubah halaman depan `rkas.index` |
| **S5** | P1-7 wizard belanja | sedang–tinggi (form sentral) | butuh test regresi belanja penuh |
| **S6** | P2 sekumpulan kecil | rendah | bisa digabung ke sprint mana pun |

Tiap sprint: `php artisan test`, uji cetak (A4), dan cek `Belum Dianggarkan` pada data nyata.

---

## 7. Sumber

**Resmi (A)** — portal Pusat Informasi Kemendikdasmen, artikel ID:
- Fitur Utama ARKAS 4 — `/hc/id/articles/52435404891033-Fitur-Utama-ARKAS-4`
- Mengisi Kertas Kerja ARKAS 4 — `/hc/id/articles/52435547420953-Mengisi-Kertas-Kerja-ARKAS-4`
- Mencatat Pembelanjaan … Detail Transaksi — `/hc/id/articles/52436500639001`
- Cara Cetak BKU ARKAS 4 — `/hc/id/articles/52436938890777-Cara-Cetak-BKU-ARKAS-4`
- Menyelesaikan / Tutup BKU ARKAS 4 — `/hc/id/articles/52436300614297`
- Lapor Pajak ARKAS 4 — `/hc/id/articles/52436721740697`
- Perubahan RKAS — `/hc/id/articles/52436006354585`
- Pemeliharaan Sistem MARKAS/ARKAS — `/hc/id/articles/52435338737945`

**Regulasi (A)** — Persesjen Kemendikbudristek 13/2022 (daftar jenis dokumen laporan), salinan resmi disdikbud.

**Sekunder (B, hanya konfirmasi)** — `websiteedukasi.com`, `e-ujian.id`, `informasiguru.com`, `opssekolah.com`, `fnanofficial.id`, `kiakrikil.com`.

**Tidak dipakai** — klaim "28 format RKAS" (hanya di sumber B) dan salinan panduan v4.1.9/4.2.2 di fliphtml5/slideshare.

**Catatan akses** — `arkas.kemendikdasmen.go.id/download/arkas4` tertahan WAF; changelog 4.2.16–4.3.0 hanya terbaca lewat cuplikan mesin pencari (Afirmasi/Sumber Dana Tambahan, komponen per Juknis 2026, perbaikan cetak Afirmasi/SDT, 6 laporan BKU Tahunan di area Penganggaran).

---

*Dokumen ini masukan, bukan komitmen implementasi. Mockup interaktif: `docs/mockup-paritas-arkas4.html` · gambar PNG: `docs/mockup/`.*
