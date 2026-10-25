# Penyelarasan SiapBOSP terhadap ARKAS 4 (`v4.2.19` / `v4.3.0`) & Permendikdasmen No. 8 Tahun 2026

> Dokumen acuan resmi dalam repositori untuk memastikan **SiapBOSP** selalu selaras 100% dengan:
> 1. **Aplikasi ARKAS 4 terbaru (`v4.2.18`–`v4.2.19`, Panduan Pengguna `v4.3.0` September 2026)** dari Pusdatin Kemendikdasmen; dan
> 2. **Permendikdasmen Nomor 8 Tahun 2026** tentang Petunjuk Teknis Pengelolaan Dana BOSP (berlaku sejak 6 Februari 2026, menggantikan Permendikdasmen No. 8 Tahun 2025).

---

## 1. Peran Strategis SiapBOSP Pendamping ARKAS 4

Satuan pendidikan umumnya mengalami kesulitan apabila langsung menyusun anggaran dan menatausahakan BKU di ARKAS Desktop tanpa simulasi terlebih dahulu. **SiapBOSP** menyediakan alur kerja hulu-ke-hilir yang aman:

1. **Hulu Pra-ARKAS (`/perencanaan` & `/perencanaan/ssh`)** — Identifikasi akar masalah **Rapor Pendidikan (PBD)** $\rightarrow$ turunkan ke **RKM 4 Tahun** $\rightarrow$ rinci dalam **RKT Tahunan** (ber-anggaran & non-anggaran) dengan referensi **Standar Satuan Harga (SSH/SBU)** $\rightarrow$ dorong 1 klik ke **Kertas Kerja RKAS**.
2. **Penganggaran / Kertas Kerja (`/rkas`)** — Simulasi **6 Sumber Dana ARKAS 4**, alokasi volume **12 Bulan (`Jan–Des`)**, **Sisip Uraian**, **Salin Kertas Kerja Tahun Lalu (`T-1`)**, aturan **Pergeseran vs Perubahan**, serta **3 Mode Review Kertas Kerja** (*Grafik Proporsi Juknis 2026*, *Per Tahapan*, *Per Tahun*).
3. **Salin Cepat ke ARKAS 4 Desktop (`/rkas/{id}/salin-arkas`)** — Tampilan berdampingan sesuai urutan form *+ Tambah Kegiatan* di ARKAS 4 lengkap dengan tombol salin per kolom dan checklist progres baris yang sudah diinput.
4. **Penatausahaan BKU, Pajak, Rekonsiliasi & Pelaporan (`/pembukuan/bku`, `/pengeluaran/belanja`, `/rekonsiliasi`, `/laporan-arkas/*`)** — BKU bulanan berurutan dari bulan penerimaan dana pertama, kalkulator pajak ARKAS 4 (Non-SIPLah & SIPLah), Bunga/Pajak Bank saat Tutup BKU, watermark `"DRAFT"`, **SPTJM**, serta **Tarik Langsung SQLCipher `arkas-data.db` (`TarikArkas`)** untuk rekonsiliasi bolak-balik.

---

## 2. Aturan Proporsi Anggaran Permendikdasmen No. 8 Tahun 2026 (`JuknisBosp2026Service`)

Seluruh persentase pada Tahun Anggaran 2026 dihitung dari **100% total pagu alokasi tahunan** satuan pendidikan:

