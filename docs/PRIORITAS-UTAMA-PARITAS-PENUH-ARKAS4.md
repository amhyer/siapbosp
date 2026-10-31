# 🚨 PRIORITAS UTAMA: Peta Jalan Paritas Visual & Fungsional 100% ARKAS 4 (`v4.3.0` / `v4.3.1`)

**Tanggal Penyusunan:** 29 Oktober 2026  
**Status:** 🔥 **PRIORITAS UTAMA (P0) — Siap Dikerjakan pada Sesi Berikutnya**  
**Sumber Rujukan Otentik:** 45 tangkapan layar (`.png`) dan 1 rekaman video penggunaan langsung berdurasi 6 menit (`Screen Recording 2026-10-29 171232.mp4`) di dalam folder `docs/FOTO DAN VIDIO ARKAS/`.

---

## Ringkasan Eksekutif

Dokumen ini merangkum seluruh anatomi antarmuka (UI), perilaku interaksi (UX), dan alur layar dari aplikasi asli **ARKAS Desktop `v4.3.0` / `v4.3.1`** yang terekam di `docs/FOTO DAN VIDIO ARKAS/`, serta memetakan daftar pekerjaan prioritas utama untuk menjadikan tampilan dan alur **SiapBOSP 100% identik dengan ARKAS**.

---

## 1. Kerangka Utama (Topbar, Sidebar, & Mode *Full-Screen*)

### Bukti Visual (`Screenshot 2026-10-21 235046.png`, `235053.png`, `2026-10-29 171035.png`, Video `00:00–00:35`)
1. **Topbar Putih (`54px`)**:
   - **Kiri**: Hanya ikon logo **A** berwarna + teks **ARKAS** (tanpa badge versi atau subteks).
   - **Kanan**: Hanya lingkaran avatar biru tua (`#1e3a8a`) berinisial **`US`** + nama sekolah (**`UPT SPF SD NEGERI UNGGULAN MONGISIDI 1`**) + chevron `⌄`.
   - **Catatan Penting**: Tidak ada pemilih Tahun Anggaran (`TA`) dan tidak ada ikon lonceng notifikasi di topbar ARKAS.
2. **Dropdown Profil & Modal `Data Penanggung Jawab`**:
   - Klik nama sekolah di kanan atas hanya menampilkan popup putih berisi **2 menu berikon**:
     1. **`Data Penanggung Jawab`**
     2. **`Keluar`**
   - Klik **`Data Penanggung Jawab`** membuka **Modal Dialog** (`Screenshot 2026-10-21 235053.png`) dengan:
     - Pemilih Tahun (`2026`) di kiri atas.
     - Subjudul `Periode 1` dengan teks *"Anda dapat memasukkan data penanggung jawab yang berbeda untuk periode waktu yang berbeda."*
     - Input `Bulan Awal: Januari` (disabled) dan `Bulan Akhir: Desember` (disabled).
     - 3 kolom form sejajar:
       - Kolom 1: `Nama Kepala Sekolah` & `NIP Kepala Sekolah`
       - Kolom 2: `Nama Bendahara` & `NIP Bendahara`
       - Kolom 3: `Nama Komite` & `Email Komite`
     - Kaki modal: teks biru `"Perbarui" membutuhkan koneksi internet`, tombol `Batal`, dan tombol biru `Perbarui`.
3. **Sidebar Kiri Biru Tua (`#1e3a8a`, lebar `215px`)**:
   - **Bagian Atas**: Hanya **3 menu teks polos tanpa ikon**:
     - `Penganggaran`
     - `Penatausahaan`
     - `Arsip`
     *(Item aktif berwarna biru cerah `#2563eb` / `#1d4ed8` dengan sudut membulat `6px`).*
   - **Bagian Bawah**: Hanya **4 menu berikon putih**:
     - `ⓘ Tutorial`
     - `▤ Pertanyaan Umum`
     - `◎ Hubungi Kami`
     - `↻ Perbarui Aplikasi`
