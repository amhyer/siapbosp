# Gambaran ARKAS 4 (Aplikasi Rencana Kegiatan Anggaran Sekolah)

> Sumber: Pusat Informasi Rumah Pendidikan — kategori ARKAS (45 artikel "Penggunaan ARKAS 4" +
> "Informasi Umum") dan **Buku Panduan ARKAS 4 v4.3.0 (September 2026, 166 halaman)** yang tersimpan di
> `docs/Buku-Panduan-ARKAS4.pdf`. Dokumen ini adalah catatan pemahaman ARKAS 4 yang **asli** (bukan lagi modul Excel-makro pendamping),
> sebagai referensi desain SiapBOSP. Pengguna resmi: kepala satuan pendidikan (penanggung jawab), dengan
> akun per satdik yang terikat NPSN.

---

## 1. Apa itu ARKAS 4

- Aplikasi **desktop** (unduh & instal di komputer satdik, ada fitur *Perbarui Aplikasi* in-app dan
  *Unduh Database dari MARKAS*), terhubung ke sistem pusat **MARKAS** (Rumah Pendidikan).
- Satu aplikasi memakai tiga tahapan anggaran sekaligus:
  | Tahap | Menu dalam ARKAS 4 | Distinguish |
  |---|---|---|
  | Penganggaran | Penganggaran | menyusun makro kegiatan; belanja per akun; disahkan DinP-Prov |
  | Perencanaan | Penatausahaan | bulanan dari bedah kertas kerja ke arsip |
  | Penatausahaan | Penatausahaan: Pengeluaran, Aktivasi BKU, Perbarui Data BKU, Penarikan Tunai, Lapor Pajak, Tutup BKU | bulanan per sumber dana |
  | Pelaporan/pencetakan | Arsip | cetak RKAS/BKU yang sudah disahkan dinas/ditutup (per sumber dana dan akumulasi) |
- ARKAS 4 = RKAS (rencana kegiatan anggaran sekolah) + BKU (buku kas umum) + arsip.

## 2. Struktur menu utama

1. **Peningkatan** → halaman awal setelah login: daftar kertas kerja/RKAS **per sumber dana**,
   menampilkan KK **tiga tahun terakhir**; ikon: `Aktivasi Kertas Kerja`, panah buka, printer (cetak), trash (hapus
   — hanya untuk status Draf/Perlu Revisi/Perlu Diajukan Ulang), ⋮.
2. **Penatausahaan** → BKU per sumber dana per **bulan**: status BKU, `Aktivasi BKU` (satu tahun anggaran),
   12 tombol bulan (abu-abu Sampai diterima dana → Sudah Selesai untuk bulan sebelum pintu masuk dana
   otomatis), `Cetak` untuk tahun berjalan/lampau.
3. **Arsip** → cetak dokumen yang **sudah disahkan/ditutup**: pilih Jenis Dokumen, Sumber Dana, Tahun;
   format **Akumulasi** (seluruh sumber dana) atau **per Sumber Dana**.
4. **Perbarui Aplikasi** — update in-app; **Profil** (atur Data Penanggung Jawab, Keluar).
5. **Data Penanggung Jawab**: nama+NIP Kepala Sekolah; nama+NIP Bendahara; nama+**email Komite**
   (untuk PAUD/Kesetaraan tidak perlu komite); **Bulan Akhir periode jabatan** — jika bukan Desember
   → otomatis muncul Formulir **Periode 1 dan Periode 2** (pengganti mid-year). Sinkron Dapodik otomatis
   mengisi Kepsek/Bendahara. "Negeri wajib NIP; swasta opsional."
   Olak-olik data: tidak bisa menyunting PJ sebelum KK aktif / sedang diajukan pengesahan.

## 3. Sumber dana (kertas kerja dan BKU per sumber dana)

- **Funding Sources** yang mungkin muncul: BOSP Reguler (2 tahap salur), BOSP Kinerja & SiLPA BOSP Kinerja (1x salur),
  BOSP Daerah/BOSDA (maks. 2x salur), Sumber Dana Lainnya (bisa >1 dana, pagu manual),
  **Sumber Dana Tambahan** (BOSP Afirmasi, APBN Bantuan Pemerintah; muncul hanya bagi satdik penetapan pusat, via "Unduh dan Perbarui Data").