| Kategori Satuan Pendidikan | Dasar Hukum | Buku / Perpustakaan | Pemeliharaan Sarpras | Pembayaran Honor | Alokasi Tahap 1 (`Jan–Jun`) |
|---|---|---|---|---|---|
| **SD, SMP, SMA, SMK, SLB — Negeri** | **Pasal 42** | **Minimal 10%** | **Maksimal 20%** | **Maksimal 20%** | **Maksimal 50%** |
| **SD, SMP, SMA, SMK, SLB — Swasta** | **Pasal 42** | **Minimal 10%** | **Maksimal 20%** | **Maksimal 40%** | **Maksimal 50%** |
| **PAUD Reguler (Negeri & Swasta)** | **Pasal 38** | **Minimal 5%** | **Maksimal 20%** | **Maksimal 40%** | **Maksimal 50%** |
| **Kesetaraan Reguler (PKBM/SKB)** | **Pasal 48** | **Minimal 10%** | **Maksimal 20%** | **Maksimal 40%** | **Maksimal 50%** |
| **BOSP Kinerja / BOSDA / Afirmasi** | **ARKAS v4.2.19** | Tanpa batas persentase reguler — dievaluasi melalui **Grafik Proporsi Antar Jenis Belanja** | — | — | Fleksibel sesuai juknis khusus |

### Batas Waktu Pelaporan & Pengurangan Salur (Pasal 59)
- **Laporan Realisasi Tahap I**: Paling lambat **31 Juli** tahun berjalan. Keterlambatan dikenai pengurangan penyaluran Tahap II sebesar **2%** (1–31 Agustus), **3%** (1–30 September), **4%** (1–25 Oktober), dan **tidak disalurkan** apabila melewati **25 Oktober**.
- **Laporan Realisasi Tahunan**: Paling lambat **31 Januari** tahun anggaran berikutnya (`T+1`).

---

## 3. Matriks Keselarasan Fitur ARKAS 4 (`v4.3.0`) di SiapBOSP

| Modul ARKAS 4 | Ketentuan Resmi ARKAS 4 (`v4.2.19` / `v4.3.0`) | Implementasi di SiapBOSP |
|---|---|---|
| **Sumber Dana** | BOSP Reguler, BOSP Kinerja, SiLPA BOSP Kinerja, BOSP Daerah, BOSP Afirmasi / Sumber Dana Tambahan, Lainnya | `MasterSeeder`, `RkasHeader::SUMBER_DANA_ARKAS` |
| **Aktivasi & Profil** | Sinkron siswa & biaya satuan, Nama & Email Komite Sekolah, aturan akhir masa jabatan Kepala Sekolah tengah tahun | `/utility/profil` (`ProfilController`, `School`) |
| **Pra-ARKAS & SSH** | PBD Rapor Pendidikan $\rightarrow$ RKM $\rightarrow$ RKT + batas atas Standar Satuan Harga Dinas (`SSH/SBU`) | `/perencanaan`, `/perencanaan/ssh` |
| **Kertas Kerja** | Multi-bulan (`Jan–Des`), *Sisip Uraian* saat status aktif sunting, *Salin Tahun Lalu (`T-1`)*, *Belum Dianggarkan = Rp0* | `RkasController` + `/rkas/{id}/salin-arkas` |
| **Pergeseran vs Perubahan** | *Pergeseran*: hanya dalam kelompok akun belanja yang sama (`5.1.02` / `5.2.02` / `5.2.05`), selisih = Rp0, $\ge$ realisasi BKU. *Perubahan*: lintas akun belanja, $\ge$ realisasi BKU | `JuknisBosp2026Service::validasiRevisiArkas()` |
| **Pembelanjaan & Pajak** | Identitas toko/perseorangan, NPWP/NIK, Ongkir & Admin SIPLah (otomatis pajak marketplace), PPN > Rp2 juta, PPh 21/22/23, SSPD 10%, kuota kumulatif bulanan | `PengeluaranController` + `JuknisBosp2026Service::rekomendasiPajakArkas()` |
| **BKU Bulanan & Cetak** | Penutupan kronologis berurutan sejak bulan dana pertama cair, Bunga & Pajak Bank saat tutup, status *Belum Lapor Pajak*, watermark **DRAFT**, & **SPTJM** | `PembukuanController` (`/pembukuan/bku`, `/pembukuan/bku/cetak`, `/pembukuan/sptjm`) |
| **Rekonsiliasi & Impor** | Pembacaan SQLCipher `arkas-data.db` (`TarikArkas`) & 9 format laporan ARKAS | `ImportArkasController`, `RekonsiliasiController`, `LaporanArkasController` |