4. **Mode *Full-Screen* Tanpa Sidebar & Topbar pada Halaman Detail/Review/Cetak**:
   - Di ARKAS, **sidebar biru dan topbar hanya muncul di 3 halaman utama** (`Penganggaran`, `Penatausahaan`, `Arsip`) serta sub-halaman `Perbarui Data BKU`.
   - Saat pengguna membuka:
     - **Detail RKAS (`Rencana Kegiatan Anggaran Sekolah (RKAS)`)**
     - **Review Kertas Kerja (`Review Kertas Kerja`)**
     - **Lembar BKU Bulanan (`BKU Juli 2026`)**
     - **Pratinjau Cetak (`Mencetak RKAS 2026` / `Mencetak BKU 2026`)**
     maka **sidebar kiri dan topbar disembunyikan sepenuhnya**, sehingga halaman tampil *full-width* (`100%` lebar layar) dengan tombol kembali `← Kembali ke ...` di pojok kiri atas.

### Target File di SiapBOSP:
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/_shell.blade.php`
- `resources/views/layouts/_interaksi.blade.php`
- `resources/views/layouts/_gaya-darurat.blade.php`

---

## 2. Area Kerja 1: Halaman Penganggaran (`Penganggaran`)

### Bukti Visual (`Screenshot 2026-10-29 171035.png`, `235131.png`–`235213.png`, Video `00:02–00:38`)
1. **Satu Kartu Utama Putih** dengan **pita 6 tab sumber dana** di bagian atas kartu:
   - `BOSP Reguler` | `BOSP Daerah` | `BOSP Kinerja` | `SiLPA BOSP Kinerja` | `Lainnya` | `Sumber Dana Tambahan` *(tanpa tab "Semua sumber dana")*.
2. **Daftar Kartu Kertas Kerja 3 Tahun Terakhir (`2026`, `2025`, `2024`)**:
   - Setiap tahun tampil sebagai kartu abu-abu muda (`#f5f5f5`) memanjang:
     - **Jika Sudah Disahkan** (`RKAS BOSP Reguler 2026`):
       - Kiri: Ikon dokumen biru + judul **`RKAS BOSP Reguler 2026`** + badge kapsul hijau **`Sudah Disahkan`** + subteks `Ver. Pergeseran - 29 Sep 2026`.
       - Kanan: Hanya **3 tombol ikon persegi (`36×36px`)**: **Buka/Edit** (kotak pensil), **Hapus** (tempat sampah abu-abu nonaktif bila sudah sah), dan **Cetak** (ikon printer).
     - **Jika Belum Aktif** (`Kertas Kerja BOSP Daerah 2026` / `BOSP Kinerja 2026`):
       - Kiri: Ikon dokumen abu-abu + judul **`Kertas Kerja BOSP Daerah 2026`** + badge putih **`Belum Aktif`**.
       - Kanan: Tombol hitam **`Aktivasi Kertas Kerja`** + subteks kecil `"Aktivasi Kertas Kerja" membutuhkan koneksi internet`.
     - **Jika Nonaktif / Lewat Batas** (`Kertas Kerja BOSP Daerah 2025` / `2024`):
       - Kiri: Ikon dokumen abu-abu + judul + badge abu-abu **`Nonaktif - Lewat Batas`**.
       - Kanan bawah: Teks `Kertas Kerja tidak dapat diaktifkan karena melewati tenggat 31 Desember 2025`.
3. **Khusus Tab `Sumber Dana Tambahan` (`235207.png`, `235213.png`, Video `00:20–00:35`)**:
   - Baris kontrol di atas daftar: dropdown **`Pilih Sumber Dana Tambahan`** (`Semua Sumber Dana Tambahan`) di kiri dan tombol **`↻ Unduh dan Perbarui Data`** di kanan (dengan subteks `"Unduh dan Perbarui Data" membutuhkan koneksi internet`).
   - Klik `Unduh dan Perbarui Data` memunculkan modal putih dengan ikon centang hijau besar, judul **`Berhasil Perbarui Data`**, pesan *"Data sumber dana tambahan sudah yang paling baru"*, dan tombol hitam **`Saya Mengerti`**.
   - Daftar kartu di bawahnya dikelompokkan berdasarkan nama bantuan (contoh: subjudul **`Banpem Keterampilan Menulis pada Sekolah Dasar`** diikuti kartu tahun `2026`, `2025`, `2024`).

