#!/usr/bin/env python3
"""peta-menu.py — gambar peta seluruh menu & halaman SiapBOSP menjadi PNG.

Sumber kebenaran: tools/tangkapan-layar/halaman.json (daftar yang sama yang
dipotret oleh foto.cjs) sehingga peta dan tangkapan layar tidak mungkin
berbeda halaman.

Hasil: docs/tangkapan-layar/peta-menu.png

  python3 tools/tangkapan-layar/peta-menu.py            # tulis PNG
  python3 tools/tangkapan-layar/peta-menu.py --svg      # sekalian .svg

Butuh Pillow (pip install pillow). Bukan tangkapan layar: ini peta struktur
menu, dibuat dari kode — bukan render aplikasi.
"""
from __future__ import annotations

import json
import os
import sys
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

AKAR = Path(__file__).resolve().parents[2]
SUMBER = Path(__file__).with_name("halaman.json")
KELUAR = AKAR / "docs" / "tangkapan-layar" / "peta-menu.png"

# Palet mengikuti aplikasi (slate/indigo), bukan tema baru.
NAVY = (15, 23, 42)
NAVY_2 = (30, 41, 59)
KERTAS = (248, 250, 252)
PUTIH = (255, 255, 255)
GARIS = (226, 232, 240)
TEKS = (15, 23, 42)
TEKS_2 = (100, 116, 139)
INDIANA = (79, 70, 229)
HIJAU = (5, 150, 105)
KUNING = (180, 83, 9)

WARNA_PERAN = {
    "operator": (79, 70, 229),
    "kepsek": (5, 150, 105),
    "dinas": (180, 83, 9),
}

LABEL_PERAN = {
    "operator": "Panel Sekolah — Operator (70 halaman)",
    "kepsek": "Panel Sekolah — Kepala Sekolah (sampel)",
    "dinas": "Panel Dinas (13 halaman)",
}

PADDING = 28
LEBAR_KOLOM = 560
JARAK_KOLOM = 26
TINGGI_ITEM = 40
TINGGI_KEPALA = 46


def font(ukuran: int, tebal: bool = False) -> ImageFont.FreeTypeFont:
    dasar = "/usr/share/fonts/truetype/dejavu/DejaVuSans%s.ttf" % ("-Bold" if tebal else "")
    if not os.path.exists(dasar):  # macOS
        dasar = "/System/Library/Fonts/Supplemental/Arial%s.ttf" % (" Bold" if tebal else "")
    return ImageFont.truetype(dasar, ukuran)


