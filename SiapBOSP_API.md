# SiapBOSP — API v1

Aplikasi lain (mis. dashboard Dinas, aplikasi pelaporan, spreadsheet) dapat membaca data SiapBOSP melalui REST API. API juga menyediakan integrasi impor ARKAS terbatas untuk akun sekolah; endpoint ini memproses unggahan secara asinkron.

## Autentikasi

Semua endpoint (kecuali `/ping`) membutuhkan **token API** pribadi:

```
Authorization: Bearer <token>
```

Token dibuat oleh masing-masing pengguna:
- **Sekolah** (operator/kepsek): menu *Utility → Profil Sekolah → Token API*.
- **Dinas**: menu *Pengguna → Token API Dinas*.

Mengganti token akan menonaktifkan token lama. Alternatif (kurang disarankan): `?api_token=<token>` di URL.

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
| `POST /api/v1/arkas-push` | operator/kepsek | unggah XLSX ARKAS (maks. 20 MB) ke antrean impor; HTTP 202 berisi `import_id` |
| `GET /api/v1/arkas-import/{import_id}` | pemilik pekerjaan | status antrean/proses, atau laporan akhir; akses dibatasi ke pengguna dan sekolah pengunggah |

## Impor ARKAS asinkron

```bash
# Unggah berkas Excel, respons akan berisi import_id (HTTP 202).
curl -s -H "Authorization: Bearer $TOKEN" \
  -F "tahun=2026" -F "berkas=@arkas-2026.xlsx" \
  "$BASE/arkas-push"

# Polling (maks. 25 permintaan/menit per akun atau IP); status queued/processing memberi HTTP 202.
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/arkas-import/123"
```

Saat `status` menjadi `completed`, respons menyertakan `laporan`. Status `failed`
memberi HTTP 422. Berkas diproses oleh worker pada queue `imports`, tidak oleh
request web; jalankan worker persisten sesuai `docs/AUDIT-KEAMANAN.md` dan
jangan unggah ulang pekerjaan yang masih berjalan.

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

- Endpoint pembacaan bersifat read-only; integrasi tulis hanya `POST /api/v1/arkas-push` (impor ARKAS terbatas, antrean) dan endpoint sinkronisasi yang didokumentasikan terpisah. Token dan role tetap divalidasi server-side.
- Token operator/kepsek hanya dapat mengakses data sekolahnya sendiri (cakupan global `school`).
- Endpoint `/dinas/*` menolak token sekolah dengan `403`.
- Taruh aplikasi di balik **HTTPS** (lihat `DEPLOY.md`) agar token tidak tersadap.