### Target File di SiapBOSP:
- `resources/views/anggaran/index.blade.php`
- `resources/views/partials/_tab-sumber-dana.blade.php`
- `app/Http/Controllers/RkasController.php`

---

## 3. Halaman Detail RKAS & Review Kertas Kerja

### Bukti Visual (`Screenshot 2026-10-28 145552.png`, Video `00:40–01:55`)
1. **Halaman Detail RKAS (`Rencana Kegiatan Anggaran Sekolah (RKAS)`)**:
   - Tampil **Full-Width tanpa sidebar** dengan tautan `← Kembali ke Penganggaran`.
   - **Header Kiri**: Judul `Rencana Kegiatan Anggaran Sekolah (RKAS)`, subjudul `BOSP REGULER 2026`, dan kotak informasi berbingkai `Pagu Dana BOSP Reguler | Rp 342.900.000`.
   - **Header Kanan**:
     - Banner hijau: `✓ RKAS Sudah Disahkan! Baca Info Selengkapnya`.
     - Baris 5 tombol aksi: `🔍 Cari`, `↻ Perubahan`, `⇄ Pergeseran`, `🖨 Cetak`, dan tombol hitam `Review` (diikuti subteks biru `"Perubahan" dan "Pergeseran" membutuhkan koneksi internet`).
   - **Baris Periode & Pita 12 Bulan**:
     - Judul `Periode Anggaran 2026` di kiri dan kotak `Dianggarkan | Rp 342.900.000` di kanan.
     - Pita **12 tab bulan (`Januari` s.d. `Desember`)** dengan garis bawah biru pada bulan aktif.
   - **Tabel Rincian Hierarki Berwarna**:
     - Kolom: `No`, `Program Kegiatan`, `Kegiatan`, `Rekening Belanja`, `Uraian`, `Dianggarkan`, `Dibelanjakan`, `Satuan`, `Harga Satuan`, `Total`, `Aksi` (`⋯`).
     - Warna baris bertingkat khas ARKAS:
       - Baris **Program Kegiatan**: Biru muda (`#dbeafe`)
       - Baris **Kegiatan**: Cyan muda (`#e0f2fe`)
       - Baris **Rekening Belanja**: Hijau muda (`#dcfce7`)
       - Baris **Uraian**: Kuning-hijau muda (`#ecfccb`) / Putih
2. **Halaman Review Kertas Kerja (`Review Kertas Kerja`, Video `00:45–01:50`)**:
   - Tampil **Full-Width tanpa sidebar** dengan tautan `← Kembali ke Halaman RKAS`.
   - 2 kartu ringkasan di kiri atas: **`Sudah Dianggarkan (Rp 342.900.000)`** (hijau) & **`Belum Dianggarkan (Rp 0)`** (kuning).
   - Dropdown **`Pilih tampilan hasil review`** dengan 2 pilihan:
     - **Mode 1: `Lihat per tahapan`** (`frame_02_47s.jpg`):
       - Tab `Periode Salur Tahap 1` & `Periode Salur Tahap 2` di kiri, kotak `Anggaran Tahap 1 | Rp 151.701.943` di kanan.
       - Tabel hierarki Kegiatan → Rekening Belanja → Uraian dengan kolom `Total` dan kolom bulan (`Januari`, `Februari`, `Maret`, `April`, dst.).
     - **Mode 2: `Lihat grafik proporsi`** (`frame_03_69s.jpg`, `frame_04_92s.jpg`):
       - Grid **4 Kartu Grafik Donut (2 kolom × 2 baris)** lengkap dengan legenda warna, persentase, nominal rupiah, batas juknis, dan banner hijau `✓ Alokasi anggaran Anda sudah sesuai juknis`:
         1. *Proporsi Belanja berdasarkan Kegiatan* (Pemeliharaan Sarpras Maks. 20% vs Komponen Anggaran Lainnya)
         2. *Proporsi Antar Jenis Belanja Lainnya dari Total Pagu* (Honorarium, Barang, Jasa, Perjalanan Dinas, Modal Peralatan & Mesin, Modal Aset Tetap Lainnya)
         3. *Proporsi Anggaran Buku* (Penyediaan Buku Min. 10% vs Komponen Anggaran Lainnya)
         4. *Proporsi Anggaran Honor dari Total Pagu* (Pembayaran Honor Maks. 20%/40% vs Komponen Anggaran Lainnya)