def main() -> int:
    data = json.loads(SUMBER.read_text(encoding="utf-8"))
    halaman = [h for h in data["halaman"] if not h.get("lewati")]

    # kelompokkan: peran -> grup -> halaman (urutan dipertahankan)
    urutan_peran = ["operator", "kepsek", "dinas"]
    kartu: list[dict] = []
    for peran in urutan_peran:
        grup_urut: list[str] = []
        isi: dict[str, list[dict]] = {}
        for h in halaman:
            if h["peran"] != peran:
                continue
            if h["grup"] not in isi:
                isi[h["grup"]] = []
                grup_urut.append(h["grup"])
            isi[h["grup"]].append(h)
        for grup in grup_urut:
            kartu.append({"peran": peran, "grup": grup, "isi": isi[grup]})

    # ukur tinggi tiap kartu
    for k in kartu:
        k["tinggi"] = TINGGI_KEPALA + len(k["isi"]) * TINGGI_ITEM + 12

    # susun ke kolom secara berimbang (greedy: taruh di kolom terpendek)
    jumlah_kolom = 3
    kolom: list[list[dict]] = [[] for _ in range(jumlah_kolom)]
    tinggi_kolom = [0] * jumlah_kolom
    for k in kartu:
        i = tinggi_kolom.index(min(tinggi_kolom))
        kolom[i].append(k)
        tinggi_kolom[i] += k["tinggi"] + JARAK_KOLOM

    f_judul = font(34, True)
    f_sub = font(15)
    f_kepala = font(16, True)
    f_item = font(14)
    f_path = font(11)
    f_kaki = font(12)

    tinggi_header = 118
    tinggi_kaki = 46
    tinggi_isi = max(tinggi_kolom)
    lebar = PADDING * 2 + jumlah_kolom * LEBAR_KOLOM + (jumlah_kolom - 1) * JARAK_KOLOM
    tinggi = tinggi_header + 24 + tinggi_isi + tinggi_kaki

    img = Image.new("RGB", (lebar, tinggi), KERTAS)
    d = ImageDraw.Draw(img)

    # header
    d.rectangle([0, 0, lebar, tinggi_header], fill=NAVY)
    d.text((PADDING, 24), "SiapBOSP — Peta Menu & Halaman", font=f_judul, fill=PUTIH)
    d.text(
        (PADDING, 70),
        f"Seluruh {len(halaman)} halaman. Sumber: layouts/app.blade.php + tools/tangkapan-layar/halaman.json "
        "— dipakai juga oleh foto.cjs untuk tangkapan layar.",
        font=f_sub, fill=(148, 163, 184),
    )
    # legenda peran
    lx = lebar - PADDING - 470
    for peran in urutan_peran:
        d.rounded_rectangle([lx, 30, lx + 14, 44], radius=4, fill=WARNA_PERAN[peran])
        d.text((lx + 20, 30), peran.capitalize(), font=f_sub, fill=(203, 213, 225))
        lx += 130
    d.rounded_rectangle([lebar - PADDING - 470, 62, lebar - PADDING, 96], radius=8, outline=(71, 85, 105))
    d.text((lebar - PADDING - 458, 72), "Peta struktur menu — bukan tangkapan layar aplikasi",
           font=f_sub, fill=(226, 232, 240))

    # kolom
    y_awal = tinggi_header + 24
    sudah_ditulis: set[str] = set()
    for ci, isi_kolom in enumerate(kolom):
        x = PADDING + ci * (LEBAR_KOLOM + JARAK_KOLOM)
        y = y_awal
        peran_terakhir = None
        for k in isi_kolom:
            if k["peran"] != peran_terakhir:
                peran_terakhir = k["peran"]
                # Judul peran cukup sekali; kolom lanjutan ditandai kecil agar
                # tidak terbaca seperti grup ganda.
                if k["peran"] in sudah_ditulis:
                    d.text((x, y + 2), LABEL_PERAN[k["peran"]].split(" (")[0] + " (lanjutan)",
                           font=f_path, fill=TEKS_2)
                    y += 22
                else:
                    sudah_ditulis.add(k["peran"])
                    d.text((x, y), LABEL_PERAN[k["peran"]], font=f_kepala,
                           fill=WARNA_PERAN[k["peran"]])
                    y += 26

            w = LEBAR_KOLOM
            h = k["tinggi"]
            d.rounded_rectangle([x, y, x + w, y + h], radius=10, fill=PUTIH, outline=GARIS)
            warna = WARNA_PERAN[k["peran"]]
            d.rectangle([x, y, x + 4, y + h], fill=warna)  # aksen kiri
            d.text((x + 18, y + 13), k["grup"], font=f_kepala, fill=TEKS)
            jumlah = f"{len(k['isi'])}"
            d.text((x + w - 18 - d.textlength(jumlah, font=f_path), y + 17), jumlah,
                   font=f_path, fill=TEKS_2)
            d.line([x + 14, y + TINGGI_KEPALA - 6, x + w - 14, y + TINGGI_KEPALA - 6], fill=GARIS)

            for i, hlm in enumerate(k["isi"]):
                yy = y + TINGGI_KEPALA + i * TINGGI_ITEM
                label = hlm["label"]
                maks = w - 40
                while d.textlength(label, font=f_item) > maks and len(label) > 4:
                    label = label[:-2]
                if label != hlm["label"]:
                    label = label.rstrip() + "…"
                d.text((x + 18, yy + 5), label, font=f_item, fill=TEKS)
                d.text((x + 18, yy + 22), hlm["path"], font=f_path, fill=TEKS_2)
            y += h + JARAK_KOLOM

    # kaki
    d.line([PADDING, tinggi - tinggi_kaki + 8, lebar - PADDING, tinggi - tinggi_kaki + 8], fill=GARIS)
    d.text((PADDING, tinggi - tinggi_kaki + 18),
           "Untuk gambar tiap halaman: jalankan tools/tangkapan-layar/foto.cjs di komputer yang menjalankan aplikasi "
           "(lihat docs/TANGKAPAN-LAYAR.md).",
           font=f_kaki, fill=TEKS_2)

    KELUAR.parent.mkdir(parents=True, exist_ok=True)
    img.save(KELUAR, optimize=True)
    print(f"Ditulis: {KELUAR}  ({img.width}×{img.height}, {KELUAR.stat().st_size // 1024} KB)")

    if "--svg" in sys.argv:
        svg = KELUAR.with_suffix(".svg")
        svg.write_text(svg_dari(data, halaman), encoding="utf-8")
        print(f"Ditulis: {svg}")
    return 0


def svg_dari(data: dict, halaman: list[dict]) -> str:
    """SVG sederhana (teks + kotak) untuk dokumentasi web."""
    baris = ['<svg xmlns="http://www.w3.org/2000/svg" width="1100" height="%d" font-family="sans-serif">' % (80 + len(halaman) * 20)]
    baris.append('<rect width="100%" height="100%" fill="#f8fafc"/>')
    baris.append('<text x="24" y="40" font-size="22" font-weight="700" fill="#0f172a">SiapBOSP — Peta Menu &amp; Halaman</text>')
    y = 80
    grup_kini = None
    for h in halaman:
        kunci = f'{h["peran"]} · {h["grup"]}'
        if kunci != grup_kini:
            grup_kini = kunci
            baris.append(f'<text x="24" y="{y}" font-size="14" font-weight="700" fill="#4f46e5">{kunci}</text>')
            y += 22
        baris.append(f'<text x="44" y="{y}" font-size="13" fill="#0f172a">{h["label"]}</text>')
        baris.append(f'<text x="420" y="{y}" font-size="11" fill="#64748b">{h["path"]}</text>')
        y += 20
    baris.append("</svg>")
    return "\n".join(baris) + "\n"


if __name__ == "__main__":
    raise SystemExit(main())
