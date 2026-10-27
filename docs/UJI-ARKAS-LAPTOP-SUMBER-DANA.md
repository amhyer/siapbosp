# Uji di Laptop ARKAS — Daftar Sumber Dana (`ref_sumber_dana`)

> **Untuk siapa:** operator/bendahara yang memegang laptop dengan **ARKAS 4
> terpasang**, atau agen AI yang membantu di laptop itu.
> **Tujuan:** memastikan isi daftar sumber dana di `arkas.db` **sebelum** fitur
> "Sumber Dana Tambahan" per sekolah dibangun.
> **Waktu:** 10–15 menit. **Tidak ada yang diubah** di ARKAS.

---

## 1. Pertanyaan yang harus dijawab uji ini

Fitur *Sumber Dana Tambahan* di ARKAS punya tombol **"Unduh dan Perbarui Data"**
yang mengambil daftar dari layanan pusat. Rencana SiapBOSP adalah membaca daftar
itu **dari laptop** (lewat `arkas.db`, seperti tombol "Tarik Data ARKAS" yang
sudah ada) alih-alih membangun integrasi pusat sendiri.

Sebelum itu, tiga hal belum pasti dan **hanya bisa dijawab dari laptop nyata**:

| # | Pertanyaan | Kenapa penting |
|---|---|---|
| Q1 | Apakah tabel `ref_sumber_dana` memuat sumber dana **non-standar** (BOSDA kabupaten, komite, banpem), atau hanya 6–8 referensi resmi? | kalau hanya referensi resmi, tab "Sumber Dana Tambahan" tidak bisa dibaca dari tabel itu |
| Q2 | Apa **nama kolom** sebenarnya di `ref_sumber_dana`? | parser impor tidak boleh menebak nama kolom |
| Q3 | Apakah sumber dana non-standar punya **baris tersendiri** di `anggaran`/`kas_umum` (id berbeda), atau semuanya dilebur ke `id_ref_sumber_dana = 5` (Lainnya)? | menentukan apakah tab bisa menyaring per nama sumber atau hanya per kelompok |

Jawaban Q1–Q3 menentukan apakah rancangan "baca `ref_sumber_dana` → tabel
`sumber_dana_sekolah`" jalan, atau harus ada jalan lain (mis. hanya mengandalkan
teks `sumber_dana` di RAPBS).

---

## 2. Persiapan (5 menit)

1. **Buka ARKAS sekali, lalu tutup sepenuhnya.** Database SQLCipher bisa
   terkunci saat aplikasi berjalan — skrip akan menolak dan memberi pesan.
2. Cari berkas database:
   - tekan `Win + R`, ketik `%APPDATA%\Arkas`, Enter;
   - di situ ada **`arkas.db`**.
   - Kalau tidak ada: ARKAS mungkin hanya dipasang tetapi belum pernah dibuka,
     atau dipasang di profil Windows lain.
3. **Salin** `arkas.db` ke folder kerja, mis. `D:\uji-arkas\arkas.db`.
   Uji dilakukan pada **salinan**, bukan berkas asli. (Skrip sebenarnya
   read-only, tetapi salinan membuat kesalahan tidak mungkin menyentuh ARKAS.)
4. Siapkan salah satu dari dua cara menjalankan skrip:
   - **Ada Node.js** (≥ 18): jalankan `node -v`. Kalau keluar angka versi,
     cara A bisa dipakai.
   - **Tidak ada Node.js**: pakai `node.exe` yang sudah terbundel di aplikasi
     **SiapBOSP portabel** → folder `agen\TarikArkas\` (cara B).

---

## 3. Uji A — jalankan probe `cek-sumber-dana.js`

Skrip: **`agen-arkas/cek-sumber-dana.js`** (di repo ini). Read-only, tidak
mengunggah apa pun.

```bat
:: dari folder repo (atau folder mana pun tempat berkas ini disalin)
cd /d D:\uji-arkas

