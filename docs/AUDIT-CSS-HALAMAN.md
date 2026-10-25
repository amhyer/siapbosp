# Audit Pemuatan CSS per Halaman

Tanggal: 2026-10-25 · Branch: `arena/eb95f7ef-siapbosp` · Lingkup: seluruh 97 berkas
Blade di `resources/views` (69 halaman `layouts.app`, 7 `layouts.print`,
7 `layouts.error`, 1 `layouts.guest`, sisanya partial/layout/Dompdf/mail).

Dokumen ini menjawab satu pertanyaan: **apakah setiap halaman benar-benar
mendapat CSS untuk kelas yang dipakainya?** Metodenya analisis statis — tidak
ada verifikasi visual, karena lingkungan kerja tidak punya runtime PHP
(`vendor/` tidak terpasang) maupun browser.

## 1. Bagaimana CSS dimuat

| Jalur | Pemicu | Sumber gaya | Halaman |
|---|---|---|---|
| A. Aset build | `public/build/manifest.json` atau `public/hot` ada | `@vite(['resources/css/app.css','resources/js/app.js'])` | 69 halaman `layouts.app`, 1 `layouts.guest` |
| B. Darurat (tanpa build) | manifest & `hot` tidak ada | `/tailwind.js` (build Tailwind di browser) + `layouts/_gaya-darurat.blade.php` | halaman yang sama dengan A |
| C. Cetak mandiri | selalu | blok `<style>` di `layouts/print.blade.php` (+ `<style>` lokal view) | 7 halaman `layouts.print` |
| D. Inline penuh | selalu | CSS di dalam view itu sendiri | 7 halaman `layouts.error`, `emails/notifikasi`, `laporan/lpj-pdf`, `rkas/pdf` (Dompdf) |

Jalur A adalah yang dipakai produksi bila `npm run build` dijalankan
(`public/build/` **tidak** ikut repository). Jalur B adalah jaring pengaman
untuk instalasi yang lupa build. Jalur C sengaja tidak memakai `@vite` supaya
dialog cetak tetap tampil benar walau aset build hilang.

## 2. Inventaris kelas: 577 dipakai, 28 tanpa aturan di CSS build

Seluruh token `class="…"` diekstrak dari view (mengabaikan `{{ }}` dan
direktif Blade), lalu diuji keberadaannya sebagai selektor di
`public/build/assets/app-*.css` (escape Tailwind seperti `.w-2\/3` dibuka
lebih dulu). 549 token punya aturan; 28 sisanya **bukan** utility Tailwind
yang salah ketik, melainkan:

| Kategori | Kelas | Disediakan oleh |
|---|---|---|
| Dokumen resmi di kerangka aplikasi | `dokumen`, `kop`, `pg`, `ttd`, `ttd-grid` | blok `<style>` kedua `layouts/app.blade.php`, dalam scope `.dokumen` |
| Cetak mandiri | `kop`, `pg`, `ttd`, `ttd-grid` | blok `<style>` `layouts/print.blade.php` |
| Gaya lokal view | `isi`, `judul`, `blockinfo`, `blockinfo-bordered`, `ttd3` | `<style>` milik `pengeluaran/spj.blade.php` |
| Gaya lokal layout | `code` | `<style>` milik `layouts/error.blade.php` |
| Penanda hook JavaScript (bukan kelas visual) | `blnInp`, `hrg`, `jml`, `nP`, `nR`, `nilai`, `nilp`, `pjk`, `qty`, `rekBelanja`, `rekSel`, `rkt-biaya`, `satInp`, `sshSel`, `sumberRow`, `trf`, `uraianInp` | tidak perlu CSS — dipakai `querySelector('.…')` di skrip view (`rkas/create`, `pengeluaran/belanja-form`, `pengeluaran/setor-pajak`, `penerimaan/pendapatan`, `perencanaan/index`) |

