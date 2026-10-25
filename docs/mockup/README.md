# Gambar mockup tampilan (paritas ARKAS 4)

Gambar-gambar ini menggambarkan usulan tampilan SiapBOSP yang dibahas di
[`../MASUKAN-ARKAS4-UNTUK-SIAPBOSP.md`](../MASUKAN-ARKAS4-UNTUK-SIAPBOSP.md)
dan versi HTML interaktifnya di [`../mockup-paritas-arkas4.html`](../mockup-paritas-arkas4.html).

| Berkas | Isi | Terkait |
|---|---|---|
| `00-semua-tampilan.png` | Semua panel dalam satu lembar | ringkasan |
| `01-penganggaran-kartu.png` | Top bar (tahun, versi ARKAS, pengingat pajak) + kartu per sumber dana + chip status Dinas | P0-1, P1-4 |
| `02-kertas-kerja-gerbang.png` | Kertas Kerja dengan gerbang pra-pengajuan: tombol **Ajukan** nonaktif, 3 pelanggaran disorot | P0-2 |
| `03-bku.png` | Lembar BKU: tab Jan–Des, tarik/setor tunai, badge **wajib lapor**, kilat kuning baris baru | P1-5 |
| `04-wizard-belanja.png` | Wizard 3 langkah mengikuti urutan ARKAS (SIPLah, tanggal pelunasan, NPWP/NIK) | P1-7 |
| `05-arsip-cetak.png` | Arsip terpadu (3 filter + 2 mode) dan pratinjau cetak ber-watermark **DRAFT** | P0-3, P1-6 |

## Sifat gambar

Digambar **manual dengan Pillow** (bukan AI image generator) supaya teks, angka,
dan istilah sama persis dengan dokumen masukan dan kode SiapBOSP. Semua data
adalah contoh; tidak ada data sekolah nyata.

## Cara regenerasi

```bash
python3 -m venv /tmp/venvmk && /tmp/venvmk/bin/pip install Pillow
/tmp/venvmk/bin/python docs/mockup/render_mockup.py
```

Skrip ini murni lokal: tanpa jaringan, tanpa aset eksternal, tanpa dependensi
selain Pillow. Hasil ditulis ke folder ini (PNG, 2× supersample lalu diperkecil
untuk `00-semua-tampilan.png`).

## Batas

Ini **mockup usulan**, bukan tangkapan layar aplikasi. Tampilan ARKAS 4 asli
tidak dapat diverifikasi secara visual di sandbox ini (screenshot portal resmi
tidak dapat dibuka), sehingga detail tata letak ARKAS yang dipakai sebagai
acuan berasal dari daftar langkah beranotasi pada artikel resmi — lihat bagian
*Metode & tingkat keyakinan sumber* di dokumen masukan.
