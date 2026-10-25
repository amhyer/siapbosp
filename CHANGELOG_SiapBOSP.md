# 📦 SiapBOSP — Catatan Perubahan & Inventaris File (CHANGELOG)

**Diperbarui:** 20 Oktober 2026 · **Versi kode terbaru:** `siapbosp-app-v23.zip` (hotfix halaman cetak di atas Fase 23)

> File ini merangkum SELURUH perubahan aplikasi SiapBOSP per fase, plus tautan semua artefak yang tersimpan di folder Google Drive ini.

---

## 🗂️ Inventaris file di folder Drive ini

| File | Isi |
|---|---|
| `BLUEPRINT_SiapBOSP.md` | Cetak biru hasil validasi (disetujui sebelum pengembangan) |
| `manual_bok_simakda_bos.pdf` | Manual acuan Simakda BOS 2023 Makassar |
| `siapbosp-app-v1.zip` … `v23.zip` | Kode sumber Laravel lengkap per fase (v23 = terbaru, sudah berisi folder `deploy/` + dokumen `DEPLOY.md/INSTALL.md/API.md`) |
| `CONTOH-LPJ-Tahap-1-2026-SDN-Mamajang.pdf` | Contoh LPJ **PDF asli sisi-server** (sampul, SPTB terbilang, BKU, K7, pajak) |
| `CONTOH-LPJ-Tahap-1-2026-SDN-Mamajang.xlsx` | Contoh LPJ **Excel multi-sheet** (Ringkasan/BKU/K7/Pajak) |
| `SiapBOSP_API.md` | Dokumentasi endpoint/rute aplikasi |
| `SiapBOSP_INSTALL.md` | Panduan pemasangan (Laravel 13 + PostgreSQL, `composer install`) |
| `SiapBOSP_DEPLOY.md` | Panduan deploy permanen: cara otomatis (`deploy/pasang.sh`, `perbarui.sh`) & manual (VPS) |

## 🧭 Ringkasan per fase