Tidak ada satu pun halaman yang bergantung pada utility Tailwind yang hilang
dari build. Yang bermasalah ada di **jalur B dan C**, bukan di aset build.

## 3. Temuan dan perbaikan

### F-1 Halaman login kehilangan seluruh kelas komponen pada jalur darurat

`layouts/guest.blade.php` memuat `/tailwind.js` tetapi **tidak** memuat satu pun
kelas komponen `resources/css/app.css` (`.lbl`, `.btn`, `.btn-sm`, `.inp`).
Halaman login memakai `class="inp"` dan `class="lbl"`, jadi pada instalasi tanpa
`public/build` formulir login tampil tanpa border, padding, dan label yang
terbaca — halaman pertama yang dilihat pengguna.

Perbaikan: gaya komponen darurat dipindah dari `layouts/app` ke partial baru
`resources/views/layouts/_gaya-darurat.blade.php`, lalu di-`@include` oleh
`layouts/app` **dan** `layouts/guest` pada cabang tanpa build.

### F-2 Gaya darurat hanya menutup 9 dari 32 kelas komponen

Blok lama (`layouts/app`) hanya berisi 10 aturan: `body`, `.inp`, `.btn`,
`.btn2`, `.card`, `.tbl`, `.tbl th`, `.tbl td`, `.num`, `.badge`. `app.css`
mendefinisikan 32 kelas komponen; yang tidak punya padanan darurat antara lain
`.lbl`, `.inp-sm`, `.btn-sm`, `.btn-danger`, `.card-title`, `.tbl-wrap`,
`.bdan`, `.bdel`, `.navw` beserta `.on/.chev/.menu-btn/.sub`, `.alert`
dengan `.alert-ok/-warn/-err`, `.empty`, `.page-head`, `.page-desc`,
`.toolbar`, dan `.nonprint` (aturan `@media print`). Akibatnya sidebar, label
formulir, tombol kecil, pesan flash, dan keadaan kosong tabel berubah bentuk.

Perbaikan: partial baru menulis ulang **seluruh** kelas komponen `app.css`
sebagai CSS biasa (warna literal, tanpa `@apply`). Utility Tailwind tetap
datang dari `/tailwind.js`.

### F-3 Halaman cetak `pengeluaran/spj` memakai utility yang tak dimuat

`layouts.print` tidak memuat Tailwind, hanya blok kompatibilitas manual. Isi
`spj.blade.php` memakai `badge`, `bg-emerald-100`, `bg-slate-100`,
`text-emerald-700`, `text-slate-600`, dan `text-[10px]` — pada dokumen cetak
keenam kelas itu tidak beraturan, jadi penanda "Wajib dilampirkan" /
"Dikecualikan" tampil sebagai teks polos tanpa latar.

Perbaikan: lima kelas pertama ditambahkan ke blok kompatibilitas
`layouts/print.blade.php`; `.text-\[10px\]` (kelas ukuran arbitrer) juga,
dengan komentar agar penambahan berikutnya mengikuti pola yang sama.

### F-4 `npm run build` tidak dijelaskan sebagai wajib

Panduan deploy sudah menjalankan `npm ci && npm run build`, tetapi tidak
menyebut akibatnya bila dilewati. Ditambahkan catatan di `SiapBOSP_DEPLOY.md`
(§2) beserta cara memeriksa (`ls public/build/manifest.json`) dan rujukan ke
dokumen ini.

## 4. Regresi otomatis: `tests/Feature/CssHalamanTest.php`

Empat tes statis (tanpa browser, tanpa database) mengunci hasil di atas:

1. `test_setiap_halaman_memakai_layout_beraset` — setiap view non-layout,
   non-partial, non-Dompdf/mail harus `@extends('layouts.…')`. Gagal bila ada
   halaman baru yang lupa memasang layout.