- Pagu BOSP Reguler = otomatis dari data jumlah murid Kepmendikbudristek (Registrasi Target Selektif);
  BOSP Kinerja = dari dinas (mutlak); BOSDA/Lainnya = diisi manual.
- Satdik Kesetaraan bisa punya 2 BKU (BOSP PAUD Reguler + BOSP Kesetaraan Reguler).

## 4. Status Kertas Kerja (RKAS)

`Belum Aktif → Draf → (Menunggu Pengesahan/Sedang Dikirim) → Sudah Disahkan` dengan cabang:
`Perlu Revisi` (ditolak dinas) dan `Perlu Diajukan Ulang`. Ketentuan kunci:
- **+ Tambah Kegiatan**: cari kegiatan (kata kunci/kode kegiatan), pilih Rekening Belanja,
  **Uraian** free-text maksimal **500 karakter** (untuk barang modal pilih dafar uraian yang tersedia),
  **Harga Satuan** harus dalam batas harga dinas (muncul perlu perlu), pilih **bulan**, jumlah + satuan,
  **+ Tambah Bulan** untuk kegiatan multi-bulan, `Masukkan Ke Anggaran`.
- Juknis BOSP 2023: **wajib menganggarkan 50% dana tersedia untuk Tahap 1**; proporsi per program dan
  batasan jenis belanja (mis. honor maks. 50% pagu; BOS Kinerja 50% biaya honor yang harus dikunci).
- Tabel atas laman: **Dana Pagu — Sudah Dianggarkan — Belum Dianggarkan** (harus **Rp0** dulu saat pengajuan).
- **Review Kertas Kerja** (VIII.1): tombol Review → ulas seluruh kegiatan; tampilan:
  **per tahapan** (khusus BOSP Reguler), **per tahun**, **grafik proporsi** (BOSP Reguler: proporsi
  belanja kegiatan belanja + **Belanja Honor dari Total Pagu** + antar jenis belanja dari total pagu;
  non-Reguler: hanya antar jenis belanja).
- **Ajukan Pengesahan** (VIII.2): Belum Dianggarkan Rp0 → kirim ke dinas via MARKAS; tenggat penganggaran
  berlaku; telaah/direview 7–14 hari kerja; jika ditolak → status Perlu Revisi + **Catatan Revisi Dinas**.
- **Cetak KK & RKAS** (VIII.4): **28 Format RKAS** sesuai peraturan terbaru; cetak (printer) atau Simpan sebagai PDF.
- **Sisip Uraian** (VII.4): duplikasi baris kegiatan pada KK untuk Kegiatan sama (uraian, harga satuan, bulan) — tersedia hanya untuk KK Murni/Pergeseran/Perubahan yang berstatus aktif, bukan saat diajukan/sudah disahkan.
- **Pergeseran RKAS** (bab IX): revisi **dalam satu jenis akun belanja yang sama** (Operasional / Alat dan
  Mesin / Aset — tidak boleh lintas akun), tanpa menyentuh kuota total, dapat kapan saja sesuai kuota dinas,
  tidak perlu menunggu BKU tutup; tidak bisa digunakan pada anggaran yang **sudah terealisasi di BKU**.
- **Perubahan RKAS** (bab XI): revisi lintas kode kegiatan, **hanya 1× per tahun**, syarat:
  BKU bulan tertentu harus tutup (umumnya setelah Juni; batas ditetapkan dinas); kembalikan sisa anggaran →
  isi bulan lain; **saldo anggaran harus balance** saat pengajuan; dengan 7-jenis output Perbarui Data
  khususungs. Batal otomatis jika melewati tenggat pengesahan pergeseran.
- **Ambil Sisa Kuota Anggaran** (X.3): setelah belanja penuh dihapus dari BKU, sisa dana + kuota
  dapat diambil kembali untuk dianggarkan ulang.

## 5. BKU — Buku Kas Umum (penatausahaan)

- **Aktivasi BKU** (bab XII): syarat: KK sumber dana sudah disahkan; dana sudah diterima;
  laporan BKU tahun sebelumnya selesai & dikonfirmasi dinas. Aktivasi = sinkronisasi data dengan
  **BOS Salur** (portal penyaluran dana) + MARKAS; frekuensi tanggapan konfirmasi penerimaan ≥24 jam.
