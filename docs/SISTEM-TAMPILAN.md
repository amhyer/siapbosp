# Sistem Tampilan SiapBOSP — Selaras ARKAS 4

Dokumen ini menjelaskan **satu tempat** yang mengatur rupa seluruh halaman SiapBOSP.
Tujuannya: setiap halaman (Penganggaran, Penatausahaan, Arsip, Laporan, Pengaturan,
Dinas) terlihat satu keluarga dengan ARKAS 4 tanpa harus menambal view satu per satu.

Sumber acuan visual: tangkapan layar **ARKAS 4.3.0** milik UPT SPF SD Negeri Unggulan
Mongisidi 1 (20 gambar, 21 Oktober 2026) — lihat ringkasannya di
[`MASUKAN-ARKAS4-UNTUK-SIAPBOSP.md`](MASUKAN-ARKAS4-UNTUK-SIAPBOSP.md).

> Catatan kejujuran: palet di bawah ini **pendekatan** dari tangkapan layar, bukan
> salinan resmi brand guideline Kemendikdasmen. Angka hex-nya dipilih agar mirip
> dan tetap kontras aman, bukan hasil sampling piksel.

---

## 1. Kenapa di lapisan token, bukan di tiap halaman

Seluruh view memakai dua lapis gaya:

1. **Kelas komponen** di `resources/css/app.css` (`card`, `tbl`, `btn`, `btn2`, `inp`,
   `badge`, `navw*`, `alert`, `toolbar`) — dipakai hampir semua halaman.
2. **Utilitas Tailwind** langsung di markup, dan mayoritas warna memakai keluarga
   `indigo-*` (`bg-indigo-600`, `text-indigo-700`, `ring-indigo-100`, …).

Karena itu palet ARKAS dipasang sebagai **pemetaan token** di blok `@theme`:

```css
@theme {
    --color-indigo-600: #1a56db;  /* biru ARKAS, bukan indigo */
    --color-indigo-700: #1e429f;
    …
    --radius-lg: 6px;
    --radius-xl: 8px;
    --radius-2xl: 10px;
}
```

Efeknya: satu perubahan di `app.css` mengubah **semua** halaman sekaligus, tanpa
menyentuh view. Tidak ada `indigo` yang ditulis manual sebagai hex di view mana pun
(dijaga tes, lihat §5).

## 2. Palet & bentuk

| Hal | Sebelum | Sesudah (ARKAS 4) |
|---|---|---|
| Warna utama | indigo `#4f46e5` | biru ARKAS `#1a56db` |
| Hover tombol | `#4338ca` | `#1e429f` |
| Latar chip | `#e0e7ff` | `#dbeafe` |
| Garis fokus input | `#c7d2fe` | `#dbeafe` |
| Sudut kartu | 16 px (`rounded-2xl`) | 10 px (`rounded-xl`) |
| Sudut tombol/input | 8 px (`rounded-lg`) | 6 px (`rounded-md`) |
| Sudut chip | kapsul (`rounded-full`) | 4 px (`rounded`) |
| Bayangan kartu/tombol | `shadow-sm` / custom | **tanpa bayangan**, hanya garis 1 px |
| Padding sel tabel | `.625rem .75rem`, teks 13 px | `.5rem .625rem`, teks 12,5 px |
| `theme-color` + `manifest.webmanifest` | `#4f46e5` | `#1a56db` |

Warna grafik ikut diselaraskan di `dashboard`, `dinas/dashboard`, dan `laporan/tahap`
(kuningan/garis grafik serta tombol kirim LPJ yang ditulis inline).

## 3. Dua jalur pemuatan CSS yang harus tetap sama

| Jalur | Berkas | Kapan dipakai |
|---|---|---|
| `build` | `public/build/assets/app-*.css` (hasil `npm run build`, **ikut di-commit**) | normal |
| `darurat` | `resources/views/layouts/_gaya-darurat.blade.php` | `public/build` hilang/terpotong |