## 15. Uji penuh app portabel & perapihannya (21 Okt 2026)

Paket `SiapBOSP-Portable-v12.zip` diuji end-to-end (diekstrak, dipasang via
`launcher-windows/Pasang-SiapBOSP.bat` mode `SIPBOS_*`, server bundel menyala, diklik-2x via HTTP):

- **Fungsi lulus semua**: status pasang awal/akhir, pemasangan (22 migrasi, master
  56 rekening + 5 pajak + 5 tahap, akun bendahara, `agen.conf` otomatis), login web 200,
  **23 halaman inti 200** (termasuk PDF/XLSX LPJ dari data kosong), Node agen v24 tersedia,
  `Sync-Pusat` memberi pesan jelas tanpa identitas, pendamping html 200.
- **Perbaikan GUI hasil audit ini**:
  1. **Tautan 404 di portable disembunyikan** — kartu "Opsi 0 (unduh paket/pemasang)" dan
     tautan agen ZIP di *Impor ARKAS* tampil hanya di server pusat; di laptop portabel diganti
     catatan "Agen sudah terbundel → pakai pintasan Tarik Data ARKAS";
     `UnduhAplikasiController::isPortable()` + redirect ramah (bukan 404) bila dibuka langsung.
  2. **`APP_DEBUG=false`** di template `.env.portable` (pembangun paket) — stack trace tidak
     tampil ke operator di laptop sekolah.
  3. **Kata sandi pemasang tersembunyi** — `Pasang-SiapBOSP.bat` memakai
     `Read-Host -AsSecureString` (PowerShell) dengan cadangan prompt biasa.
- **Paket dibangun ulang**: `php artisan siapbos:paket-portable` → 73.2 MB (10.570 berkas,
  PHP 27 berkas, agen 40 berkas) dan diverifikasi ekstrak → bat-pasang → server → HTTP.