- **Konfirmasi Penerimaan Dana** — 4 kondisi BOSP Reguler:
  1. Tahap 1 saja → tanggal & nominal **otomatis dari BOS Salur** (tidak bisa diedit di ARKAS);
  2. SiLPA saja / SiLPA + belum Tahap 1 → rincian SiLPA **manual** kolom **Nontunai** & **Tunai**
     (salah satu kolom otomatis menyesuaikan; tidak boleh melebihi total SiLPA; SiLPA memotong Tahap 2);
  3. hanya SiLPA tanpa salur; 4. SiLPA + Tahap 1 bersamaan. → klik Konfirmasi → Aktifkan BKU.
- **Perbarui Data BKU** (XII.3): pemeriksaan data perangkat vs MARKAS/BOS Salur dengan **7 output**:
  (1) sudah sesuai; (2) perubahan tanggal dana; (3) perubahan nominal salur/SiLPA; (4) penambahan salur
  tahap 1/2/SiLPA; (5) perubahan sisa dana positif; (6) sisa dana negatif; (7) tanggal transaksi invalid.
  Tidak bisa dipakai jika BKU sudah Ditutup sampai Desember. Pembetulan otomatis jika bunga/pajak
  tercatat ganda.
- **Dashbor BKU** (halaman bulan): Total Dana Tersedia; saldo **Nontunai (bank)** & **Tunai**;
  **Nominal Bisa Dibelanjakan** (anggaran sampai bulan ini sesuai RKAS), **Sudah Dibelanjakan**,
  **Bisa Dianggarkan Ulang** (bisa diambil sebagai syarat kuota habis); **status bar progress** khusus BOS Reguler Jan–Jun; tombol: `+ Tambah Pembelanjaan`, `Cari`, `Cetak`, `Tarik Tunai`, `Setor Tunai`, `Hapus BKU`, `Tutup BKU`, `Perbarui Data BKU`, sudah selesai/sudah selesai status.
- **Status BKU bukanAktif (X.7)**: KK belum disahkan; dana belum diterima (tunggu konfirmasi BOS Salur);
  belakangan belum dikonfirmasi; **saldos per tahun bermasalah** (laporan tahun sebelumnya belum dikonfirmasi —
  sesuai Juknis BOSP 2023 BKU tahun berjalan baru bisa aktif setelah laporan tahun lalu selesai);
  gangguan sinkronisasi.
- **Status laporan bulan**: `Draf` (setelah diterima dana; otomatis tersimpan lokal), `Sudah Selesai`
  (bulan-bulan sebelum penerimaan dana otomatis "tertutup"), setelah Tutup BKU: `Sudah Selesai`;
  status tambahan: **Belum Lapor Pajak** dan **Aktif - Lewat Batas Lapor** (lewat tenggat pelaporan →
  dilarang dikurangi penyaluran/pembekuan berikutnya). Cetak BKU bulan berjalan memberi **watermark "Draft"**.

## 6. Mengisi BKU (bab XIII–XVI)

Wajib urutan: RKAS disahkan → aktivitas tarik tunai/setor tunai/belanja nyata → catatan **kronologis**
per bulan. Halaman BKU bulan:

1. **+ Tambah Pembelanjaan** → Form isian **Detail Transaksi**:
   - centang **SIPLah** / tidak; **Tanggal Transaksi** (harus bulan pelunasan yang sama);
   − **Jenis Transaksi**: **Tunai** atau **Non-tunai** (saldo tunai/nontunai ditampilkan sesuai pilihan);
   - centang **usaha perseorangan** jika belanja bukan toko/badan usaha → kolom berubah
     (nama orang penerima, alamat, telepon, NPWP/NIK);
   - nama toko/badan usaha, alamat, telepon, **NPWP (15 digit) atau NIK (16 digit)** — NPWP/NIK wajib;
     centang "tidak punya NPWP" jika tidak ada → Lanjut.
2. **Form Detail Barang/Jasa** (XV.3): **nomor nota** (jika ada), **tanggal belanja** (dan tidak boleh
   Tahun lampau), **Kegiatan** (cari), **Rekening Belanja** (cari), **Uraian** muncul dari **Kertas Kerja**
   (klausa/kuota: jumlah maksimal per item dan harga satuan tidak boleh melebihi rencana) → edit ≡
   **Jumlah** & **Harga Satuan** riil; **+ Tambah Kegiatan Lain** untuk beberapa kegiatan dalam nota yang sama → Lanjut.