### Target File di SiapBOSP:
- `resources/views/anggaran/show.blade.php`
- `resources/views/anggaran/review.blade.php`
- `app/Http/Controllers/RkasController.php`

---

## 4. Area Kerja 2: Penatausahaan, Perbarui Data BKU, & Modal Penerimaan Dana

### Bukti Visual (`Screenshot 2026-10-29 171058.png`, `171121.png`, `235223.png`–`235248.png`, `235522.png`, Video `04:25–04:50`)
1. **Halaman Utama Penatausahaan (`Penatausahaan`)**:
   - Satu kartu putih utama dengan **6 tab sumber dana** (`BOSP Reguler` s.d. `Sumber Dana Tambahan`).
   - Di dalam kartu terdapat daftar kartu tahunan (`2026`, `2025`, `2024`):
     - **Kartu Tahun Aktif (`BKU BOSP Reguler 2026`)**:
       - Header kartu: Ikon dokumen biru + judul **`BKU BOSP Reguler 2026`** + badge biru muda **`Aktif`**, subteks biru `Tutup semua bulan BKU paling lambat 31 Januari 2027`, dan di kanan 2 tombol putih berbingkai: **`🖨 Cetak`** dan **`Tambah Penerimaan Dana`**.
       - Isi kartu: **Grid 12 Kartu Bulan (4 kolom × 3 baris, `Januari` s.d. `Desember`)**:
         - Bulan selesai (`Januari`–`Juni`): Tanda `✓ Nama Bulan` + pil hijau **`Sudah Selesai`**.
         - Bulan berjalan (`Juli`): `Juli` + pil biru muda **`Draf`**.
         - Bulan belum dibuat (`Agustus`–`Desember`): Latar abu-abu + pil berbingkai **`Belum Dibuat`**.
       - Kaki kartu (kanan bawah): Tautan biru **`↻ Perbarui Data BKU 2026`**.
     - **Kartu Tahun Sebelumnya (`BKU BOSP Reguler 2025`)**:
       - Satu baris kartu abu-abu dengan badge hijau **`Sudah Selesai`** dan tombol **`🖨 Cetak`** di kanan.
     - **Kartu Tahun Lewat Batas (`BKU BOSP Reguler 2024`)**:
       - Satu baris kartu abu-abu dengan badge **`Nonaktif - Lewat Batas`**.
2. **Halaman `Perbarui Data BKU` (`frame_12_272s.jpg`, Video `04:32`)**:
   - Tetap memiliki sidebar kiri, dengan tautan `← Kembali ke Penatausahaan` di atas kartu.
   - Kartu putih berisi ilustrasi dokumen, judul **`Perbarui Data BKU BOSP Reguler 2026`**, deskripsi pemeriksaan akurasi perhitungan BKU, tombol **`Perbarui Data`** + subteks `"Perbarui Data" membutuhkan koneksi internet`, dan kotak status hijau:
     - `✓ Terakhir perbarui data: 29 Okt 2026`
     - **`Data dan perhitungan BKU sudah sesuai`**
     - `Hasil pembaruan tidak mempengaruhi data dan perhitungan BKU Anda.`
3. **Modal `Tambah Penerimaan Dana` (`Screenshot 2026-10-21 235522.png`)**:
   - Menampilkan kartu histori penerimaan per tahap (mis. `BOSP Reguler Tahap 1 2026 | 20 Jan 2026 | Rp 171.450.000` dan `BOSP Reguler Tahap 2 2026 | 6 Agt 2026 | Rp 171.450.000` lengkap dengan ikon hapus di kanan) serta kotak garis putus-putus **`+ Tambah Penerimaan Tahap Berikutnya`**.

### Target File di SiapBOSP:
- `resources/views/penatausahaan/index.blade.php`
- `app/Http/Controllers/PenatausahaanController.php`

---

## 5. Lembar BKU Bulanan (`BKU Juli 2026`) & Wizard Belanja 3 Langkah