2. `test_layout_memuat_aset_css_dengan_jalur_fallback` — `layouts.app` dan
   `layouts.guest` wajib punya `@vite`, pemeriksaan `build/manifest.json`,
   `/tailwind.js`, dan `@include('layouts._gaya-darurat')`; `layouts.print`
   wajib tetap mandiri (punya `<style>`, tanpa `@vite`).
3. `test_gaya_darurat_mencakup_semua_kelas_komponen_app_css` — setiap kelas di
   `@layer components` `resources/css/app.css` harus ada di
   `_gaya-darurat.blade.php`. Menambah komponen baru tanpa memperbarui gaya
   darurat akan gagal di CI.
4. `test_halaman_cetak_hanya_memakai_kelas_yang_didefinisikan` — setiap kelas di
   view `@extends('layouts.print')` harus punya aturan di `<style>`
   `layouts.print` atau `<style>` lokal view tersebut (hook JS dikecualikan).

## 5. Verifikasi yang dijalankan

| Pemeriksaan | Cara | Hasil |
|---|---|---|
| Build aset | `npm ci` lalu `npm run build` (vite 8.3.4, tailwindcss 4.3.3) | sukses; `app-BWZe9esl.css` 100.62 kB (gzip 18.48 kB), `app-BBzzTR7w.js` 1.45 kB, `manifest.json` 0.33 kB |
| Sintaks PHP | `php -l` (php-wasm PHP 8.5.10) pada `tests/Feature/CssHalamanTest.php` | tanpa galat |
| Logika tes 1–4 | harness PHP di luar PHPUnit (vendor tidak terpasang) | tes 1: 0 pelanggaran; tes 2: 4 syarat terpenuhi; tes 3: 32 kelas komponen, 0 hilang; tes 4: 0 kelas cetak tanpa definisi |
| Diff kelas vs CSS build | skrip Python (577 token unik) | 549 punya aturan, 28 terklasifikasi seperti §2 |
| CI | `gh pr checks` (SQLite + PostgreSQL 16) | _diisi setelah pipeline berjalan_ |

Catatan CSP: `public/tailwind.js` tidak memakai `eval`/`new Function` dan
menyuntikkan CSS lewat `document.createElement('style')`, sehingga cocok dengan
`script-src` berbasis nonce dan `style-src 'unsafe-inline'` yang berlaku
(`app/Http/Middleware/KeamananHeader.php`).

Yang **tidak** diverifikasi: tampilan nyata di browser, hasil cetak PDF, dan
perilaku Play CDN pada perangkat sangat lambat.

## 6. Risiko tersisa

1. **`public/build/` gitignored.** Setiap pemasangan/perbaruan wajib
   `npm run build`. Tanpa itu aplikasi tetap jalan lewat jalur darurat, tetapi
   lebih lambat (Tailwind dikompilasi di browser: `public/tailwind.js` 451 kB) dan hasil
   tipografinya tidak identik dengan build. Sudah dicatat di panduan deploy.
2. **`welcome.blade.php` tidak terpakai** — tidak ada route yang merujuknya;
   kelasnya ikut terhitung dalam inventaris. Kandidat dihapus pada pekerjaan
   terpisah (tidak dilakukan di sini agar tidak mengubah berkas di luar lingkup).
3. **Blok `<style>` masih inline.** Gaya darurat, gaya cetak, dan gaya dokumen
   bergantung pada `style-src 'unsafe-inline'` di CSP (`KeamananHeader.php`).
   Mengetatkan `style-src` tanpa memindahkan blok-blok ini ke berkas `@vite`
   akan mematikan gaya cetak dan dokumen — jangan dilakukan tanpa migrasi
   terlebih dahulu (lihat `docs/AUDIT-KEAMANAN.md`).
4. **Daftar kompatibilitas cetak bersifat manual.** Tes 4 menangkap kelas yang
   belum didefinisikan, tetapi tidak menangkap nilai yang berbeda dari
   Tailwind; penambahan utility baru di view cetak tetap perlu disertai aturan
   CSS-nya.