- **Pemasang satu-berkas EXE kini dibangun dari repo** — perintah baru
  `php artisan siapbos:pemasang-exe` (sumber: `app/Console/Commands/PemasangExe.php`):
  - Pola SFX sederhana tanpa dependensi: `csc.exe` bawaan Windows membangun kulit EXE kecil
    (±24 KB, ikon SiapBOSP, program ekstraksi C#), lalu payload ZIP dijahit DI BELAKANG EXE
    ditutup trailer `[int64 panjang][magic "SIAPBOSPACK"]` → `SiapBOSP-Setup-v12.exe` 73.2 MB.
  - Saat diklik 2x: payload diekstrak ke `%TEMP%\SiapBOSP-Setup`, `akun-sekolah.conf` di
    sebelah EXE (unduhan "Unduh Identitas") disuntik otomatis → pasang tanpa menanya NPSN;
    `PASANG-SIAPBOSP.bat` dijalankan; folder payload dibersihkan setelah selesai.
  - **Bug terungkap dari uji EXE** (berlaku juga untuk alat ekstrak mana pun yang membuang
    folder kosong): entri folder kosong ZIP (`bootstrap/cache`, `storage/framework/*`) tidak
    dibuat → artisan gagal boot ("bootstrap/cache must be present and writable"). Diperbaiki
    dua lapis: (a) ekstraktor C# kini membuat folder untuk entri folder; (b) `Pasang-SiapBOSP.bat`
    menjamin folder wajib (`bootstrap\cache`, `storage\framework\{views,cache,sessions}`,
    `storage\logs`) sebelum artisan pertama berjalan.
  - **Uji E2E EXE lulus**: identitas di samping EXE terbaca (SD Uji EXE), instal penuh
    (`.env`, `database.sqlite`, token `agen.conf`), exit 0, folder payload bersih,
    server instalan menyala (HTTP 302 → login). Integritas ZIP diverifikasi utuh
    (12.381 entri) sebelum & sesudah pembuatan EXE.
  - Rutin pembaruan: `siapbos:paket-portable` → `siapbos:pemasang-exe` (EXE selalu memuat
    ZIP terkini).

---

## 16. Tarik Data ARKAS "1 klik" di dalam aplikasi portabel (21 Okt 2026)

- **Kartu ⚡ di *Utility → Profil Sekolah*** — hanya di aplikasi portabel (`TarikArkasService::tersedia()`):
  status database ARKAS di laptop ini (ditemukan + NPSN + path), pilih tahun anggaran, tombol
  **"⬇️ Tarik Data Sekarang"** → selesai; ringkasan jumlah (transaksi kas, RKAS, pajak terima/setor)
  langsung tampil di kartu yang sama. Pintasan Desktop lama tetap tersedia.
- **Alur (tanpa token API & tanpa HTTP ke server sendiri)** — aplikasi menjalankan agen bundel
  `agen/TarikArkas/node.exe tarik.cjs lokal --json --tahun X --keluar <temp>.xlsx --db <path>`;
  agen membaca ARKAS desktop (read-only) dan menulis Excel, lalu aplikasi mengimpornya sendiri
  lewat `ArkasImportService` (aturan impor identik dengan unggahan web — idempoten, tak ada
  yang dobel/terhapus). Tak macet walau port server berubah, dan tetap jalan walau token API
  dirotasi.
- **Pengaman** — NPSN ARKAS ≠ NPSN profil sekolah → tarik ditolak (data tak tertukar); ARKAS
  sedang terbuka → pesan "tutup dulu"; tahun wajib valid; route `POST /utility/tarik-arkas`
  (`tarik-arkas.tarik`, role operator/kepsek); berkas Excel sementara otomatis terhapus.
- **Dua temuan nyata dari uji E2E (instalan portabel + ARKAS nyata 33 MB)**
  1. **`php artisan serve` memberi proses server env KOSONG** — Laravel `ServeCommand` menurunkan
     `$_ENV` ke proses server, dan `variables_order` php bundel tidak memuat "E" sehingga
     `$_ENV` kosong → `getenv()` (APPDATA/USERPROFILE) hilang di dalam request. Diperbaiki:
     (a) php.ini bundel kini `variables_order = "EGPCS"` (PaketPortable `isiPhpIni`);
     (b) `TarikArkasService` menghitung path DB berlapis (APPDATA → USERPROFILE → **turunan
     dari lokasi pemasangan** `…\AppData\Local\…`) dan selalu mengirim `--db` eksplisit ke agen.
  2. **Loop pembaca proses agen sempat spin di EOF** (select tetap menyatakan pipa "readable"
     setelah proses mati) — kini keluar saat `proc_get_status` tak berjalan, lalu buffer dihabiskan.
- **Agen** menambah mode `lokal --keluar X.xlsx` (JSON, tanpa server/token) dan opsi `--db <path>`;
  dibundel ulang via esbuild (cache npx) → dijahit ke `TarikArkas-v1.0.0.zip` **dan**
  `TarikArkas-GUI-v1.0.0.zip` (backend GUI).

---

## 17. Penyeselarasan regulasi 2026 & perbaikan impor (21 Okt 2026)

1. **Agen mengekspor sheet master** — `Ref_Kode` (14.185 baris kode kegiatan resmi),
   `Ref_Rekening`, `Ref_Sumber_Dana`, dan `Anggaran` (pagu per sumber dana). Akibatnya
   `rkas_details.kode_kegiatan` terisi saat impor (file ARKAS maupun tombol 1-klik) → dasar
   klasifikasi komponen pada `JuknisBosp2026Service` & cek proporsi.
2. **Perbaikan impor RKAS: hanya revisi terbaru** — ARKAS menyimpan beberapa revisi RKAS per
   tahun (tiap revisi ber-pagu penuh: 3 revisi × Rp342,9 jt = Rp905,5 jt pada data uji).
   Importer kini memakai **revisi anggaran aktif terbaru per sumber dana**
   (`ArkasImportService::anggaranTerpilih`) dan menghapus baris dari revisi lama pada draf impor
   → pagu & anggaran tak lagi terduplikasi. Pagu (sheet `Anggaran`) memakai logika yang sama.
3. **BOSP Afirmasi** — `bos_types` `afirmasi` (ARKAS 4.2.16+, Permendikdasmen 8/2026 Pasal 46),
   pemetaan `id_ref_sumber_dana` **11 → afirmasi**, **5 → lainnya**, **6 (BOSP PAUD Reguler) →
   reguler**; label & ikon sumber dana (`app/Support/SumberDana.php`) + rekap anggaran per
   sumber dana di halaman RKAS; kode kegiatan Afirmasi (06.33.x / 03.34.x) dikenali.
4. **PPN 12%** — tarif otomatis pada form input belanja mengikuti **tahun transaksi**: 12% untuk
   2026 ke atas (penyesuaian PPN 12% pada ARKAS & SiPLah), 11% untuk tahun sebelumnya
   (nilai pajak lama tersimpan apa adanya, tak dihitung ulang).
5. **"🔍 Cek Ulang Integritas Impor"** (`CekIntegritasService` + `CekIntegritasController`,
   `GET/POST /impor-arkas/cek-integritas`) — membandingkan data ARKAS terbaru dengan pencatatan
   SiapBOSP **per bulan** (jumlah baris & nilai; catatan lokal dipisah) untuk kasus resmi
   "ketidaksesuaian anggaran vs realisasi pasca pergeseran/perubahan". Portabel: tarik langsung
   dari ARKAS; server pusat: unggah file ekspor ARKAS. Tidak mengubah data apa pun.
6. **Paket SPJ sesuai Permendikdasmen No 15/2026** — kini 8 halaman: **Ringkasan Kepatuhan**
   (6 bukti pelaporan Pasal 24 + status), **Dokumen Perencanaan (KAK/RKA)** (Pasal 12) dan
   **Bukti Perbandingan Harga ≥ 2 penyedia** (Pasal 15) yang **dikecualikan otomatis bila
   belanja ≤ Rp1.000.000** (Pasal 12 ayat 4), plus catatan "pembayaran di luar SiPLah dilakukan
   setelah barang diterima" (Pasal 18 ayat 5), pelaporan lewat sistem RKA (Pasal 25–26) dan
   pengawasan komite (Pasal 31–33). Sumber: PDF `Permendikdasmen-no-15-tahun-2026.pdf`
   (peraturan.go.id) — dibaca & diuraikan pada 21 Okt 2026.
7. **Kartu "Berita 2026" di halaman pendamping** (`ARKAS4-Pendamping-Sekolah.html` → salinan
   `public/pendamping-arkas.html`): ringkasan Juknis 8/2026, pengingat pembaruan **ARKAS 4.2.18 /
   MARKAS 2.2.15**, BOSP Afirmasi (4.2.16+), PPN 12%, Permendikdasmen 15/2026, dan kendala
   selisih pasca pergeseran — lengkap dengan tautan artikel resmi.
8. **Komponen master** "Pelaksanaan Penerimaan Peserta Didik Baru" → **"Pelaksanaan Penerimaan
   Murid Baru"** (Permendikdasmen 8/2026) — migrasi `2026_10_21_000004`.
- Pengujian baru: `CekIntegritasTest` (6), `SpjPermendikdasmen15Test` (2), `TarikArkasTest` (6).

---

## 18. Preview cetak (21 Okt 2026)

Tombol cetak di dalam aplikasi **tak lagi membuka tab HTML** — halaman cetak dimuat di iframe
tersembunyi lalu **dialog cetak browser langsung dibuka** (pengguna tetap di halaman asalnya):

- Helper global `window.siapbosCetakPreview(url)` (partial `layouts/_cetak-preview.blade.php`,
  dimuat di **layouts app & print**) + penangkap klik pada elemen `a[data-cetak]`
  (fallback: buka tab baru bila iframe gagal).
- **Audit seluruh halaman cetak** — 10 view `layouts.print` diperiksa; tombol ber-intent cetak
  diubah jadi preview:
  - Halaman BKU: **Cetak BKU**, **Buku Pembantu (Semester)**, **Rekap Realisasi (Semester)**, **SPTJM**
  - Dashboard: **LPJ per Tahap**
  - Kas Tunai/Bank: **Register Kas Tunai**, **Register Kas Bank**
  - Belanja: **Kwitansi**
  - Penutupan Kas: **Cetak Register + Berita Acara**
  - Halaman LPJ per Tahap: **LPJ Lengkap** (tombol di dalam halaman cetak)
- **Pengecualian:** halaman **SPJ** tetap dibuka sebagai halaman biasa karena isiannya
  (78 field *contenteditable*) harus diklik & diisi pengguna — tak bisa disembunyikan di iframe.
- Link `target="_blank"` yang tersisa memang navigasi, bukan cetak: halaman Pending ARKAS 4
  (alat bantu HTML mandiri) dan **Detail Nota: Daftar Kas Umum** (halaman data aplikasi).
- Pengujian: `PreviewCetakTest` (6) — atribut `data-cetak`, helper termuat di kedua layout,
  halaman cetak tetap dapat diakses langsung, dan halaman dokumen tetap berkerangka aplikasi.

### 18.1 Halaman dokumen kini memakai kerangka aplikasi (sidebar ikut ada)

Tiga halaman yang **dibuka dari menu sidebar** sebelumnya tampil sebagai HTML polos tanpa
sidebar (karena memakai `layouts.print`): **LPJ & Tutup Buku per Tahap** (`/laporan/tahap`),
**LPJ per Tahap** (`/laporan/lpj`), dan **SPTJM BOSP** (`/pembukuan/sptjm`).

- Ketiganya kini memakai `layouts.app` → **sidebar, header, dan menu aplikasi tampil**;
  isinya dibungkus `<div class="dokumen">` (gaya kop/tabel/ttd ala dokumen resmi) sehingga
  tetap berformat surat saat dicetak.
- `layouts.app` kini punya **aturan cetak** (`@media print`): sidebar, header, footer, pesan
  flash, dan toolbar disembunyikan; isi halaman tetap tercetak (A4, margin 1,5 cm). Class
  `nonprint` pada area konten dilepas agar konten tidak ikut hilang saat print.
- Halaman dokumen **murni** (Buku Pembantu, Rekap Realisasi, Cetak BKU, Register, Kwitansi,
  Register Penutupan Kas) tetap memakai `layouts.print` — dibuka lewat **preview cetak**
  (iframe), jadi tak perlu sidebar.

---

## 19. Restrukturisasi menu & halaman baru (22 Okt 2026)

Hasil audit struktur menu terhadap keadaan aplikasi: 26 item sudah ada, 6 item perlu penyesuaian
nama/posisi, dan 9 item belum ada — kini semuanya dikerjakan.

### 19.1 Sidebar: Dashboard langsung + 7 grup

**📊 Dashboard** tampil sebagai tautan langsung paling atas (tanpa induk "Beranda"), lalu 7 grup:

| Grup | Isi (semua terarah ke route nyata) |
|---|---|
| **🏫 Data Sekolah** | Profil Sekolah · Penanggung Jawab · **Tahun Anggaran** · **Sumber Dana** · **Status Sinkronisasi ke Pusat** |
| **📝 Penganggaran** | Kertas Kerja (RKAS) · **Review & Proporsi Anggaran** · **Daftar Kegiatan** · Anggaran Bulanan (ARKAS) · PBD → RKM → RKT |
| **📒 Penatausahaan** | Buku Kas Umum · Kas dan Bank · _Penerimaan:_ Pendapatan · Tarik Tunai · Setor Tunai · _Pengeluaran & Pajak:_ Pembelanjaan · Pajak (Setor & Utang) · Buku Pembantu · _Pelaporan:_ Penutupan BKU · SPTJM |
| **🖨️ Laporan** | LPJ & Tutup Buku · LPJ per Tahap · Pajak Triwulan · **Realisasi per Sumber Dana** · Rekonsiliasi + 8 laporan format ARKAS |
| **🗄️ Arsip** (baru) | **RKAS Disahkan** · **BKU Ditutup** · **Dokumen Pajak** · **Riwayat Impor** · **Riwayat Ekspor** |
| **🧩 Data Referensi** | Referensi Master (8 tab: rekening, pajak, program, kegiatan, satuan, uraian, PTK, jenis BOS) · Referensi Harga (SSH/SBU) |
| **⚙️ Pengaturan** | **Koneksi ARKAS** · Impor Database · Pemetaan Data (HPP) · **Backup Data** · **Deteksi Versi ARKAS** · **Log Sistem** · Ganti Password · Hapus Data |

### 19.2 Halaman baru (semua datanya sudah ada di aplikasi)

- **Arsip** (`ArsipController`) — RKAS berstatus disahkan/dikunci (+ unduh PDF/Excel), periode
  BKU yang ditutup (bulan & tahap), pajak beserta status lapor & NTPN, riwayat impor ARKAS, dan
  riwayat ekspor/sinkronisasi (+ `sync_states`).
- **Data Sekolah** (`DataSekolahController`) — *Tahun Anggaran*: pagu, rencana, realisasi, sisa,
  serapan, jumlah transaksi & tahap tertutup per tahun. *Sumber Dana*: pagu, rencana, realisasi,
  dan sisa tiap sumber dana (termasuk BOSP Afirmasi).
- **Pengaturan** (`PengaturanController`) — *Koneksi ARKAS* (alamat pusat, NPSN, token, status),
  *Status Sinkronisasi* (identitas, transaksi lokal, terakhir didorong, tombol sinkron & uji
  koneksi), *Backup Data* (ZIP: `database.sqlite` + konfigurasi launcher tanpa token +
  `MANIFEST.json`), *Deteksi Versi ARKAS*, *Log Sistem* (jejak audit sekolah).
- **Laporan Realisasi per Sumber Dana** (`LaporanArkasController@realisasiSumber`) — pagu, rencana,
  penerimaan, belanja, sisa, dan serapan per sumber dana.
- **Referensi** kini 8 tab: rekening, pajak, **Program BOSP** (nama lama "Komponen BOSP"),
  **Kegiatan (Kode ARKAS)**, **Satuan**, **Uraian**, **PTK**, jenis BOS & tahap — plus halaman
  **Daftar Kegiatan** (`/penganggaran/kegiatan`) dengan pencarian & paginasi.
- **Review & Proporsi Anggaran** (`/penganggaran/review`, `RkasController@review`) — daftar kertas
  kerja tahun berjalan dengan total, pagu, temuan validasi, dan ringkasan proporsi komponen
  (Juknis 8/2026 Pasal 42) per kertas kerja, plus tautan ke panel review lengkap.

### 19.6 Perapihan menu (22 Okt 2026)

Tujuh temuan audit dibereskan:

1. **Tiga target dobel dihapus** — "RKAS Bulanan (ARKAS)" dan "Register Penutupan Kas (ARKAS)"
   (duplikat dari *Anggaran Bulanan* dan *Penutupan BKU*), serta "Kegiatan (Kode ARKAS)" di Data
   Referensi (sudah ada *Daftar Kegiatan*, dan tab Kegiatan di Referensi Master).
2. **"Review & Proporsi Anggaran" jadi halaman nyata** — sebelumnya hanya tautan kedua ke `/rkas`;
   kini `/penganggaran/review` berisi ringkasan proporsi + temuan tiap kertas kerja.
3. **URL menu kini mengikuti grupnya** (status aktif & grup terbuka jadi benar) — rute lama tetap
   dipertahankan sebagai alias: `/data-sekolah/sinkronisasi`, `/penganggaran/anggaran-bulanan`,
   `/penatausahaan/penutupan-bku`, `/laporan/realisasi-sumber-dana`, `/laporan/rekonsiliasi`,
   `/referensi/harga`, `/pengaturan/impor-database`, `/pengaturan/pemetaan`.
4. **Data Referensi diringkas 7 → 2 entri** — sub-data (program, kegiatan, satuan, uraian, PTK)
   dibuka lewat 8 tab di *Referensi Master*, tidak lagi memenuhi sidebar.
5. **Tautan "🧩 Referensi" ganda di sidebar sekolah dihapus** (menu Dinas tetap punya).
6. **"Pajak" diperjelas** menjadi **"Pajak (Setor & Utang)"**; "Pajak Triwulan" dan "Dokumen Pajak"
   tetap terpisah sesuai konteks.
7. **Penatausahaan diberi sub-judul** _Penerimaan_, _Pengeluaran & Pajak_, dan _Pelaporan_.
8. **Induk "🏠 Beranda" dihapus** — Dashboard kini tautan langsung teratas, tidak lagi grup
   satu-item; sidebar sekolah menjadi 7 grup.

Penjaga regresi: `MenuDanArsipTest::test_tak_ada_menu_duplikat_atau_tautan_mati` memastikan setiap
URL menu hanya muncul sekali dan semuanya dapat dibuka (200).

### 19.3 Master baru yang diimpor dari ARKAS

- **Satuan** — sheet ARKAS `Ref_Satuan` → tabel `satuans` (43 baris pada data uji).
- **PTK** — sheet ARKAS `PTK` → tabel `ptks` (nama, jenis kelamin, masa kerja, NUPTK,
  sertifikasi, cut-off; 42 baris pada data uji).
- **Uraian** — dikumpulkan dari rincian RKAS impor → tabel `uraian` (95 baris pada data uji).
- **Kode kegiatan** — sheet `Ref_Kode` → `arkas_kodes` (905 baris pada data uji).
- Perbaikan kecil: `ArkasImportService::sheetAssoc` kini menerima penanda kolom wajib, karena
  header `Ref_Satuan` (satuan/unit) tidak termasuk daftar kunci header standar.

### 19.4 Deteksi Versi ARKAS

Agen membaca `app_config.version_app` (nilai sebenarnya, mis. **4.3.0** — bukan "ARKAS 4.0" dari
tabel `app`) lalu melaporkan: versi, tahun anggaran aktif, **tenggat penambiguan anggaran** &
**tenggat penatausahaan**, batas pergeseran/perubahan, serta jumlah master (kode kegiatan,
rekening, PTK). Aplikasi membandingkan dengan minimum **4.2.18** (versi ketika Implementasi
Juknis BOSP 2026 mulai berlaku) dan menampilkan peringatan bila masih di bawahnya.

### 19.5 Pengujian

`MenuDanArsipTest` (12 test): 7 grup sidebar + Dashboard sebagai tautan langsung, 5 halaman
arsip, 2 halaman data sekolah, 5 halaman pengaturan (+ unduhan backup), laporan per sumber dana,
tab referensi baru, daftar kegiatan, pembatasan akses role (Dinas tidak bisa membuka arsip
sekolah), halaman *Review & Proporsi Anggaran* (label proporsi benar-benar tampil), serta penjaga
anti-duplikat/tautan mati. Suite penuh: **149 test hijau (820 asersi)**.

> Audit jalur tarik data (agen → impor) dan cara memeriksanya di laptop:
> lihat `docs/AUDIT-TARIK-ARKAS.md`.