### Bukti Visual (`Screenshot 2026-10-21 235645.png`–`235801.png`, Video `04:50–05:55`)
1. **Halaman Lembar BKU Bulanan (`BKU Juli 2026`, `235645.png`)**:
   - Tampil **Full-Width tanpa sidebar** dengan tautan `← Kembali ke Penatausahaan`.
   - **Header Kiri**: Judul **`BKU Juli 2026`** berdampingan dengan tombol `🗑 Hapus BKU`, serta subjudul `BOSP REGULER 2026`.
   - **Header Kanan**:
     - Banner abu-abu: `? Butuh panduan mengisi? Baca Panduan Selengkapnya`.
     - Deretan 4 tombol: **`+ Tambah Pembelanjaan`**, **`🔍 Cari`**, **`🖨 Cetak`**, dan tombol hitam **`Tutup BKU`** (dengan subteks `"Tutup BKU" membutuhkan koneksi internet` dan `✓ Tersimpan otomatis pada ...`).
   - **Dua Panel Kontrol Dana Berdampingan**:
     - **Panel Kiri (Latar Biru Muda `#eef6ff`)**:
       - Kiri atas: `TOTAL DANA TERSEDIA` → **`Rp 58.999.770`**.
       - Kanan atas: Tautan biru **`Tarik Tunai`** | **`Setor Tunai`**.
       - Bawah: 2 kotak putih berbingkai biru berdampingan: **`Nontunai | Rp 58.999.770`** dan **`Tunai | Rp 0`**.
     - **Panel Kanan (Bingkai Biru)**:
       - Judul: `ANGGARAN DIBELANJAKAN SAMPAI BULAN INI`.
       - Bawah: 3 kotak sejajar: **`Bisa Dibelanjakan ⓘ | Rp 47.053.213`**, **`Sudah Dibelanjakan ⓘ | Rp 112.450.230`**, dan **`Bisa Dianggarkan Ulang ⓘ | Rp 0`**.
   - **Tabel Transaksi Bulanan**:
     - Kolom: `ID`, `Tanggal`, `Kegiatan`, `Rekening Belanja`, `Jenis Transaksi`, `Anggaran`, `Dibelanjakan`, `Pajak Wajib Lapor`, `Aksi` (`⋯`).
2. **Modal Wizard 3 Langkah `Isi Detail Pembelanjaan` (`235656.png`–`235801.png`)**:
   - Berupa **Modal Dialog** di atas halaman BKU Bulanan dengan 3 tab indikator langkah:
     - **Langkah 1: `Detail Transaksi`** (`235656.png`–`235723.png`, `frame_14_317s.jpg`):
       - Kotak biru muda dengan checkbox: `Centang jika pembelanjaan ini dari SIPLah dan sudah menerima barang serta invoicenya`.
       - Input `Tanggal Transaksi` dan dropdown `Jenis Transaksi` (`Tunai` / `Nontunai` yang menampilkan teks `Saldo Tunai Rp ...` dan `Saldo Nontunai Rp ...` di sisi kanan opsi dropdown).
       - Checkbox: `Centang jika pembelanjaan ini tidak memiliki toko/badan usaha (perusahaan, PT, CV, UD, firma, dll)`:
         - **Tidak dicentang**: Input pencarian `Nama Toko/Badan Usaha`, `Alamat Toko/Badan Usaha`, `Nomor Telepon`, `NPWP Toko/Badan Usaha`, + checkbox tidak memiliki NPWP.
         - **Dicentang**: Berubah menjadi `Nama Orang Penerima Pembayaran`, `Alamat Orang Penerima Pembayaran (Opsional)`, `Nomor Telepon (Opsional)`, `NPWP/NIK Orang Penerima Pembayaran`, + checkbox tidak memiliki NPWP.
     - **Langkah 2: `Detail Barang/Jasa`** (`235733.png`–`235801.png`, `frame_15_340s.jpg`):
       - Baris atas: `Nomor Nota (Opsional)` dan `Tanggal Belanja/Nota`.
       - Kartu `Kegiatan 1`:
         - Input pencarian **`Kegiatan`** yang membuka dropdown tabel 3 kolom (`Kode | Program | Kegiatan`).
         - Input pencarian **`Rekening Belanja`** yang membuka dropdown tabel 3 kolom (`Kode | Jenis Belanja | Rekening Belanja`).
         - Setelah dipilih, muncul daftar **`Uraian`** dengan checkbox `Pilih semua uraian`, dan untuk tiap uraian yang dicentang tampil baris input `Jumlah` (dengan label merah `Maks. {vol}` di dalam input kanan) serta `Harga Satuan` (dengan label merah `Maks. Rp {harga}`), ditambah pita `Total Pembelanjaan | Rp ...` di bawahnya dan tombol `+ Tambah Kegiatan`.
     - **Langkah 3: `Perhitungan Pajak`**:
       - Konfirmasi pajak wajib lapor sebelum menyimpan transaksi.

