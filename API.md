# SiapBOSP — API Baca-Saja v1

Aplikasi lain (mis. dashboard Dinas, aplikasi pelaporan, spreadsheet) dapat membaca data SiapBOSP melalui REST API **read-only** ini.

## Autentikasi

Semua endpoint (kecuali `/ping`) membutuhkan **token API** pribadi:

```
Authorization: Bearer <token>
```

Token dibuat oleh masing-masing pengguna:
- **Sekolah** (operator/kepsek): menu *Utility → Profil Sekolah → Token API*.
- **Dinas**: menu *Pengguna → Token API Dinas*.

Mengganti token akan menonaktifkan token lama. Token hanya ditampilkan **satu kali** saat dibuat dan disimpan di server dalam bentuk ter-hash (SHA-256), sehingga tidak bisa dilihat lagi lewat aplikasi. Kirim selalu lewat header — dukungan `?api_token=<token>` di URL **sudah dihapus** agar token tidak bocor ke log server / riwayat browser.

Respons gagal autentikasi: `401 {"ok":false,"pesan":"..."}`.

## Ringkasan Endpoint

| Endpoint | Untuk | Isi |
|---|---|---|
| `GET /api/v1/ping` | publik | status layanan + versi |
| `GET /api/v1/profil` | semua | data pengguna + sekolah |
| `GET /api/v1/tahap?tahun=2026` | semua | daftar tahap + status dibuka dinas / tutup buku sekolah |
| `GET /api/v1/saldo?tahun=2026` | sekolah | pagu, dana cair, belanja, serapan %, pajak belum disetor, saldo tunai/bank |
| `GET /api/v1/transaksi?tahun=&stage=&jenis=&per_page=` | sekolah | transaksi BKU lengkap dengan rincian rekening & pajak (paginasi) |
| `GET /api/v1/dinas/sekolah` | dinas | daftar seluruh sekolah |
| `GET /api/v1/dinas/konsolidasi?tahun=` | dinas | ringkasan pagu/serapan/pajak per sekolah + total wilayah |

## Contoh

```bash
TOKEN="tempel-token-di-sini"
BASE="https://domain-anda/api/v1"

curl -s -H "Authorization: Bearer $TOKEN" $BASE/ping
curl -s -H "Authorization: Bearer $TOKEN" $BASE/saldo
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/transaksi?stage=6&jenis=belanja&per_page=50"
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/dinas/konsolidasi?tahun=2026"   # akun dinas
```

## Contoh Respons `/saldo`

```json
{
  "ok": true,
  "tahun": 2026,
  "sekolah": "SD Negeri 012345 Mamajang",
  "pagu": 150000000,
  "dana_cair": 60000000,
  "belanja": 10825000,
  "serapan_persen": 7.2,
  "pajak_belum_disetor": 325000,
  "saldo": {"tunai": 1675000, "bank": 47500000, "total": 49175000}
}
```

## Batasan & Keamanan

- API ini **read-only** — tidak ada endpoint tulis.
- Token operator/kepsek hanya dapat mengakses data sekolahnya sendiri (cakupan global `school`).
- Endpoint `/dinas/*` menolak token sekolah dengan `403`.
- Token diverifikasi sebagai hash SHA-256 (tidak pernah disimpan/dikirim plaintext di sisi server).
- Taruh aplikasi di balik **HTTPS** (lihat `DEPLOY.md`) agar token tidak tersadap.