3. **Form Perhitungan Pajak** (XV.4/5): sistem merekomendasikan jenis pajak: **PPN (12% default TA 2025, opsi 11%)**
   atau **SSPD/PD-Daerah**. Ambang: transaksi **sebelum PPN ≤ Rp2.000.000 → tidak terutang (Pajak Wajib Lapor = Rp0)**;
   **> Rp2.000.000 → wajib memungut PPN** (nilai minimum dihasilkan: dengan PPN 2.240.000).
   Bisa **Tambah Pajak** (jenis lain sesuai daerah) / **Hapus**. Untuk SIPLah: pajak **otomatis**
   (memungut, setor, lapor) oleh sistem.
4. **Tarik Tunai** (XIV.1): tanggal + nominal (tidak melebihi saldo bank); tercatat **berwarna biru**;
   meningkatkan **saldo tunai**. **Setor Tunai** (XIV.2): memindahkan tunai ke nontunai (dampak ke
   saldo non tunai); dapat edit/hapus via menu ⋮ (hapus hanya menyisakan yang uangnya belum dibelanjakan).
   Transaksi non-tunai boleh langsung selama saldo nontunai cukup.

## 7. SIPLah (bab XVI)

- Centang belanja SIPLah → sistem menarik **daftar transaksi yang sudah lunas** pada **Bulan Pelunasan**
  (bulan BKU aktif); pencarian via invoice/nama marketplace/penyedia; pilih Jenis Transaksi dulu agar saldo muncul.
- **Halaman Pemetaan Pembelanjaan (HPP)**: petakan belanja SIPLah → Kegiatan/Rekening/Uraian dari KK.
- **Detail Pencatatan**: rincian pembelanjaan + **Detail Biaya Lainnya**:
  **Biaya Ongkos Kirim** dan **Biaya Administrasi (transfer bank, asuransi, pengemasan)** —
  keduanya **TIDAK mengurangi** dana BOSP tersedia tahap terkait.
- Kesalahan pemetaan → **Edit Pembelanjaan**; jika data SIPLah rusak/notifikasi muncul → Lanjut & Edit Pembelanjaan;
  jika KK Murni ditolak saat pemetaan → klik Lanjut.
- Manfaat: sinkronisasi otomatis — tak perlu manual memungut/setor/lapor pajak.

## 8. Tutup BKU, Lapor Pajak, Hapus BKU (bab XVII–XX)

- **Tutup BKU** per bulan (kronologis; tidak bisa menutup bulan sebelum bulan sebelumnya; BKU Juni 2024 tak
  bisa ditutup di Mei). **Tenggat pelaporan**: panggilan pengingat H-7 tenggat berdasarkan Juknis BOSP 2023;
  menutup BKU bulan berjalan dengan pajak belum lapor → tetap bisa, tapi status **Belum Lapor Pajak**.
- **Tutup BKU Satu Tahun** (Desember): setelah ini, **SiLPA tahun berjalan akan mengurangi penerimaan**
  tahun anggaran berikutnya; BKU tak bisa diubah; tidak bisa Perbarui Data BKU.
- **Lapor Pajak** (bab XIX): klik kegiatan yang **Pajak Wajib Lapor**-nya **merah** → **Lapor Pajak** →
  **Tanggal Lapor** + **NTPN** (Nomor Transaksi Penerimaan Negara dari Bukti Bayar/Bukti Setor Pajak) → Simpan.
  Setelah lapor, nominal jadi **hitam**. Wajib untuk sumber dana **APBN (BOSP Reguler/Kinerja)**;
  transaksi ≤Rp2jt dan semua SIPLah otomatis tidak wajib (sistem).
- **Hapus BKU** (bab XX): pengajuan ke dinas ( satu-satu bulan, dinas punya kuota penghapusan);
  saat pengajuan + dinas menyetujui → BKU kembali kosong (murni) dan bisa diisi ulang;
  jika ditolak → **Lihat Catatan Dinas**; pengajuan bulan lain belum selesai memblokir pengajuan baru.
- **Bunga bank**: tercatat otomatis di BKU (Perbarui Data BKU mampu membetulkan bunga/pajak ganda).

## 9. Cetak (bab VIII.4, XXI, II Arsip)

