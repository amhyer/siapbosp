# Perintah Agent: Bangun Modul Impor ARKAS → SiapBOS

> Salin seluruh isi blok di bawah ini sebagai prompt untuk agent di lain waktu.

---

Bangun modul impor ARKAS → SiapBOS (alur Excel → SiapBOS) di repo ini
(`C:\Users\LENOVO\Downloads\siapbosp-app-v12`, Laravel 13 / PHP 8.3).

**WAJIB baca dulu sebelum coding** (berurutan):
1. `docs/IMPOR-ARKAS-EXCEL.md` — spesifikasi lengkap + tabel mapping +
   masalah terbuka (§4) yang harus kamu putuskan dan tulis hasilnya kembali
   ke dokumen itu.
2. `BLUEPRINT_SiapBOSP.md` §5 (skema) dan §3 (aturan tutup buku, BKU otomatis,
   saldo tidak boleh negatif).
3. `app/Http/Controllers/RkasController.php` fungsi `impor` (pola impor CSV
   yang ada) dan `app/Http/Controllers/PengeluaranController.php` baris 51
   (pola cek `TutupBuku::terkunci`).

**Prasyarat lingkungan:** pastikan `php -v` ≥ 8.3 dan `composer -V` jalan;
jika belum, minta user menginstal dulu (jangan lanjut tanpa runtime PHP).
Lalu `composer install`. Database dev: SQLite `database/database.sqlite`
(sudah termigrasi 17/17 + terseed: 6 user, 2 sekolah — jangan
`migrate:fresh` tanpa konfirmasi user).

**Tugas (berurutan, satu per satu, verifikasi tiap langkah):**
1. Patch query sheet `KAS_Umum` di `C:\Users\LENOVO\Downloads\ArkasExcel\export.js`
   (tambahkan `k.id_kas_umum AS id_kas_umum, k.id_kas_nota AS id_kas_nota,`
   `k.id_rapbs_periode AS id_rapbs_periode`), karena impor KAS_Umum butuh
   kunci stabil — lihat `docs/IMPOR-ARKAS-EXCEL.md` §2.
2. Migrasi: tambah kolom `arkas_id` nullable + unique `(school_id, arkas_id)`
   di `rkas_details`, `kas_transactions`, `kas_taxes`.
3. `app/Services/ArkasImportService.php`: baca `.xlsx` (PhpSpreadsheet,
   sudah di `composer.json`) sheet RAPBS, KAS_Umum, Nota, Sekolah;
   cocokkan NPSN → `schools.npsn` (tolak bila tak ada/nonaktif);
   tulis via `updateOrCreate` per aturan §3 dokumen spesifikasi
   (idempoten, hormati tutup buku, rekening tak dikenal diskip-dilaporkan,
   tanpa penghapusan, satu transaksi DB, laporan hasil ke `activity_logs`).
4. `app/Http/Controllers/ImportArkasController.php` + route
   `GET/POST /impor-arkas` (grup `role:operator,kepsek`, nama
   `impor-arkas.*`) + view Blade form upload + halaman laporan hasil.
5. Putuskan masalah terbuka di §4 dokumen (nominal KAS_Umum, pemetaan
   `type`, `stage_id`, pajak, BKU) berdasar data nyata, dan tulis keputusan
   + alasan ke `docs/IMPOR-ARKAS-EXCEL.md` §4.
6. Test `tests/Feature/ArkasImportTest.php` + fixture xlsx kecil:
   impor-2x-tanpa-duplikat, isolasi antar-sekolah, skip periode tutup buku,
   tolak NPSN tak dikenal. Jalankan `php artisan test` sampai hijau.

**Batasan:** jangan ubah logika BKU/saldo/tutup buku yang sudah ada; jangan
menghapus data apa pun; jangan meniru perilaku impor RKAS lama yang selalu
membuat revisi baru; jangan menampilkan secret/token di log.

**Kriteria selesai:** semua langkah + test hijau + file uji nyata
`ArkasExcel/ARKAS-Lengkap-2025.xlsx` (NPSN 40313912) berhasil diimpor ke
database dev tanpa duplikat saat diimpor dua kali, dan dokumen §4 terisi
keputusan final.