### Target File di SiapBOSP:
- `resources/views/pembukuan/bku.blade.php`
- `resources/views/pengeluaran/belanja-form.blade.php`
- `app/Http/Controllers/PembukuanController.php`

---

## 6. Area Kerja 3: Halaman Arsip & Pratinjau Cetak Terpadu (`Mencetak RKAS` / `Mencetak BKU`)

### Bukti Visual (`Screenshot 2026-10-21 235301.png`–`235624.png`, Video `01:55–04:20`)
1. **Halaman Arsip (`235301.png`)**:
   - Judul `Arsip` dan subjudul deskripsi.
   - Satu kartu putih dengan **3 dropdown filter otomatis di kanan atas** (`Semua Format`, `Semua sumber dana`, `Semua` tahun `2026`–`2018`) tanpa tombol submit/reset.
   - Daftar kartu abu-abu dokumen yang telah disahkan/ditutup (`RKAS BOSP Reguler 2026`, `BKU Sumber Dana Lainnya 2025`, dll.) dengan tombol **`🖨 Cetak`** di sebelah kanan.
2. **Halaman Pratinjau Cetak Terpadu (`Mencetak RKAS 2026` & `Mencetak BKU 2026`, `235346.png`–`235624.png`, `frame_05_114s.jpg`–`frame_10_227s.jpg`)**:
   - Saat tombol **`🖨 Cetak`** diklik dari mana saja (Penganggaran, Detail RKAS, Penatausahaan, BKU Bulanan, atau Arsip), halaman berpindah ke layar **Full-Width tanpa sidebar** dengan tautan `← Kembali ke ...` di kiri atas dan banner `? Butuh panduan mengisi? Baca Panduan Selengkapnya` di kanan atas.
   - **Kontrol Pemilih di Atas**:
     - Untuk **RKAS (`Mencetak RKAS 2026`)**:
       - Dropdown **`Format RKAS`** (berisi: *Rincian Kertas Kerja (Tahunan)*, *Rincian Kertas Kerja (Tahapan)*, *Rincian Kertas Kerja (Triwulan)*, *Rincian Kertas Kerja (Bulanan)*, *Lembar Kertas Kerja (Tahapan)*, *Lembar Kertas Kerja (Triwulan)*, *Lembar Kertas Kerja (Unit 2.2)*, *Lembar Kertas Kerja (Unit 2.2.1)*, *Rincian RKAS (Tahunan)*).
       - Dropdown **`Periode`** (muncul di sebelah kanan `Format RKAS` saat pengguna memilih format Tahapan/Bulanan, mis. `Tahap 1`, `Tahap 2`, atau `Januari`–`Desember`).
     - Untuk **BKU (`Mencetak BKU 2026`)**:
       - Dropdown **`Format Laporan`** (berisi: *BKU*, *Bank*, *Tunai*, *Pajak*, *Panjar*, *Realisasi*, *Rekapitulasi Realisasi Penggunaan Dana BOSP*, *SPTJM*, *Register Penutupan Kas*, *Berita Acara Pemeriksaan Kas*).
       - Dropdown **`Periode`** (mis. `Tahap 1`–`Tahap 2` atau `Januari`–`Desember` serta opsi `Tahunan`).
   - **Jendela Penampil Dokumen (PDF Viewer Gelap)**:
     - Di bawah dropdown langsung tampil area viewer berwarna gelap (`#323639` / `#525659`) seperti Chromium PDF Viewer:
       - **Toolbar Viewer (`#323639`)**: Ikon toggle sidebar `☰`, nama berkas (`rka-tahap-output.pdf` / `bku-output.pdf`), indikator halaman `1 / 12`, kontrol zoom `- | 83% | +`, fit-to-page, rotasi, serta ikon **Unduh (`⬇`)** dan **Cetak (`🖨`)** di kanan atas.
       - **Sidebar Kiri Viewer**: Daftar *thumbnail* halaman bernomor (`1`, `2`, `3`, ...).
       - **Kanvas Tengah Viewer**: Lembar kertas putih dokumen resmi sesuai format dan periode yang dipilih.