- **Cetak RKAS**: dari notifikasi disahkan atau ikon printer; pilih salah satu dari **28 Format RKAS**.
- **Cetak BKU**: dari laman utama Penatausahaan (tahun penuh; pilih Format BKU + **Periode**) atau dari bukan
  BKU bulan tertentu. **Format BKU**: **Tahapan** (khusus BOSP Reguler) dan Tahunan; untuk Sumber Dana
  Non-Reguler tersedia **Rekapitulasi Realisasi (Semester)**, **Buku Pembantu Rincian Objek Belanja (Semester)**,
  **Surat Pernyataan Tanggung Jawab Mutlak**. BKU bulan berjalan tercetak dengan **penanda "Draft"**.
- **Arsip**: RKAS/BKU yang sudah disahkan/ditutup — Format **Akumulasi** atau **per Sumber Dana**;
  periode dipilih dari BKU Tahunan/Arsip.

## 10. Glosarium penting

MARKAS (Rumah Pendidikan — pusat data & workflow), BOS Salur (portal penyaluran dana), Dapodik,
SIPLah (Lembaha pengadaan pemerintah untuk sekolah), RAS = Rencana Kegiatan Anggaran Sekolah,
Pagu, jumlah kuota, HPP/pemetaan pembelanjaan, NTPN, SSPD, BOS Kinerja, SiLPA (Sisa Likuid PAA
/ sisa anggaran tahun lalu), penyed, pemadanan NPWP-NIK, pendanaan BP-BPMP/provinsi/kab.

---

## 11. Pemetaan ke SiapBOSP (per tanggal ini)