Keduanya wajib memakai palet yang sama. `_gaya-darurat.blade.php` adalah CSS biasa
(bukan Tailwind), jadi setiap perubahan token di `app.css` harus dicerminkan manual
di sana — dan tes akan gagal bila paletnya berbeda.

## 4. Cara mengubah tampilan dengan benar

```bash
npm ci          # sekali per mesin
# …ubah resources/css/app.css atau view…
npm run build   # WAJIB: tanpa ini aplikasi masih memakai CSS lama
```

`public/build/manifest.json` dan berkas `public/build/assets/app-*.css` ikut
di-commit karena aplikasi dipasang di laptop sekolah tanpa Node.js
(lihat `SiapBOSP_DEPLOY.md`). Berkas CSS lama otomatis dihapus oleh Vite.

Aturan praktis saat menambah UI baru:

- Pakai kelas komponen (`card`, `tbl`, `btn`, `badge`, `inp`) — jangan menulis hex.
- Kalau butuh warna, pakai utilitas `indigo-*` (sudah berarti biru ARKAS), `slate-*`,
  `emerald/amber/rose` untuk status.
- Jangan menambah `rounded-2xl`/`shadow-lg` baru: ARKAS pipih dan tegas.
- Kelas baru harus ada di CSS hasil build (Tailwind memindai `resources/views`);
  kalau kelasnya kelas komponen di `@layer components`, **wajib** dicerminkan di
  `_gaya-darurat.blade.php` (dijaga `CssHalamanTest`).

## 5. Pagar regresi

`tests/Feature/ArkasTampilanTest.php` memeriksa:

1. token biru ARKAS terpasang untuk seluruh skala `indigo-*` (50…900);
2. CSS **hasil build** benar-benar memuat token itu, kartu tanpa bayangan, chip
   bersudut 4 px, dan tabel yang lebih rapat → menangkap “lupa `npm run build`”;
3. `_gaya-darurat.blade.php` memakai palet yang sama dan tidak menyisakan hex lama;
4. `theme-color` di `layouts/app`, `layouts/guest`, dan `manifest.webmanifest`;
5. tidak ada hex indigo lama yang ditulis manual di `resources/views`.

Selain itu `CssHalamanTest` tetap menjaga: setiap view punya layout beraset CSS,
halaman cetak hanya memakai kelas yang didefinisikan di `layouts/print`, dan semua
kelas komponen `app.css` ada di jalur darurat.

## 6. Yang sengaja tidak diubah

- **Struktur halaman.** Dokumen, kop surat, tanda tangan, dan tata letak cetak
  (`layouts/print`, `layouts.error`, halaman `dokumen`) tetap seperti semula.
- **Navigasi.** Sidebar gelap tiga area (Penganggaran · Penatausahaan · Arsip) sudah
  meniru ARKAS 4; menu SiapBOSP yang tidak punya padanan ARKAS tetap ada di bawah
  label “Menu Lain”. Sejak 28 Okt 2026 area **Penatausahaan** mengikuti ARKAS
  sepenuhnya: satu tautan, sedangkan rincian fiturnya ada di halaman
  `/penatausahaan` (lihat `docs/GAMBARAN-ARKAS-4.md` bagian 20).
- **Ikon/emoji.** ARKAS 4 tidak memakai ikon di sidebar sekolah; SiapBOSP masih
  memakai emoji pada beberapa item menu Dinas. Belum diubah karena akan mengubah
  hafalan pengguna tanpa manfaat fungsional.

## Sidebar tanpa ikon

ARKAS 4 menampilkan tiga area kerja (Penganggaran · Penatausahaan · Arsip) sebagai
teks saja, tanpa ikon. Karena itu label menu SiapBOSP juga teks murni — emoji
hanya dipakai di dalam isi halaman (tombol aksi), bukan di navigasi.
`MenuDanArsipTest` menjaga aturan ini: blok `<nav class="navw">` tidak boleh
memuat emoji.