### Target File di SiapBOSP:
- `resources/views/arsip/index.blade.php`
- `resources/views/laporan/index.blade.php`
- `resources/views/pembukuan/bku-cetak.blade.php`
- `app/Http/Controllers/LaporanArkasController.php`
- `app/Http/Controllers/PembukuanController.php`

---

## 7. Status Eksekusi (Selesai Diimplementasikan)

Seluruh tahapan peta jalan telah dieksekusi:

1. **[x] Tahap 1 — Kerangka & Navigasi (`resources/views/layouts/_shell.blade.php`, `_modal-penanggung-jawab.blade.php`)**:
   - Mode *Full-Width* otomatis (`fullScreen => true`, menyembunyikan sidebar & topbar secara visual) untuk halaman Detail RKAS, Review Kertas Kerja, Detail BKU Bulanan, dan Pratinjau Cetak.
   - Menyembunyikan *header* halaman bawaan (`hidePageHead => true`) pada ruang kerja utama (`Penganggaran`, `Penatausahaan`, `Arsip`).
   - Avatar topbar 2 huruf inisial dan Modal **`Data Penanggung Jawab`** saat item dropdown profil diklik.
2. **[x] Tahap 2 — Halaman Penganggaran, Detail RKAS, & Review Kertas Kerja (`resources/views/rkas/*`)**:
   - Kartu 3 tahun terakhir (`2026`, `2025`, `2024`) dengan 3 ikon aksi persegi (`Edit`, `Hapus`, `Cetak`), tab `Sumber Dana Tambahan` + modal `Berhasil Perbarui Data`.
   - Halaman Detail RKAS *full-width* dengan 12 tab bulan & tabel hierarki berwarna bertingkat (`#dbeafe`, `#e0f2fe`, `#dcfce7`).
   - Halaman Review Kertas Kerja *full-width* dengan switcher **`Lihat per tahapan`** dan **`Lihat grafik proporsi` (4 Donut Chart 2×2)**.
3. **[x] Tahap 3 — Halaman Penatausahaan, Lembar BKU Bulanan, & Wizard Belanja 3 Langkah (`resources/views/penatausahaan/index.blade.php`, `resources/views/pembukuan/bku.blade.php`)**:
   - Kartu BKU tahunan (`2026` dengan grid 12 bulan 4×3, `2025` Sudah Selesai, `2024` Nonaktif - Lewat Batas), sub-halaman `Perbarui Data BKU`, dan modal `Tambah Penerimaan Dana`.
   - Lembar BKU Bulanan *full-width* dengan 2 panel kontrol dana berdampingan (`TOTAL DANA TERSEDIA` + `Tarik/Setor Tunai` di kiri, `ANGGARAN DIBELANJAKAN SAMPAI BULAN INI` di kanan).
   - Modal Wizard 3 Langkah `Isi Detail Pembelanjaan` langsung di halaman BKU Bulanan.
4. **[x] Tahap 4 — Halaman Arsip & Pratinjau Cetak Terpadu (`resources/views/arsip/index.blade.php`, `resources/views/laporan/format.blade.php`)**:
   - Halaman Arsip 1 kartu putih dengan 3 dropdown filter di kanan atas dan kartu dokumen abu-abu (`#f5f5f5`).
   - Halaman viewer cetak *full-width* (`Mencetak RKAS` / `Mencetak BKU`) dengan dropdown `Format RKAS` / `Format Laporan` + `Periode` kondisional dan kerangka *PDF Viewer* gelap ber-thumbnail (`#323639` & `#525659`).
5. **[x] Tahap 5 — Pengujian & Build Aset**:
   - `npm run build`, `vendor/bin/pint --dirty --format agent`, dan suite pengujian PHPUnit.