| Konsep ARKAS 4 | Implementasi SiapBOSP | Status |
|---|---|---|
| RKAS/Kertas Kerja per sumber dana | `rkas`/`rkas_details` per `sumber_dana`, tahap 1..N via `periode_volume` | ✅ |
| Data Penanggung Jawab | sect. profil sekolah (kepala/bendahara/komite) untuk kop tanda tangan | ✅ |
| BKU per bulan + saldo tunai/nontunai | `pembukuan/bku` (format kolom ARKAS: Tanggal/Kegiatan/Rekening/No. Bukti/Uraian/Penerimaan/Pengeluaran/Saldo) + `KasService::ledger` | ✅ |
| Tarik/Setor Tunai | menu **"💵 Tarik Tunai (Bank→Tunai)"** & **"💵 Setor Tunai (Tunai→Bank)"** — istilah ARKAS dipakai langsung | ✅ |
| Detail Transaksi + NPWP/NIK toko | `kas_notas` (nama_toko, alamat_toko, NPWP, total) via impor nota ARKAS | ✅ |
| Pajak PPN/SSPD + NTPN + Pajak Wajib Lapor | pajak 1 baris per (trx,jenis)+NTPN; badge **"⚠ wajib lapor"** (merah) → **"✓ sudah lapor"** (hijau) di daftar belanja; pemberitahuan ≥ Rp2 jt bila belanja non-SIPLah; tenggat tgl 20 di kartu pajak dashboard | ✅ |
| Kontrol dana BKU (panel) | panel "📊 Kontrol Dana Tahun D": Saldo Tunai/Bank, Dana Tersedia (SILPA+salur), Sudah Dibelanjakan, Bisa Dianggarkan Ulang, Total Kas + bar serapan pagu ala ARKAS | ✅ |
| BKU 12 tombol bulan (status) | grid 12 bulan di halaman BKU: **Tertutup / Sudah Selesai / Draf / Belum Ada Dana / Belum Aktif**, klik untuk membuka; bulan berjalan ditandai | ✅ |
| Tutup BKU bulanan | **🔒 Tutup BKU bulan ini** (siapa pun) → semua catatan/ubah/hapus di bulan itu diblokir; **🔓 Buka Kembali khusus Kepala Sekolah**. Disimpan `bku_tutup_bulans` (mirror `tutup_bukus` per tahap). Impor ARKAS melompat baris pada bulan yang tertutup. | ✅ |
| SIPLah + HPP | imput Excel & HPP SIPLah | ✅ |
| Tutup BKU / hormati tutup buku | impor idempoten menghormati tutup buku (tahap DAN bulan) | ✅ |
| Konfirmasi Penerimaan Dana via BOS Salur | input manual `salur` + **kartu Pagu vs Diterima (SILPA disebut)** di pendapatan; melewati pagu = peringatan (tidak memblokir) | ✅ (manual + pagu) |
| Laporan resmi cetak | Modul **Laporan ARKAS** (9 laporan + Register Penutupan Kas + BKU) kolom-per-kolom sama dengan lembar workbook ARKAS v1.4 | ✅ |
| Realisasi satu pintu | laporan native "Realisasi vs RKAS" dinonaktifkan (`/laporan/realisasi` → 404); satu-satunya **Realisasi Anggaran (ARKAS)** kini memuat **Komponen** + kolom **%** tercapai + tombol **⬇ Ekspor CSV** (`/laporan-arkas/realisasi.csv`, pemisah titik-koma); rekap bulanan mengikuti format resmi ARKAS | ✅ |
| Label anti-duplikat | menu "Laporan per Tahap" (transaksi + tutup buku + kirim LPJ) diganti **"LPJ & Tutup Buku per Tahap"** agar tidak dikira sama dengan "Rincian RKAS per Tahap (ARKAS)" yang berisi rencana anggaran; tombol dashboard ikut berganti "🖨️ LPJ per Tahap" | ✅ |
| Taut silang BKU ↔ Kas Umum | halaman BKU punya tombol **"🔎 Detail Nota: Daftar Kas Umum"** (bulan sama, tab baru); Daftar Kas Umum punya tautan balik **"← Buku resmi BKU bulan ini (dengan saldo)"** — dua sisi transaksi yang sama (detail nota vs buku resmi bersaldo) jadi satu keluarga | ✅ |
| Paket Dokumen SPJ belanja | tombol **"🗂️ Paket Dokumen SPJ"** pada detail belanja — 5 dokumen satu halaman (SP → BA Negosiasi → Surat Hasil Pemeriksaan → BAST → Rekap DOKUMEN PENGADAAN), penyedia diisi otomatis dari nota ARKAS (nama/alamat/telepon/NPWP), sel isian kosong (nomor, tanggal terima, waktu pengerjaan, nama perwakilan, kuantitas, spesifikasi) tinggal diklik-isi lalu `window.print()`; Terbilang di SP | ✅ |
| Format BKU Semester (2 dari 4 format BKU) | **📚 Buku Pembantu Rincian Objek Belanja** (`/pembukuan/buku-pembantu`): pengeluaran per kode rekening, saldo kumulatif per transaksi + saldo awal dari bulan sebelum semester; **📊 Rekapitulasi Realisasi** (`/pembukuan/rekap-realisasi`): Anggaran (RKAS aktif) vs Realisasi vs Sisa Pagu (bahan SiLPA); keduanya filter semester 1 (Jan–Jun) / 2 (Jul–Des), tombol langsung dari halaman BKU, ttd Kepsek + Bendahara | ✅ |
| Bunga bank (pendapatan insidental) | kartu **"💰 Bunga Bank"** di halaman Pendapatan: penerimaan di luar RKAS, masuk Kas Bank (seganti tahap terbuka, keterangan "Bunga Bank F Y" — nama bulan Indonesia), menghormati tutup tahap & bulan; memuncak saldo | ✅ |
| SiLPA eksplisit | kartu Dana Tersedia panel BKU kini rinci: **SiLPA tahun lalu + salur baru** terpisah; halaman Pendapatan sudah menunjukkan SILPA; Rekap Realisasi semester menjelaskan sisa pagu = bahan usulan SiLPA | ✅ |
| Status workflow (Draf → Disahkan) | **belum diselaraskan penuh** — tugasan desain (lihat §12) |
| 28 Format RKAS / 4 Format BKU | subset: RKAS Bulanan/Rincian per Tahap/Realisasi/Per kategori Akun/Habis Pakai/Modal/Kas Umum/Nota/Registers | ✅ sebagian |

## 12. Ide penyelarasan lanjutan (nyata-ARKAS, bukan improvisasi)

Sudah dieksekusi (Oktober 2026): **Tarik/Setor Tunai (istilah), panel kontrol BKU, grid 12 bulan,
Tutup/Buka BKU per bulan, Pajak Wajib Lapor badge + tenggat tgl 20, konfirmasi pagu pendapatan,
Paket Dokumen SPJ lengkap 5 dokumen, banner pengingat tenggat (pajak tgl 20 + tutup BKU akhir bulan) di dashboard,
Format BKU Semester: Buku Pembantu Rincian Objek Belanja + Rekapitulasi Realisasi,
Bunga Bank + SiLPA eksplisit,
konsolidasi laporan: Realisasi satu pintu (Komponen+%+CSV masuk ke format ARKAS),
rename "LPJ & Tutup Buku per Tahap", taut silang BKU ↔ Daftar Kas Umum,
penggabungan fitur dari v23 (lihat §13)** —
lihat §11. Sisa (prioritas rendah), pilih kapan bila diminta:
1. ~~Status Kertas Kerja~~ ✓ dasarnya sudah ada: RkasHeader punya alur lengkap
   `draft → diajukan → disetujui → disahkan → dikunci` berikut persetujuan Dinas (rkasSahkan/rkasRevisi) —
   yang mungkin tersisa hanya pemoles UI tenggat revisi bila diminta sekolah.
