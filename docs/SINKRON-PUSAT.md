# Desain Sinkronisasi SiapBOSP Lokal → Pusat (Tahap C)

**Status:** desain disetujui-berjalan · **Dibuat:** 16 Oktober 2026
**Prinsip:** sama seperti impor ARKAS — idempoten, hormati tutup buku, tanpa hapus, laporkan selisih.

## 1. Arsitektur

```
Laptop sekolah (SQLite, 1 sekolah)          Website pusat (multi-tenant)
──────────────────────────────              ────────────────────────────
PASANG-SIAPBOSP.bat (sekali, Tahap D) ──► aplikasi + akun siap dipakai
Tarik ARKAS satu-klik (Tahap B) ──► DB lokal
DB lokal ──► [Sinkron ke Pusat] ──POST /api/v1/sync-push──► DB pusat (per school_id)
DB pusat ──GET /api/v1/sync-referensi──► DB lokal (master, pagu, tahap)
```

Identitas lokal: `launcher-windows/akun-sekolah.conf` (NPSN + identitas sekolah +
wilayah + TAHUN + TOKEN + PUSAT_URL), dibuat otomatis saat unduh per akun
(Tahap A3/A4, `UnduhAplikasiController`).

## 2. Aturan sync (wajib)

1. **Idempoten:** tiap baris punya `kunci` = `arkas_id` (hasil impor ARKAS) atau UUID klien
   (`lokal:{uuid}`) untuk input manual. Server `updateOrCreate(['school_id','arkas_id'])`.
2. **Hormati tutup buku:** server menolak baris periode terkunci (`422` + daftar ditolak);
   klien melaporkan, tidak mengulang buta.
3. **Tanpa hapus:** baris yang dihapus di lokal TIDAK menghapus di pusat (masuk selisih,
   sama seperti `selisih_hapus` di impor ARKAS).
4. **Konflik = last-write-wins per kunci**, berdasar `updated_at`; yang kalah tercatat
   di `peringatan` respons.
5. **Arah master satu arah (pusat → lokal):** rekening, pajak, komponen, tahap
   (buka/tutup), pagu, pengumuman. Lokal tidak boleh mengubah master
   (menu Referensi di portable disembunyikan — Tahap C2).
6. **Satu transaksi HTTP per sync**, dibungkus transaksi DB di server.
7. **Wajib HTTPS** di produksi (token di header `Authorization: Bearer`).

## 3. Endpoint pusat

| Endpoint | Isi |
|---|---|
| `POST /api/v1/sync-push` | Terima paket `{tahun, perangkat, data:{transaksi[]}}` (maks 500 baris); jawab `{ok, diterima, diperbarui, ditolak[], peringatan[]}` + simpan ke `activity_logs`. v1: transaksi (+rincian+pajak) saja; RKAS menyusul C2 |
| `GET /api/v1/sync-referensi?tahun=` | Snapshot master `{versi_master, rekenings[], pajaks[], komponens[], stages[], tutup_buku[]}`; klien simpan `versi_master` di `sync_states` |

## 4. Sisi lokal (ada di ZIP portable yang sama)

- `php artisan siapbos:sync-push` — baca `akun-sekolah.conf`, ambil baris
  `updated_at > sync_states.terakhir_dorong`, POST ke pusat, simpan hasilnya.
- `Sinkron-Pusat.bat` — satu-klik (mirip `Tarik-ARKAS.bat`).
- Tabel lokal `sync_states (id, kunci, nilai)` — `terakhir_dorong`, `versi_master`.

## 5. Cakupan v1 (lengkap, 16 Okt 2026)

- `kas_transactions` (+details+taxes): idempoten per kunci, no_bku pusat dipertahankan.
- `rkas` (+details via `arkas_id`): hanya header DRAFT; tolak bila tahun itu sudah
  disetujui/dikunci (revisi lewat web).
- `tandatangan`: cocok (nama+jabatan), tanpa duplikat.
- `tutup` + `lpj_kumpulkan`: aksi idempoten dengan validasi yang sama dengan tombol
  web (LPJ wajib tutup buku dulu; tidak ganda; notifikasi Dinas hanya saat baru).
- `lampiran` per transaksi: base64 ≤5 MB, pdf/jpg/png, dedup nama+ukuran.
- Yang TIDAK disync (sengaja): master referensi (turun dari pusat), buka-buku
  (hanya Kepsek via web), putusan dinas atas LPJ/RKAS (terima/tolak/revisi).

## 6. Pemasangan mandiri di laptop bendahara (Tahap D, 18 Okt 2026)

Paket hasil `GET /unduh-aplikasi` kini **mandiri** — laptop bendahara tidak
perlu install PHP/Node/Composer sama sekali. Isi tambahan paket:

- **Runtime PHP portable** (`php/`): `php.exe` + DLL + `ext/` + `php.ini`
  khusus (ekstensi sqlite3, mbstring, openssl, curl, zip, fileinfo, gd).
- **Agen Tarik ARKAS** (`agen/TarikArkas/`): `node.exe` + `tarik.cjs`
  (SQLCipher read-only) — tidak perlu unduh agen terpisah lagi.
- **CA bundle** `php/cacert.pem` + `resources/php-portable/cacert.pem`
  (lihat catatan HTTPS di bawah).

Alur pasang (sekali, ±2–3 menit) via `PASANG-SIAPBOSP.bat`:
1. Salin aplikasi ke `%LocalAppData%\Programs\SiapBOSP` (tanpa admin).
2. Jalankan `php artisan siapbos:pasang-lokal` (perintah baru):
   buat `.env` dari `.env.portable` + APP_KEY, migrasi, seed master
   (`MasterSeeder` idempoten: 56 rekening, 5 pajak, tahap 2023–2028),
   buat sekolah dari `akun-sekolah.conf`, buat akun operator (bendahara),
   dan **tulis token Tarik ARKAS otomatis** ke `launcher-windows/agen.conf`
   (menggantikan salin-tempel token manual).
3. Tiga pintasan Desktop: **SiapBOSP**, **Tarik Data ARKAS**,
   **Sinkron ke Pusat** (+ ikon khusus, unblock mark-of-the-web).

Catatan teknis:
- **HTTPS pusat:** curl PHP Windows tanpa CA bundle gagal
  (`unable to get local issuer certificate`). Aplikasi membawa
  `resources/php-portable/cacert.pem` dan memasangnya sebagai opsi
  `verify` pada HTTP client (`AppServiceProvider`) — tidak bergantung
  php.ini mesin. `ini_set('curl.cainfo')` TIDAK mempan (terverifikasi).
- `SiapBOSP.bat` memilih PHP bundel lebih dulu (fallback PHP sistem untuk
  dev), memeriksa kesiapan via `siapbos:pasang-lokal --status`, dan
  menuntun bila belum dipasang.
- `Tarik-ARKAS.bat` memeriksa dulu server lokal hidup (TCP 127.0.0.1:PORT)
  dan memberi pesan "jalankan SiapBOSP.bat dulu" alih-alih galat mentah.
- `Hapus-SiapBOSP.bat` = uninstaller (aplikasi + data lokal; data pusat aman).
- Uji baru: `PasangLokalTest` (5 kasus), `CaBundleTest`; `UnduhAplikasiTest`
  diperluas (conf memuat JENJANG/DESA/KECAMATAN/KAB_KOTA/PROVINSI/TAHUN).
- Urutan kompatibilitas: master referensi lokal disamakan dengan pusat lewat
  `MasterSeeder` (satu sumber dengan `DatabaseSeeder`), sehingga impor ARKAS
  lokal memetakan rekening/tahap yang sama dengan pusat.