node "C:\path\ke\siapbosp\agen-arkas\cek-sumber-dana.js" --db "D:\uji-arkas\arkas.db" --keluar "D:\uji-arkas\hasil-sumber-dana.txt"
```

Hasil: **`hasil-sumber-dana.txt`** (dan `.json`). Isinya:

1. versi ARKAS + tahun aktif + NPSN (untuk memastikan laptop yang benar);
2. **semua tabel yang namanya mengandung "sumber"** + skema kolom + isinya —
   ini yang menjawab Q1 dan Q2; kolom bernilai uang dicetak `[nominal]`
   (nama kolomnya tetap terlihat, nilainya tidak);
3. sumber dana yang dipakai di `anggaran` dan `kas_umum` **per tahun** — Q3;
4. sumber dana/ID yang dipakai tetapi **tidak ada** di `ref_sumber_dana`;
5. ringkasan "kesimpulan awal" di bagian akhir.

> Skrip **tidak** menampilkan nominal transaksi, nama penerima, atau data
> siswa: setiap kolom bernilai uang (`jumlah`, `nilai`, `saldo`, …) diganti
> `[nominal]` baik di `.txt` maupun `.json`, sehingga isinya memang hanya nama
> sumber dana + jumlah baris. Tetap: periksa dulu isi `.txt` sebelum
> membagikannya ke siapa pun.

### Cara B — tanpa Node.js, pakai paket portabel SiapBOSP

1. Salin `cek-sumber-dana.js` ke folder **`agen\TarikArkas\`** di aplikasi
   SiapBOSP portabel (satu folder dengan `node.exe` dan `lib\`).
2. Buka Command Prompt di folder itu:

```bat
cd /d "C:\SiapBOSP\agen\TarikArkas"
node.exe cek-sumber-dana.js --db "D:\uji-arkas\arkas.db" --keluar "D:\uji-arkas\hasil-sumber-dana.txt"
```

Skrip otomatis memakai `lib\better-sqlite3-multiple-ciphers` di folder itu
(komponen yang sama dengan yang dipakai "Tarik Data ARKAS").

### Kalau muncul error

| Pesan | Artinya | Tindakan |
|---|---|---|
| `Database ARKAS tidak ditemukan` | path salah atau ARKAS belum pernah dibuka | cek langkah 2.2, atau isi `--db` manual |
| `Database tidak bisa dibaca (kunci salah / berkas rusak / ARKAS masih membuka)` | ARKAS masih jalan, atau berkas disalin saat sedang ditulis | tutup ARKAS, salin ulang, ulangi |
| `Cannot find module 'better-sqlite3-multiple-ciphers'` | Node ada, tetapi modul SQLCipher belum terpasang | pakai cara B |

---

## 4. Uji B — pembanding dari sisi ARKAS (opsional, 3 menit)

Supaya hasil probe bisa dipercaya, bandingkan dengan tampilan ARKAS:

1. Buka ARKAS → **Penganggaran** → tab **Sumber Dana Tambahan**.
2. Catat **nama sumber dana** yang muncul di select (boleh difoto).
3. Bandingkan dengan bagian *"Tabel: ref_sumber_dana"* di `hasil-sumber-dana.txt`:
   - nama yang muncul di ARKAS **ada** di laporan → Q1 terjawab "ya";
   - nama muncul di ARKAS tetapi **tidak** ada di laporan → berarti daftar itu
     tidak disimpan di `ref_sumber_dana`; kirimkan juga foto/daftar namanya.
4. Kalau tombol **"Unduh dan Perbarui Data"** bisa ditekan (butuh internet),
   tekan sekali, tutup ARKAS, salin `arkas.db` baru, dan jalankan probe lagi.
   Bandingkan kedua laporan — selisihnya memperlihatkan apa yang sebenarnya
   diunduh dari pusat.

---

## 5. Yang harus dilaporkan balik

Tempelkan ke sesi kerja (atau lampirkan berkasnya):

1. **`hasil-sumber-dana.txt`** — seluruhnya, atau minimal bagian
   *"Tabel: ref_sumber_dana"*, *"Sumber dana yang dipakai…"*, dan *"Ringkasan"*.
2. Jawaban tiga baris:

```
Versi ARKAS          : ................  (lihat baris "ARKAS: …" di laporan)
NPSN                 : ................
Q1 ref_sumber_dana memuat sumber non-standar?   ya / tidak / tidak tahu
Q2 kolom yang ada    : ................  (salin dari "Kolom (n): …")
Q3 sumber tambahan punya id sendiri di anggaran? ya / tidak / tidak tahu
```

**Jangan** mengunggah `arkas.db` itu sendiri: isinya seluruh keuangan sekolah,
nama penandatangan, dan data PTK. Yang dibutuhkan hanya laporan teks.

---

## 6. Hasil uji (diisi setelah uji)

| Tanggal | NPSN | Versi ARKAS | Baris `ref_sumber_dana` | Tabel "sumber" lain | Kesimpulan |
|---|---|---|---|---|---|
| 2026-10-27 | 40313912 | ARKAS 4.0 (4.3.0), tahun aktif 2026 | 11 baris · 21 kolom — termasuk id 1005 "Banpem Keterampilan Menulis pada Sekolah Dasar" (`is_sumber_dana_tambahan=1`, `is_filtered_by_instansi_id=1`, `mekanisme_pengesahan=1`) | `anggaran_sumber_dana` (2), `ref_sumber_dana_bentuk_pendidikan` (0), `ref_sumber_dana_sekolah` (1: id 1005 / 2026) | **Q1 = ya**, **Q2 = 21 kolom**, **Q3 = ya** — id 1005 dipakai di `anggaran` 2026 (2 baris), `kas_umum` 2026 baru id 1; pemakaian tanpa master: **0 nama, 0 id** |

_Sumber: salinan `arkas.db` (SQLCipher) tanggal 21 Okt 2026; probe
`agen-arkas/cek-sumber-dana.js` dijalankan read-only pada 2026-10-27, laporan
`.txt`/`.json` tanpa kolom nominal._

Cabang yang dipilih: **Q1 = ya** dan **Q3 = ya** → importer membaca
`Ref_Sumber_Dana` apa adanya ke tabel `sumber_dana_sekolah` (baris id 1005 ikut
terbawa lewat `ref_sumber_dana_sekolah`), dan tab ke-6 **menyaring per nama
sumber**. Pilihan lain tetap tercatat bila uji di laptop/versi ARKAS berbeda:

- **Q1 = ya** → importer membaca `Ref_Sumber_Dana` apa adanya ke tabel
  `sumber_dana_sekolah` (per sekolah), tab ke-6 menyaring per nama.
- **Q1 = tidak** (hanya 6–8 baris) → tab ke-6 dibentuk dari **teks `sumber_dana`**
  yang benar-benar muncul di `RAPBS`/`KAS_Umum`, dan tombol "Perbarui" hanya
  menyegarkan daftar itu (tetap tanpa layanan pusat).
- **Q3 = tidak** (semua dilebur ke `Lainnya`) → tab ke-6 menyaring kelompok
  `lainnya` dan menampilkan nama sumber sebagai chip, bukan filter per nama.

---

## 7. Prompt siap tempel

### 7a. Untuk agen AI yang bekerja **di laptop ARKAS** (Windows)

```
Kamu membantu menguji satu hal di laptop ini: isi daftar "sumber dana" di
database ARKAS 4 lokal. Ikuti docs/UJI-ARKAS-LAPTOP-SUMBER-DANA.md di repo
siapbosp (kalau repo belum ada di laptop ini, minta izin mengunduhnya dari
GitHub amhyer/siapbosp, atau minta user menyalin agen-arkas/cek-sumber-dana.js).