2. ~~Pengingat tenggat aktif~~ ✓ sudah: banner dashboard tampil antara tgl 12–20 bila ada pajak belum setor,
   dan sejak tgl 24 bila BKU bulan berjalan belum ditutup (tautan langsung ke halaman BKU).
3. ~~Format BKU tambahan~~ ✓ sudah: Buku Pembantu Rincian Objek Belanja (Semester) & Rekapitulasi
   Realisasi (Semester) — lihat baris "Format BKU Semester" pada §11.
4. ~~Dokumen SPJ~~ ✓ sudah: lihat baris "Paket Dokumen SPJ belanja" pada §11.
5. ~~Bunga bank & SiLPA~~ ✓ sudah: kartu Bunga Bank di Pendapatan + rincian SiLPA pada panel BKU
   (saldo awal tahun otomatis membawa SiLPA tahun sebelumnya; tutup tahun berikutnya mengalir lagi).
   **Semua item penyelarasan ARKAS-4 yang terencana di §12 selesai.**

## 13. Penggabungan cabang v23 (fitur Dinas & komunikasi)

Aplikasi v23 adalah cabang sejajar dari basis kode yang sama, dengan fokus **pengelolaan Dinas
& komunikasi** — fiturnya komplementer dan kini sudah **digabung penuh** ke aplikasi ini
(dipilih gabung penuh, antrian 21 Okt 2026). Semuanya bersifat menambah; 2 migrasi baru tidak
bentrok nomornya, dan progres ARKAS di §1–§12 tetap utuh:

| Fitur (dari v23) | Wujud di aplikasi ini | Status |
|---|---|---|
| Aktivasi akun | `users.is_active` (Dinas bisa 🔇/🔊 akun); login ditolak bila akun/sekolah nonaktif + pesan jelas | ✅ |
| Throttle login & jejak audit | maks. 5 gagal per 5 menit (email+IP); semua percobaan `auth-ok/gagal/blok` terekam `activity_logs` → halaman **🛡️ Keamanan** (statistik 24 jam + filter email) | ✅ |
| Kelola pengguna Dinas | pada daftar 👥 Pengguna: ✏️ ubah identitas/peran/sekolah, 🔁 reset sandi (manual/acak + email), 🔇/🔊, 🗑 hapus (proteksi akun diri sendiri & akun Dinas terakhir) | ✅ |
| Master Data Dinas | 🧱 **Master Data** (`/dinas/master`): rekening & komponen + **impor CSV** upsert aman + unduh contoh CSV; melengkapi master hasil Impor ARKAS | ✅ |
| Notifikasi email | `EmailNotif` + antrean `KirimEmailNotif` — RKAS masuk/disahkan/perlu revisi, LPJ masuk/diterima/ditolak, tahap dibuka/ditutup, paket tahap baru, pengumuman, reset sandi. **Tidak pernah mengganggu alur utama** (queue/sync + try-catch); mode `log` tercatat di laravel.log; kartu 📧 + tombol kiriman email uji di Dashboard Dinas | ✅ |
| Tanda tangan basah digital | `penandatangans.ttd_path` + `TtdGambar` (PNG dirapikan, disimpan privat) — otomatis tercetak di **LPJ cetak/PDF, RKAS PDF**, unggah/hapus di 📝 Penandatangan | ✅ |
| LPJ Kumulatif (cakupan Tahap 1–N) | tombol **🔁 Mode Kumulatif** di LPJ & Tutup Buku per Tahap: kas, BKU, rekap & pajak berlaku kumulatif dengan label "Kumulatif Tahap 1–N" + saldo awal tahun (SILPA) — berlaku juga di PDF/Excel 4-sheet | ✅ |
| Buat paket tahap tahun baru | tombol **🗓️ Buat Paket Tahun** di Kontrol Tahap: **jumlah tahap fleksibel 2–5 (default 5)** — ikut aturan juknis TA 2026 *dua tahap salur* (Permendikdasmen No 8/2026); idempoten + notifikasi sekolah | ✅ |
| Cek juknis 50% Tahap 1 | 💡 kartu **Cek juknis 50%** di Kontrol Tahap: belanja tercatat tahap pertama vs pagu aktif per sekolah (✓ ≥50% / ⚠ <50% / pagu belum ada) — pengingat bantu, bukan blokir | ✅ |
| Laporan Pajak Triwulan | 🧾 **Kertas Kerja Pajak Triwulan** (`/laporan/pajak-triwulan`): matriks dipungut (kode billing) vs disetor (NTPN) per jenis pajak per bulan + sisa kewajiban; unduh CSV | ✅ |
| Buat paket tahap tahun baru | tombol **🗓️ Buat Paket Tahun** di Kontrol Tahap: idempoten membuat Tahap 1–5 (Tahap 1 langsung terbuka) + notifikasi semua sekolah | ✅ |

