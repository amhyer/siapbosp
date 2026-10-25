# R-03 — Inventaris & migrasi handler event inline

| | |
|---|---|
| **Tanggal** | 25 Oktober 2026 |
| **Issue** | [#6 — R-03: Remove inline event handlers from CSP](https://github.com/amhyer/siapbosp/issues/6) |
| **Commit dasar** | `bac62c2` (`main`, setelah PR #5 dan PR #9) |
| **Lingkup** | `resources/views/**/*.blade.php`, `resources/js/app.js`, `app/Http/Middleware/KeamananHeader.php` |
| **Sifat dokumen** | Inventaris statis + catatan migrasi. Bukan hasil uji penetrasi. |

## 1. Ringkasan

| Sebelum | Sesudah |
|---|---|
| 80 atribut handler inline di 37 berkas Blade | 0 |
| `script-src-attr 'unsafe-inline'` | `script-src-attr 'none'` |
| `script-src 'self' 'nonce-…' https://cdn.jsdelivr.net` | tidak berubah (nonce & CDN Chart.js tetap) |

Rincian jenis handler yang ditemukan:

| Jenis | Jumlah | Perilaku yang dipertahankan |
|---|---|---|
| `onchange` | 18 | 12× filter langsung mengirim form (`this.form.submit()`), 6× logika kalkulator/pilihan (SIPLah, saldo, tarif pajak, total, SSH, opasitas biaya) |
| `onsubmit` | 21 | 20× `confirm()` sebelum kirim form, 1× menonaktifkan tombol saat form tarik ARKAS dikirim |
| `onclick` | 35 | 11× `window.print()`, 2× `confirm()` pada tautan/tombol, 3× buka/tutup sidebar & grup menu, 19× aksi kalkulator/tabel |
| `oninput` | 6 | 6× hitung ulang total (nilai belanja, harga, volume bulan) |

Angka tersebut berasal dari pemindaian berikut (komentar Blade dikecualikan karena tidak
pernah dikirim ke browser):

```bash
grep -rEn '\bon(click|change|submit|input|load|error|focus|blur)\s*=' resources/views --include=*.blade.php
```

## 2. Rencana migrasi & pola yang dipakai

Tidak ada paket CSP baru dan tidak ada hash untuk handler. Semua perilaku dipindah ke
JavaScript non-inline yang sudah sesuai arsitektur aplikasi (nonce per respons tetap
dipertahankan untuk blok `<script>` yang sah):

| Lapisan | Berkas | Isi |
|---|---|---|
| Kerangka aplikasi (aset Vite) | `resources/js/app.js` | Delegasi klik untuk `data-sidebar-buka`, `data-sidebar-tutup`, `data-grup` |
| Lapisan interaksi bersama | `resources/views/layouts/_interaksi.blade.php` *(baru, nonce)* | `data-konfirmasi` (submit + klik), `data-kirim-otomatis`, `data-cetak-halaman`, `data-tombol-sibuk`. Dimuat oleh `layouts.app` **dan** `layouts.print` |
| Skrip halaman (nonce, sudah ada) | `penerimaan/pendapatan`, `pengeluaran/belanja-form`, `pengeluaran/setor-pajak`, `rkas/create`, `rkas/salin-arkas`, `rkas/show`, `perencanaan/index` | Delegasi event untuk baris tabel yang dibuat dari `<template>` |

Kontrak atribut `data-*` (semua diikat lewat `addEventListener`, tanpa handler atribut):

| Atribut | Dipasang pada | Efek |
|---|---|---|
| `data-konfirmasi="pesan"` | `<form>`, `<a>`, tombol submit | `window.confirm()`; batal ⇒ aksi dibatalkan (`preventDefault`) |
| `data-kirim-otomatis` | `<select>`, `<input>` | `change` ⇒ `form.submit()` |
| `data-cetak-halaman` | `<button type="button">` | klik ⇒ `window.print()` |
| `data-tombol-sibuk="teks"` | `<form>` | submit ⇒ tombol submit dinonaktifkan + teks diganti |
| `data-sidebar-buka` / `data-sidebar-tutup` / `data-grup` | tombol di kerangka | buka/tutup drawer, buka/tutup grup menu |
| `data-tambah-rincian`, `data-hapus-rincian`, `data-hapus-baris`, `data-tambah-pajak`, `data-rekomendasi-pajak`, `data-tambah-baris`, `data-bawa-rekomendasi`, `data-tab-review`, `data-salin`, `data-rkt-butuh` | tombol/select di halaman terkait | aksi kalkulator & tabel (lihat §3) |

## 3. Inventaris per berkas

Kolom **Migrasi** menyebut pengganti yang dipakai. Nomor baris mengacu pada commit `bac62c2`.

| Berkas | Baris | Handler | Perilaku yang dipertahankan | Migrasi |
|---|---|---|---|---|
| `arsip/pajak.blade.php` | 9 | `onchange` | filter tahun memuat ulang halaman | `data-kirim-otomatis` |
| `arsip/rkas.blade.php` | 9 | `onchange` | filter tahun memuat ulang halaman | `data-kirim-otomatis` |
| `dinas/aktivitas.blade.php` | 18 | `onchange` | filter sekolah memuat ulang halaman | `data-kirim-otomatis` |
| `dinas/lpj.blade.php` | 37 | `onsubmit` | konfirmasi terima LPJ | `data-konfirmasi` |
| `dinas/pengguna.blade.php` | 106 | `onsubmit` | konfirmasi hapus akun (menyebut email) | `data-konfirmasi` |
| `dinas/pengumuman.blade.php` | 18 | `onsubmit` | konfirmasi hapus pengumuman | `data-konfirmasi` |
| `dinas/sekolah.blade.php` | 41 | `onsubmit` | konfirmasi hapus **seluruh** data sekolah (menyebut nama) | `data-konfirmasi` |
| `dinas/tahap.blade.php` | 5 | `onsubmit` | konfirmasi buat paket tahap | `data-konfirmasi` |
| `hpp/index.blade.php` | 9, 14 | `onchange` | filter sumber & status memuat ulang halaman | `data-kirim-otomatis` |
| `laporan-arkas/_header.blade.php` | 12 | `onchange` | filter bulan laporan ARKAS | `data-kirim-otomatis` |
| `laporan-arkas/penutupan-kas-cetak.blade.php` | 7 | `onclick` | cetak register penutupan kas | `data-cetak-halaman` |
| `laporan/lpj-cetak.blade.php` | 16, 54 | `onclick` | cetak dokumen LPJ | `data-cetak-halaman` |
| `laporan/lpj-cetak.blade.php` | 18 | `onsubmit` | konfirmasi terima LPJ | `data-konfirmasi` |
| `laporan/lpj-cetak.blade.php` | 44 | `onchange` | ganti tahap LPJ | `data-kirim-otomatis` |
| `laporan/tahap.blade.php` | 7 | `onchange` | ganti tahap | `data-kirim-otomatis` |
| `laporan/tahap.blade.php` | 11 | `onclick` | cetak LPJ tahap | `data-cetak-halaman` |
| `laporan/tahap.blade.php` | 20, 25, 40 | `onsubmit` | konfirmasi buka/tutup buku tahap, kirim LPJ ke Dinas | `data-konfirmasi` |
| `layouts/app.blade.php` | 148, 208 | `onclick` | tutup/buka drawer sidebar | `data-sidebar-tutup`, `data-sidebar-buka` (`app.js`) |
| `layouts/app.blade.php` | 157 | `onclick` | buka/tutup grup menu + `aria-expanded` | `data-grup` (`app.js`) |
| `layouts/app.blade.php` | 187 | `onclick` | konfirmasi unduh backup database | `data-konfirmasi` |
| `layouts/app.blade.php` | 255 | `onchange` | ganti tahun anggaran (kirim form) | `data-kirim-otomatis` |
| `pembukuan/bku-cetak.blade.php` | 18 | `onclick` | cetak BKU | `data-cetak-halaman` |
| `pembukuan/bku.blade.php` | 27 | `onchange` | filter bulan BKU | `data-kirim-otomatis` |
| `pembukuan/bku.blade.php` | 35 | `onsubmit` | konfirmasi tutup BKU (menyebut bulan/tahun) | `data-konfirmasi` |
| `pembukuan/buku-pembantu.blade.php` | 14 | `onclick` | cetak buku pembantu | `data-cetak-halaman` |
| `pembukuan/register.blade.php` | 13, 16 | `onchange`, `onclick` | filter bulan register, cetak register | `data-kirim-otomatis`, `data-cetak-halaman` |
| `pembukuan/rekap-realisasi.blade.php` | 10 | `onclick` | cetak rekap realisasi | `data-cetak-halaman` |
| `pembukuan/sptjm.blade.php` | 5 | `onclick` | cetak SPTJM | `data-cetak-halaman` |
| `penerimaan/ambil.blade.php` | 23 | `onsubmit` | konfirmasi hapus transaksi tarik tunai | `data-konfirmasi` |
| `penerimaan/mutasi.blade.php` | 23 | `onsubmit` | konfirmasi hapus transaksi setor tunai | `data-konfirmasi` |
| `penerimaan/pendapatan.blade.php` | 17 | `onclick` | tambah baris rincian pendapatan | `data-tambah-rincian` |
| `penerimaan/pendapatan.blade.php` | 62 | `onsubmit` | konfirmasi hapus pendapatan | `data-konfirmasi` |
| `penerimaan/pendapatan.blade.php` | 75 | `oninput` | hitung total rincian saat nilai diisi | delegasi `input` pada `.nilai` |
| `penerimaan/pendapatan.blade.php` | 76 | `onclick` | hapus baris rincian + hitung ulang | `data-hapus-rincian` |
| `pengaturan/log.blade.php` | 9 | `onchange` | filter jenis aksi log | `data-kirim-otomatis` |
| `pengeluaran/belanja-form.blade.php` | 19, 34 | `onchange` | tampilkan kotak SIPLah; tampilkan saldo metode bayar | `addEventListener('change')` |
| `pengeluaran/belanja-form.blade.php` | 87, 103, 104 | `onclick` | tambah rincian, hitung rekomendasi pajak (fetch), tambah pajak manual | `data-tambah-rincian`, `data-rekomendasi-pajak`, `data-tambah-pajak` |
| `pengeluaran/belanja-form.blade.php` | 129, 136 | `oninput` | hitung total belanja; hitung nilai pajak per baris | delegasi `input` pada `.nR` / `.trf` |
| `pengeluaran/belanja-form.blade.php` | 130 | `onclick` | hapus rincian belanja + hitung ulang | `data-hapus-rincian` |
| `pengeluaran/belanja-form.blade.php` | 134 | `onchange` | isi tarif pajak otomatis per jenis | delegasi `change` pada `.pjk` |
| `pengeluaran/belanja-form.blade.php` | 139 | `onclick` | hapus baris pajak (**tanpa** hitung ulang) | `data-hapus-baris` |
| `pengeluaran/belanja-show.blade.php` | 40 | `onsubmit` | konfirmasi hapus lampiran | `data-konfirmasi` |
| `pengeluaran/belanja.blade.php` | 48 | `onsubmit` | konfirmasi hapus belanja | `data-konfirmasi` |
| `pengeluaran/kwitansi.blade.php` | 10 | `onclick` | cetak kwitansi | `data-cetak-halaman` |
| `pengeluaran/setor-pajak.blade.php` | 20 | `onchange` | hitung total pajak tercentang | delegasi `change` pada `input[name="tax_id[]"]` |
| `pengeluaran/setor-pajak.blade.php` | 48 | `onclick` | tambah rincian setor utang pajak | `data-tambah-rincian` |
| `pengeluaran/setor-pajak.blade.php` | 72 | `onclick` | hapus baris rincian setor utang | `data-hapus-baris` |
| `pengeluaran/spj.blade.php` | 41 | `onclick` | cetak paket SPJ | `data-cetak-halaman` |
| `perencanaan/index.blade.php` | 157 | `onchange` | redupkan blok biaya bila "Tanpa Biaya" | `data-rkt-butuh` + delegasi `change` |
| `rkas/create.blade.php` | 32 | `onclick` | bawa satu baris rekomendasi realisasi (data JSON per baris) | `data-bawa-rekomendasi='@json($rc)'`, dibaca `JSON.parse` |
| `rkas/create.blade.php` | 102 | `onclick` | tambah baris kegiatan | `data-tambah-baris` |
| `rkas/create.blade.php` | 142 | `onchange` | isi uraian/satuan/harga dari referensi SSH | delegasi `change` pada `.sshSel` |
| `rkas/create.blade.php` | 156 | `oninput` | sinkron qty ⇄ volume bulan | delegasi `input` pada `.qty` |
| `rkas/create.blade.php` | 157 | `oninput` | hitung total, sisa pagu, proporsi tahap | delegasi `input` pada `.hrg` |
| `rkas/create.blade.php` | 163 | `oninput` | sinkron volume bulan ⇒ qty | delegasi `input` pada `.blnInp` |
| `rkas/create.blade.php` | 169 | `onclick` | hapus baris kegiatan + reindex bulan | `data-hapus-baris` |
| `rkas/salin-arkas.blade.php` | 51, 55, 59, 63 | `onclick` | salin kode kegiatan/rekening/uraian/harga ke clipboard | `data-salin="…"` (nilai di-escape Blade, bukan lagi literal JS) |
| `rkas/show.blade.php` | 17 | `onsubmit` | konfirmasi sahkan RKAS | `data-konfirmasi` |
| `rkas/show.blade.php` | 57 | `onclick` | konfirmasi kirim RKAS ke Dinas (tombol submit bernama `aksi`) | `data-konfirmasi` pada tombol; batal ⇒ submit dicegah |
| `rkas/show.blade.php` | 77, 78, 79 | `onclick` | ganti tab review (proporsi/tahapan/tahunan) | `data-tab-review` |
| `utility/hapus-data.blade.php` | 8 | `onsubmit` | konfirmasi hapus seluruh data | `data-konfirmasi` |
| `utility/penandatangan.blade.php` | 43 | `onsubmit` | konfirmasi hapus penandatangan | `data-konfirmasi` |
| `utility/profil.blade.php` | 137 | `onsubmit` | nonaktifkan tombol + ganti teks saat tarik ARKAS diproses | `data-tombol-sibuk` |
| `utility/profil.blade.php` | 192, 197 | `onsubmit` | konfirmasi buat ulang / cabut token API | `data-konfirmasi` |

### Catatan semantik yang dijaga

1. **Batal konfirmasi benar-benar menghentikan aksi.** `onsubmit="return confirm(...)"` lama
   mencegah pengiriman form; penggantinya `preventDefault()` pada event `submit`. Untuk
   tombol submit yang punya `name`/`value` (mis. `aksi=kirim_dinas` di `rkas/show`),
   konfirmasi dipasang pada tombol, bukan form, sehingga nilai tombol tetap terkirim.
2. **Hapus baris yang tidak menghitung ulang tetap tidak menghitung ulang.**
   `belanja-form` baris pajak (`tplP`) dulu hanya `this.closest('tr').remove()`. Migrasinya
   memakai `data-hapus-baris` (tanpa `hitung()`), sedangkan rincian belanja memakai
   `data-hapus-rincian` (hapus + `hitung()`), persis seperti sebelumnya — supaya nilai pajak
   yang sudah diisi pengguna tidak tertimpa.
3. **Baris dari `<template>` tetap berfungsi.** Semua listener untuk tabel dinamis dipasang
   di `document` (delegasi), jadi baris hasil `cloneNode(true)` ikut tertangani tanpa
   atribut handler.
4. **Data per baris tidak lagi berupa literal JS.** `bawaRekomendasi(@json($rc))` diganti
   atribut `data-bawa-rekomendasi='@json($rc)'`, dan `salinTeks('{{ … }}')` diganti
   `data-salin="{{ … }}"` sehingga escaping HTML standar Blade yang berlaku.
5. **Tombol cetak diberi `type="button"`.** Sebelumnya `<button onclick="window.print()">`
   mengandalkan tipe bawaan `submit`; sekarang eksplisit agar tidak mengirim form induk.

## 4. CSP

`app/Http/Middleware/KeamananHeader.php` mengirim `script-src-attr 'none'` (sebelumnya
`'unsafe-inline'`). `script-src` tetap memakai nonce acak per respons + `'self'` +
`https://cdn.jsdelivr.net` (Chart.js di dashboard). `style-src` **tidak** diubah: masih ada
banyak atribut `style="…"` dan blok `<style>` di layout, sehingga `'unsafe-inline'` untuk
style tetap diperlukan dan berada di luar lingkup R-03.

## 5. Di luar cakupan (tidak diubah)

| Objek | Alasan |
|---|---|
| `public/pendamping-arkas.html`, `ARKAS4-Pendamping-Sekolah.html` | Halaman statis dokumentasi, bukan keluaran Blade. Disajikan langsung oleh web server sehingga **tidak melewati middleware Laravel** dan tidak menerima header CSP. Halaman ini juga memuat blok `<script>` inline tanpa nonce, jadi bila suatu saat disajikan lewat Laravel, keduanya perlu dimigrasi tersendiri. |
| `agen-arkas/gui/resources/app.js` | Aplikasi desktop Neutralino terpisah; memakai properti `el.onclick = …` (bukan atribut skrip), yang tidak dibatasi CSP. |
| `resources/views/layouts/_cetak-preview.blade.php` | Sudah memakai nonce + `addEventListener` sejak sebelumnya; tidak memuat handler atribut. |

## 6. Verifikasi

| Pemeriksaan | Alat | Hasil |
|---|---|---|
| Pemindaian ulang handler atribut di seluruh view | skrip regex yang sama dengan `CspHandlerInlineTest` | commit dasar `bac62c2` = **80**, sesudah migrasi = **0** |
| Sintaks JavaScript | `node --check` pada `resources/js/app.js` + semua blok `<script>` nonce di view | 13 blok OK (dashboard tidak diubah; stub `@php` pada harness membuat blok chart tak terparse — bukan akibat migrasi) |
| Perilaku lapisan interaksi | jsdom 30 (di luar repo) | 64 pemeriksaan lulus: konfirmasi form/tautan/tombol (termasuk tombol submit bernama yang batal ⇒ form tidak terkirim), `data-kirim-otomatis`, `data-cetak-halaman`, `data-tombol-sibuk`, sidebar & grup menu, serta halaman pendapatan, belanja-form, setor-pajak, rkas/create, salin-arkas, rkas/show, dan perencanaan |
| Sintaks PHP | `php -l` (PHP 8.5 WebAssembly) + parser `php-parser` | `KeamananHeader.php` dan `CspHandlerInlineTest.php` tanpa kesalahan sintaks |
| Regresi otomatis di CI | `php artisan test` (SQLite + PostgreSQL 16, workflow `tests.yml`) | **belum dijalankan di lingkungan ini** — tidak ada biner PHP native, `vendor/` tidak ada, dan Packagist tidak dapat dijangkau; lihat laporan akhir |

Regresi PHPUnit baru: `tests/Feature/CspHandlerInlineTest.php` — memeriksa header CSP,
memindai semua berkas Blade, memeriksa markup ter-render beberapa halaman kunci, dan
memastikan lapisan interaksi pengganti ikut dimuat. Uji klik di browser sungguhan
(dialog konfirmasi asli, dialog cetak, izin clipboard) tetap perlu dilakukan saat
staging karena lingkungan kerja ini tidak memiliki browser.