Aturan keras:
- JANGAN menulis, memindahkan, atau menghapus arkas.db. Hanya salin berkasnya
  (copy) lalu baca salinannya.
- JANGAN mengunggah arkas.db atau isi database ke mana pun. Yang boleh
  dibagikan hanya hasil-sumber-dana.txt.
- Jangan menyentuh berkas project lain selain yang disebut di atas.

Langkah:
1. Pastikan ARKAS tertutup (cek proses Arkas.exe). Kalau masih jalan, minta
   user menutupnya — jangan di-force kill.
2. Salin %APPDATA%\Arkas\arkas.db ke folder kerja D:\uji-arkas\ (buat foldernya).
3. Jalankan: node agen-arkas/cek-sumber-dana.js --db "D:\uji-arkas\arkas.db"
   --keluar "D:\uji-arkas\hasil-sumber-dana.txt"
   Kalau `node` tidak ada, pakai node.exe di folder agen\TarikArkas\ aplikasi
   SiapBOSP portabel (cara B di dokumen).
4. Kalau gagal, jangan mengarang hasil: laporkan pesan errornya apa adanya dan
   sebutkan langkah mana yang gagal.
5. Laporkan ke user: isi bagian "Tabel: ref_sumber_dana" (kolom + 20 baris
   pertama), daftar tabel lain yang ber-nama "sumber", dan bagian "Ringkasan".
   Jangan menyimpulkan lebih jauh dari isi berkas.
```

### 7b. Untuk ditempel balik ke sesi kerja SiapBOSP (setelah uji)

```
Hasil uji laptop ARKAS (docs/UJI-ARKAS-LAPTOP-SUMBER-DANA.md):
- Versi ARKAS: ...
- NPSN: ...
- ref_sumber_dana: ... baris, kolom: ...
- tabel lain ber-nama sumber: ...
- sumber dana terpakai tanpa master: ...
- lampiran: hasil-sumber-dana.txt

Silakan lanjutkan rancangan "Sumber Dana Tambahan":
1. migrasi tabel sumber_dana_sekolah + model;
2. importer membaca Ref_Sumber_Dana (defensif pada nama kolom);
3. tab ke-6 menyaring + chip nama sumber + CTA "Tarik Data ARKAS" bila kosong;
4. tombol Perbarui memakai TarikArkasController yang sudah ada;
5. tes + dogfooding CI (sqlite + pgsql).
Kalau hasil uji membantah asumsi (mis. ref_sumber_dana hanya 6 baris), tulis
dulu keputusan barunya di dokumen itu sebelum mengoding.
```

---

## 8. Kenapa uji ini dijalankan di laptop, bukan di sini

Sandbox pengembangan tidak punya:

- berkas `arkas.db` (data sekolah, tidak boleh keluar dari laptop),
- aplikasi ARKAS Windows,
- akses ke layanan pusat Kemendikdasmen.

Tanpa uji ini, parser impor hanya bisa ditulis **defensif** (mencoba beberapa
kemungkinan nama kolom) dan tesnya mengunci tebakan — persis yang ingin
dihindari. Satu laporan teks dari laptop nyata menghilangkan seluruh tebakan itu.

Referensi terkait: [`IMPOR-ARKAS-EXCEL.md`](IMPOR-ARKAS-EXCEL.md) §1 (skema
`arkas.db`), [`AUDIT-TARIK-ARKAS.md`](AUDIT-TARIK-ARKAS.md) (risiko kunci
SQLCipher), [`MASUKAN-ARKAS4-UNTUK-SIAPBOSP.md`](MASUKAN-ARKAS4-UNTUK-SIAPBOSP.md)
§3a baris "Sumber Dana Tambahan".