Catatan teknis penggabungan:
- **Arah**: v23 → aplikasi ini (yang lebih maju data & uji). Laporan
  "Realisasi vs RKAS" native milik v23 **tidak dipulihkan** — fungsinya telah disatukan ke
  **Realisasi Anggaran (ARKAS)** (lihat §11 baris "Realisasi satu pintu").
- **Migrasi baru**: `2026_10_20_000001_add_is_active_to_users` & `000002_add_ttd_path_to_penandatangans`.
- **Pengujian**: `tests/Feature/GabungV23Test.php` — 14 test (kelola akun, nonaktif login,
  reset sandi, keamanan, impor master CSV **+ render halaman Master**, template, paket tahap
  idempoten **(jumlah fleksibel 2–5)**, **kartu cek juknis 50%**, halaman+CSV pajak triwulan,
  unggah/hapus TTD, email uji, **tinjau LPJ halaman Dinas**). Suite penuh **110 lulus, 2 gagal bawaan** §11.
- **QA smoke semua halaman (21 Okt 2026)**: 79 rute GET dikunjungi via hasil kunjungan
  terotentikasi (guest, operator sekolah, dinas) — **79/79 status 200/302 tanpa 500**.
  Dua bug hasil merge tertangkap & diperbaiki: relasi `Komponen::rekenings()` hilang
  (halaman Master 500) dan `lpjShow` yang belum meneruskan `kumulatif`/`cakupan`
  (tinjau LPJ Dinas 500 §13-gabung view `lpj-cetak`).

## 14. Aset pendamping: ARKAS4-Pendamping-Sekolah.html

Alat bantu **satu file HTML** (bukan bagian aplikasi Laravel) persiapan input ke **aplikasi
resmi ARKAS 4**: checklist 13 item pra-input, kertas kerja angka (pagu → rencana → realisasi
dengan % + peringatan), rekap `.txt`, dan 7 tautan panduan resmi (portal ARKAS + pusat
bantuan Rumah Pendidikan, termasuk panduan Kertas Kerja, Pengesahan, Pergeseran, Perubahan).
Tersimpan otomatis via localStorage; tanpa jaringan; disclaimer independen + vocab "MARKAS"
sesuai panduan resmi.

- **Sumber**: `ARKAS4-Pendamping-Sekolah.html` (akar repo, ✍️ sumber utama yang diedit).
- **Disajikan**: salinan di `public/pendamping-arkas.html` → buka langsung
  `http://<server>/pendamping-arkas.html` dan **tautan "🧭 Pendamping ARKAS 4"** pada
  halaman **Impor ARKAS** (bersama taut unduh aplikasi/pemasang) — ikut terbawa paket portabel.
- **Audit 21 Okt 2026**: JS valid (node --check), 17 ID DOM terawat, 5 handler terdefinisi,
  semua tautan `target=_blank rel=noopener`, artikel resmi diverifikasi hidup.
- **Konten diperbarui 21 Okt 2026**: penekanan juknis — **minimal 50% dana tersedia untuk
  Tahap 1** & **TA 2026 dua tahap salur (Permendikdasmen No 8/2026)** pada kartu 01/Alur
  + item checklist ke-13; stamp "Sumber diperiksa 21 Oktober 2026 (artikel resmi terakhir 8 September 2026)".

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
