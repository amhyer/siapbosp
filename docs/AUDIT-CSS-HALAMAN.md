# Audit Pemuatan CSS per Halaman

Tanggal: 2026-10-25 · Lingkup: seluruh 97 berkas
Blade di `resources/views` (69 halaman `layouts.app`, 7 `layouts.print`,
7 `layouts.error`, 1 `layouts.guest`, sisanya partial/layout/Dompdf/mail).

Dokumen ini menjawab satu pertanyaan: **apakah setiap halaman benar-benar
mendapat CSS untuk kelas yang dipakainya?** Metodenya analisis statis — tidak
ada verifikasi visual, karena lingkungan kerja tidak punya runtime PHP
(`vendor/` tidak terpasang) maupun browser.

## 1. Bagaimana CSS dimuat

| Jalur | Pemicu | Sumber gaya | Halaman |
|---|---|---|---|
| A. Dev server (`hot`) | `public/hot` ada **dan** server Vite menjawab | `@vite([...])` → `http://…:5173/…` (CSP wajib mengizinkan origin ini) | 69 halaman `layouts.app`, 1 `layouts.guest` |
| B. Aset build | ada berkas `public/build/assets/*.css` | `<link rel="stylesheet" href="/build/assets/app-*.css">` **tanpa Vite/manifest/hot** | sama dengan A |
| C. Darurat | dua-duanya tidak memenuhi syarat di atas | `/tailwind.js` (bila ada) + `layouts/_gaya-darurat.blade.php` | sama dengan A |
| D. Cetak mandiri | selalu | blok `<style>` di `layouts/print.blade.php` (+ `<style>` lokal view) | 7 halaman `layouts.print` |
| E. Inline penuh | selalu | CSS di dalam view itu sendiri | 7 halaman `layouts.error`, `emails/notifikasi`, `laporan/lpj-pdf`, `rkas/pdf` (Dompdf) |

Penentuan jalur A–C dilakukan satu tempat: `App\Support\Aset`
(`Aset::sumber()`), bukan lagi `file_exists()` mentah di layout. Dua
kegagalan yang dulu menghasilkan **halaman tanpa CSS sama sekali** kini
tertangani sendiri:

| Keadaan | Dulu | Sekarang |
|---|---|---|
| `public/hot` basi (sisa `npm run dev` yang sudah ditutup) | `@vite` mengeluarkan alamat `http://localhost:5173/…` yang mati → CSS 404, halaman polos | koneksi ke dev server diuji (≤0,2 s); bila tidak menjawab, `public/hot` dihapus dan aplikasi memakai build/darurat |
| `manifest.json` menunjuk berkas yang hilang (build terpotong, ZIP tak lengkap) | tetap `@vite` → CSS 404, halaman polos | setiap berkas rujukan manifest diverifikasi; bila ada yang hilang → jalur darurat |
| `npm run dev` hidup, CSP hanya `'self'` | aset dev (port 5173) **diblokir CSP** → halaman polos | origin dev server + WebSocket HMR ditambahkan ke `script-src`/`style-src`/`connect-src` selama `hot` hidup |
| `manifest.json` rusak/hilang padahal berkas CSS ada | `@vite` gagal atau menunjuk aset yang tidak ada | jalur build memakai `<link>` langsung ke `build/assets/*.css` (glob), manifest hanya dipakai untuk laporan/paket |
| server lama masih menahan port 8000 (kode lama tetap dilayani) | halaman polos walau berkas sudah diperbarui | `launcher-windows\Jalankan-Ulang-Server.bat` mematikan listener port lalu menyalakan server baru |

Jalur B adalah yang dipakai produksi. **`public/build/` kini ikut repository**
(sejak 2026-10-25): laptop bendahara dan paket portable tidak punya Node.js,
sehingga tanpa aset yang dikomit setiap pemasangan baru tampil polos. Jalur C
tetap jaring pengaman bila `public/build` hilang/terpotong. Jalur D sengaja
tidak memakai `@vite` supaya dialog cetak tetap tampil benar walau aset build
hilang.

Konsekuensi untuk pengembang: setiap perubahan `resources/css/app.css` atau
`resources/js/app.js` **wajib** diikuti `npm run build` dan komit ulang
`public/build`. Tes `test_css_build_memuat_semua_kelas_komponen` menolak commit
yang lupa membangun ulang (CI membangun lebih dulu, jadi CI sendiri lolos).

### Diagnosa cepat (tanpa PHP CLI)