- **Fase 1–3 (v1–v3)** — Fondasi multi-tenant + PostgreSQL: master sekolah/tahap/rekening, login 3 peran (operator, kepsek, dinas), pencatatan penerimaan (pendapatan, ambil simpanan, mutasi tunai→bank) gaya Simakda.
- **Fase 4 (v4)** — RKAS-BOSP: penyusunan & revisi anggaran per rekening/komponen, status draft→disahkan.
- **Fase 5 (v5)** — Pengeluaran: belanja multi-rincian + pungut pajak (kode billing), setor pajak (NTPN), setor utang pajak; peringatan belanja vs RKAS (warning-only).
- **Fase 6 (v6)** — Pembukuan: BKU berjalan dengan saldo otomatis (SILPA dibawa), no BKU otomatis, tutup/buka buku per tahap (kepsek).
- **Fase 7 (v7)** — Pelaporan: Realisasi vs RKAS + CSV, LPJ per tahap versi cetak browser, paket ZIP CSV; notifikasi 🔔; log aktivitas; utilitas profil sekolah & penandatangan.
- **Fase 8 (v8)** — Panel Dinas: dashboard lintas sekolah (serapan, status), daftar sekolah, konsolidasi laporan, broadcast pengumuman, backup database 💾.
- **Fase 9 (v8)** — **Alur LPJ Sekolah↔Dinas**: kirim LPJ (wajib tutup buku) → antrean 📥 LPJ Masuk → tinjau dokumen asli → ✅ terima / ❌ tolak berkomentar → notifikasi dua arah + log; kirim ulang setelah ditolak. Dashboard Dinas v2 (🏆 peringkat serapan + 📈 belanja wilayah per tahap). Peringatan ⛔ belanja melebihi pagu.
- **Fase 10 (v9)** — **Ekspor LPJ Profesional**: (1) **PDF asli server-side (Dompdf)** — halaman sampul resmi, SPTB dengan **terbilang otomatis**, tanda tangan Kepsek/Bendahara, BKU, K7 rekap+rincian per rekening, register pajak; (2) **Excel .xlsx multi-sheet (PhpSpreadsheet)** — Ringkasan, BKU (freeze pane + saldo berjalan), K7, Pajak; (3) tombol unduh di panel Sekolah & Dinas.
- **Fase 11 (v10)** — **Manajemen Pengguna oleh Dinas**: buat akun operator/kepsek per sekolah, **impor pengguna massal via CSV**, halaman detail per sekolah (ringkasan data + aktivitas).
- **Fase 12 (v11)** — **Persetujuan RKAS Online oleh Dinas**: sekolah mengirim RKAS (status `kirim_dinas` → `dikirim_dinas`), Dinas menelaah, **sahkan ✅ / kirim revisi 🟠 berkomentar**, umpan balik dua arah + log; status akhir `disahkan`↔`revisi`.
- **Fase 13 (v12)** — **Ekspor RKAS Profesional**: PDF resmi A4 **landscape** (kop Dinas, grup komponen + subtotal, terbilang, blok tanda tangan Komite/Bendahara/Kepsek) dan Excel .xlsx dua sheet; tombol unduh di panel Sekolah & Dinas (`rkas/{id}/pdf|xlsx`, `dinas/rkas/{id}/pdf|xlsx`).
- **Fase 14 (v13)** — **LPJ Kumulatif + Master Rekening Dinas**: (1) LPJ mode **🔁 kumulatif** mengakumulasi semua tahap sejak Tahap 1 s/d tahap terpilih di tampilan/PDF/Excel dengan badge `KUMULATIF · Tahap 1 – Tahap N`, saldo awal tetap SILPA awal tahun; (2) halaman **🗂️ Master Rekening** Dinas: statistik, tabel rekening per komponen, **impor CSV sinkron-aman** (buat/perbarui/lewati) + unduh contoh berkas.
- **Fase 15 (v14)** — **Notifikasi Email Sungguhan**: surat HTML bermerek `[SiapBOSP]` (salam personal + tombol aksi) ikut terkirim pada 8 peristiwa — RKAS masuk/disahkan/revisi, LPJ masuk/diterima/ditolak, tahap dibuka–ditutup, pengumuman broadcast. Pengiriman **tahan-tanggal** (kegagalan SMTP hanya tercatat, alur utama tak terganggur); tombol **📨 Kirim Email Uji** di dashboard Dinas; driver default `log` (surat tercatat utuh di `storage/logs/laravel.log`) dengan panduan SMTP Gmail di `.env`.
- **Fase 16 (v15)** — **Paket Deploy Permanen ke VPS**: folder `deploy/` berisi empat berkas siap jalan — `pasang.sh` (pemasangan satu perintah: paket, DB, .env produksi, Nginx, HTTPS Certbot, ufw, cron backup harian, berkas kredensial), `perbarui.sh` (pembaruan zero-data-loss: backup DB otomatis → salin berkas tanpa menyentuh `.env`/`storage` → migrate → cache), `backup.sh` (pg_dump + arsip `storage/app`, rotasi retensi), `nginx-siapbosp.conf`. `DEPLOY.md` v2: cara otomatis & manual, daftar wajib pasca-pasang (ganti akun demo, SMTP sungguhan, APP_URL), alur update/rollback, tabel diagnosis masalah.
- **Fase 17 (v16)** — **Kelola Akun Penuh oleh Dinas**: di halaman Pengguna, tiap akun kini bisa **diubah** (nama/email/peran/sekolah), **reset sandi** (manual atau acak + **email pemberitahuan** berisi sandi baru), **dinonaktifkan/diaktifkan** (akun nonaktif otomatis ditolak saat login dengan pesan jelas), dan **dihapus**. Proteksi: tak bisa mengubah peran/menonaktifkan/menghapus **akun diri sendiri**, dan satu-satunya akun Dinas/admin tak dapat dihapus. Badge status AKTIF/NONAKTIF di tabel; kolom `is_active` ditambah via migrasi.
- **Fase 18 (v17)** — **Email Antrean (Queue)**: semua notifikasi email kini diproses latar belakang lewat `App\Jobs\KirimEmailNotif` (coba ulang otomatis 3× dengan jeda bertingkat; kegagalan permanen masuk `failed_jobs` lewat `queue:failed`/`queue:retry`) — aksi pengguna selesai dalam milidetik tanpa menunggu SMTP. Mode `QUEUE_CONNECTION=sync` tetap didukung otomatis (langsung kirim, nyaman untuk uji). Paket deploy diperkaya: unit systemd `deploy/siapbosp-worker.service` (dipasang & di-restart otomatis oleh `pasang.sh`/`perbarui.sh`), DEPLOY.md bertambah baris diagnosis antrean.
- **Fase 19 (v18)** — **Grafik & Drill-down Konsolidasi Wilayah (Dinas)**: halaman 🗂️ Konsolidasi kini memiliki **filter jenjang & kecamatan** (select + tombol Terapkan + ✖ bersihkan) dan **tiga grafik interaktif (Chart.js)** — 🍩 proporsi jenis belanja (Pegawai 5.1.01 / Barang & Jasa 5.1.02 / Modal 5.2 / Lainnya), 📈 belanja per bulan (agregat SQL `date_part`, 12 titik), 📊 pagu vs realisasi per jenjang. Semua grafik mengikuti filter yang aktif.
- **Fase 20 (v19)** — **Multi-Tahun Anggaran Penuh**: Dinas dapat **membuat paket tahun anggaran baru** (Tahap 1–5 sekaligus, idempoten — panggil kembali tidak menggandakan; validasi rentang tahun 2020 s/d 3 tahun ke depan); tahun baru langsung tersedia di selector tahun (rentang navbar kini dinamis mengikuti tahun terjauh) dan tahun aktif sesi otomatis pindah. Semua kueri laporan/kas memang sudah kunci-by-tahun sejak awal, sehingga data per tahun benar-benar terpisah.
- **Fase 21 (v20)** — **Tanda Tangan Basah Digital**: di Utility → Penandatangan kini bisa **unggah gambar tanda tangan** (PNG/JPG ≤ 1 MB; otomatis diperkecil tinggi ≤ 320 px, disimpan privat di `storage/app/private/ttd/`). PDF **RKAS & LPJ otomatis menyematkan gambar itu** di blok tanda tangan (via data-URI base64 — aman untuk Dompdf) menggantikan ruang kosong; tombol hapus TTD kembali ke mode spasi kosong. Kolom `ttd_path` via migrasi.
- **Fase 22 (v21)** — **Audit Keamanan Lanjutan**: (1) **throttle login** — 5× kata sandi salah dalam 5 menit (per email + IP) → akun diblokir sementara dengan pesan sisa waktu; (2) pencatatan peristiwa autentikasi (`auth-ok` / `auth-gagal` / `auth-block`) ke jejak audit dengan IP; (3) halaman baru **🔐 Pusat Keamanan** untuk Dinas: statistik 24 jam (login sukses / gagal / email unik aktif), tabel riwayat 50/halaman berbadge MASUK/GAGAL/BLOKIR, dan filter email.
- **Fase 23 (v22)** — **Kertas Kerja Pajak Triwulan**: laporan baru di 🖨️ Laporan → 🧾 Pajak Triwulan: per TW (I–IV) TA aktif — rekap per jenis pajak **per bulan** (dipungut / disetor / sisa), kartu ringkasan (dipungut, disetor, **sisa kewajiban akhir triwulan** kumulatif, jumlah billing & setoran), tabel **rincian kode billing** (status LUNAS/BELUM) dan **rincian NTPN**, plus **ekspor CSV** satu klik. Ideal untuk merapikan e-Bupot & lembar SSPT per 3 bulan.
- **Perbaikan (v23)** — **Halaman cetak tidak lagi telanjang**: tiga halaman cetak (📦 LPJ per Tahap, 🧾 Kwitansi Belanja, 📒 Cetak BKU Bulanan) kini dibungkus layout utama — **sidebar & menu tetap tampil di layar saat mempersiapkan cetak**; tidak ada lagi halaman kosong tanpa navigasi. Saat tombol 🖨️ Cetak ditekan, sidebar/topbar otomatis **tersembunyi hanya pada hasil cetakan** (@media print, @page A4 1.4 cm) — print preview bersih dokumen saja. Sekaligus memperbaiki bug laten: konten halaman ber-layout sempat ikut tersembunyi saat dicetak browser.
- **Pengerasan Lanjutan R-01…R-12 (audit 24 Okt 2026, tahap 2)** — menindaklanjuti §5 [`docs/AUDIT-KEAMANAN.md`](docs/AUDIT-KEAMANAN.md): (R-01) kebijakan sandi baru minimal 10 karakter (huruf+angka, menolak sandi umum/nama/email) di semua jalur; impor CSV tidak lagi memakai sandi bawaan `password` (sandi acak + `must_change_password` sehingga wajib ganti saat login pertama); (R-02) token API berbatas waktu (`API_TOKEN_UMUR_HARI`, default 180 hari), jejak pemakaian, dan tombol Cabut Token; (R-03) `X-Frame-Options: SAMEORIGIN` + CSP `frame-ancestors 'self'`; (R-04) pembatas laju unggahan/impor (`unggah`, `unggah-api`); (R-05) pengumuman Dinas dibatasi 5/jam, hanya akun aktif, dengan batas penerima; (R-06) `.env.example` `APP_DEBUG=false` + peringatan log bila produksi masih debug / cookie sesi belum `secure`; (R-07) `TRUSTED_HOSTS` via `trustHosts`; (R-08) `sync_states` tidak tampil di server pusat; (R-09) pemisahan peran: `dinas` hanya mengelola `operator`/`kepsek`, akun `dinas`/`superadmin` hanya oleh superadmin (termasuk lewat impor CSV); (R-10) backup ZIP dienkripsi AES-256 bila `BACKUP_PASSWORD` diisi, berkas `0600`; (R-11) backup SQL hanya untuk PostgreSQL dengan pesan galat bersih; (R-12) jejak audit *append-only* + salinan `storage/logs/audit.log`, dan reset data sekolah tidak lagi menghapus log. 12 uji baru di `PerbaikanLanjutanTest` (total 35 uji regresi).

