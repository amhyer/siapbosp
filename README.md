# SiapBOSP

[![Tests](https://github.com/amhyer/siapbosp/actions/workflows/tests.yml/badge.svg)](https://github.com/amhyer/siapbosp/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

**Sistem Informasi Akuntansi & Pertanggungjawaban Dana BOSP — Multi Sekolah**

SiapBOSP memudahkan sekolah menyusun dan mempertanggungjawabkan dana BOS/BOSP
**sebelum** memasukkannya ke aplikasi ARKAS: perencanaan RKAS → pembukuan
(penerimaan/pengeluaran/kas) → pelaporan (BKU, realisasi, LPJ), dalam satu
aplikasi web multi-tenant — satu Dinas memantau seluruh sekolah.

## Fitur

- **Perencanaan** — RKAS-BOSP per rekening/komponen, revisi, paket tahap
  fleksibel (2–5 tahap; TA 2026 = dua tahap salur), cetak PDF.
- **Pembukuan** — pendapatan, belanja (+ terima pajak), setor pajak dengan
  NTPN, ambil simpanan & mutasi tunai, BKU otomatis, tutup buku per tahap.
- **Pelaporan** — realisasi vs RKAS, laporan per tahap/semester, LPJ + arsip
  lampiran, konsolidasi lintas sekolah untuk Dinas.
- **Impor ARKAS** — tarik data `arkas.db` sekolah lewat agen satu-klik
  ([`agen-arkas/`](agen-arkas/README.md)) atau impor Excel, idempoten.
- **Multi-tenant** — isolasi data per sekolah via global scope; role
  superadmin / dinas / operator / kepsek; API token untuk integrasi.
- **Portable** — paket ZIP & pemasang EXE untuk laptop bendahara
  ([`launcher-windows/`](launcher-windows/BACA-DULU.txt), [`CHANGELOG_PEMASANG.md`](CHANGELOG_PEMASANG.md)).

## Kebutuhan

- PHP **8.3+** + Composer
- Node.js 18+ (untuk aset & agen)
- SQLite bawaan (atau PostgreSQL/MySQL — lihat `.env.example`)

## Instalasi

```bash
git clone https://github.com/amhyer/siapbosp.git
cd siapbosp
composer setup        # install + .env + key + migrate + seed + build aset
php artisan serve     # http://127.0.0.1:8000
```

Mode pengembangan (server + queue + log bersamaan): `composer dev`.

## Akun demo

Semua kata sandi **`password`** (dibuat oleh seeder) — daftar lengkap di
[`docs/AKUN_DEMO.md`](docs/AKUN_DEMO.md):

| Email | Role | Panel |
|---|---|---|
| `dinas@siapbosp.id` | dinas | `/dinas` |
| `superadmin@siapbosp.id` | superadmin | `/dinas` |
| `operator@sekolah.demo` | operator | `/dashboard` |
| `kepsek@sekolah.demo` | kepsek | `/dashboard` |

## Pengujian

```bash
composer test         # atau: php artisan test
```

Suite PHPUnit feature/unit mencakup isolasi multi-tenant, impor ARKAS, BKU,
LPJ, dan keamanan token API. Jalankan `composer test` sebelum rilis; workflow CI
ada di [`.github/workflows/tests.yml`](.github/workflows/tests.yml).

## Dokumen

| Dokumen | Isi |
|---|---|
| [`BLUEPRINT_SiapBOSP.md`](BLUEPRINT_SiapBOSP.md) | Rancangan sistem, peran, alur bisnis |
| [`SiapBOSP_API.md`](SiapBOSP_API.md) / [`API.md`](API.md) | Endpoint API v1 |
| [`SiapBOSP_DEPLOY.md`](SiapBOSP_DEPLOY.md) | Deployment |
| [`docs/`](docs) | Panduan ARKAS, impor Excel, sinkron pusat |
| [`CHANGELOG_SiapBOSP.md`](CHANGELOG_SiapBOSP.md) | Riwayat perubahan |

## Struktur

```
app/            Controller, model, service, command
routes/         web.php, api.php
resources/views Blade (panel sekolah & dinas)
database/       migrasi + seeder
agen-arkas/     Agen Node.js tarik data arkas.db (satu-klik)
launcher-windows/  Skrip portable Windows
tools/          Pemasang EXE (bootstrap.cs)
tests/          PHPUnit feature & unit
```

## Lisensi

MIT — lihat [LICENSE](LICENSE).