Buka `http://localhost:8000/_aset` — halaman ini (hanya bisa diakses dari
localhost) menampilkan commit yang sedang berjalan, sumber CSS yang dipakai,
berkas `public/build` yang terdeteksi, dan keadaan `public/hot`. Bila sumber CSS
tertulis **darurat**, berarti `public/build` tidak ada di folder yang dilayani.

### Diagnosa cepat (CLI)

```bash
php artisan siapbos:cek-aset
```

Perintah ini menyebutkan sumber CSS yang sedang dipakai halaman, keberadaan
`public/hot`, kelengkapan `public/build`, dan langkah perbaikannya. Kode keluar
`1` bila aplikasi masih di jalur darurat — bisa dipakai skrip pemasangan.
Launcher Windows (`launcher-windows/SiapBOSP.bat`) juga menjalankannya dan
menampilkan peringatan sebelum membuka browser bila aset belum siap.

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

### F-2 Gaya darurat hanya menutup 10 aturan dari 31 kelas komponen

Blok lama (`layouts/app`) hanya berisi 10 aturan: `body`, `.inp`, `.btn`,
`.btn2`, `.card`, `.tbl`, `.tbl th`, `.tbl td`, `.num`, `.badge`. `app.css`
mendefinisikan 31 kelas komponen; yang tidak punya padanan darurat antara lain
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
| Logika tes 1–4 | harness PHP di luar PHPUnit (vendor tidak terpasang) | tes 1: 0 pelanggaran; tes 2: 4 syarat terpenuhi; tes 3: 31 kelas komponen, 0 hilang; tes 4: 0 kelas cetak tanpa definisi |
| Diff kelas vs CSS build | skrip Python (577 token unik) | 549 punya aturan, 28 terklasifikasi seperti §2 |
| CI (run 40200670771) | workflow `tests` pada SQLite + PostgreSQL 16 | **205 tes lulus, 1.054 assertion** di kedua driver (201 tes sebelumnya + 4 tes baru); commit `c4fddbb` |

Catatan CSP: `public/tailwind.js` tidak memakai `eval`/`new Function` dan
menyuntikkan CSS lewat `document.createElement('style')`, sehingga cocok dengan
`script-src` berbasis nonce dan `style-src 'unsafe-inline'` yang berlaku
(`app/Http/Middleware/KeamananHeader.php`).

Yang **tidak** diverifikasi: tampilan nyata di browser, hasil cetak PDF, dan
perilaku Play CDN pada perangkat sangat lambat.

## 6. Risiko tersisa

1. **Aset build harus ikut di setiap rilis.** `public/build` sudah dikomit,
   tetapi bila seseorang membangun ulang CSS tanpa mengomit hasilnya, tampilan
   memakai CSS lama. Dijaga oleh tes paritas kelas; pengingatnya ada di
   `npm run build` + commit. Jika aset hilang sama sekali, aplikasi jatuh ke
   jalur darurat (lebih lambat: `public/tailwind.js` 451 kB dikompilasi di
   browser) dan `siapbos:cek-aset` melaporkannya.
2. **`welcome.blade.php` tidak terpakai** — tidak ada route yang merujuknya;
   kelasnya ikut terhitung dalam inventaris. Kandidat dihapus pada pekerjaan
   terpisah (tidak dilakukan di sini agar tidak mengubah berkas di luar lingkup).
3. **Jalur darurat bukan tiruan sempurna.** Bila build hilang, `/tailwind.js`
   mengompilasi utility di browser (451 kB skrip) dan gaya komponen berasal
   dari `_gaya-darurat`; tampilannya mendekati build tetapi tidak identik dan
   lebih lambat. Karena itu `siapbos:paket-portable` sekarang **menolak**
   mengemas bila `public/build` tidak lengkap.
4. **Blok `<style>` masih inline.** Gaya darurat, gaya cetak, dan gaya dokumen
   bergantung pada `style-src 'unsafe-inline'` di CSP (`KeamananHeader.php`).
   Mengetatkan `style-src` tanpa memindahkan blok-blok ini ke berkas `@vite`
   akan mematikan gaya cetak dan dokumen — jangan dilakukan tanpa migrasi
   terlebih dahulu (lihat `docs/AUDIT-KEAMANAN.md`).
5. **Daftar kompatibilitas cetak bersifat manual.** Tes 4 menangkap kelas yang
   belum didefinisikan, tetapi tidak menangkap nilai yang berbeda dari
   Tailwind; penambahan utility baru di view cetak tetap perlu disertai aturan
   CSS-nya.
