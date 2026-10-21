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