## 🔑 Kredensial demo

| Aplikasi | Login | Password |
|---|---|---|
| Sekolah (Operator/Bendahara) | `operator@sekolah.demo` | `password` |
| Sekolah (Kepala Sekolah) | `kepsek@sekolah.demo` | `password` |
| Dinas Pendidikan | `dinas@siapbosp.id` | `password` |

## ⚙️ Catatan pemasangan (v22)

1. `composer install` wajib — paket: **dompdf/dompdf ^3.1**, **phpoffice/phpspreadsheet ^5.10**.
2. Ekstensi PHP yang dibutuhkan: `pgsql`, `mbstring`, `xml`, `zip`, **`gd`**, `curl`.
3. Database: PostgreSQL; migrasi + seeder demo: `php artisan migrate:fresh --seed`.
4. **Email** (Fase 15): default `MAIL_MAILER=log` — surat tercatat di `storage/logs/laravel.log` (aman untuk demo). Untuk kirim sungguhan, set `MAIL_MAILER=smtp` + kredensial SMTP di `.env` (panduan contoh Gmail ada di berkas `.env`); setel juga `APP_URL` ke alamat publik aplikasi agar tautan di email benar.
5. Seluruh fitur Fase 1–23 tetap utuh; tidak ada perubahan skema yang merusak data lama. Migrasi baru Fase 21 (`ttd_path` di `penandatangans`) **aman dilewati tanpa data hilang** — jalankan `php artisan migrate` biasa.
6. **Deploy** (Fase 16): gunakan `deploy/pasang.sh` untuk pemasangan satu perintah di Ubuntu 24.04 dan `deploy/perbarui.sh` untuk pembaruan zero-data-loss — rincian di `DEPLOY.md`. Berkas TTD basah hidup di `storage/app/private/ttd/` → otomatis ikut `deploy/backup.sh` (arsip `storage/app`) dan tak disentuh `perbarui.sh`.
