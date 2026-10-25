#!/usr/bin/env python3
"""Render gambar mockup tampilan SiapBOSP (paritas ARKAS 4) -> PNG.

Butuh Pillow:      pip install Pillow
Jalankan:          python3 docs/mockup/render_mockup.py
Hasil:             docs/mockup/*.png

Semua digambar manual (bukan AI) supaya teks, angka, dan istilah persis
sama dengan docs/mockup-paritas-arkas4.html dan docs/MASUKAN-ARKAS4-UNTUK-SIAPBOSP.md.
"""
import os
from PIL import Image, ImageDraw, ImageFont, ImageChops

S = 2                     # supersample 2x
W = 1200                  # lebar logis

FD = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
FDB = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'
FDM = '/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf'

INK, MUTE, LINE, BG = '#0f172a', '#64748b', '#e2e8f0', '#f1f5f9'
CARD, SOFT, DARK = '#ffffff', '#f8fafc', '#0b3b36'
BRAND, BRAND2 = '#0f766e', '#0d9488'
ROSE, AMBER, GREEN, BLUE, VIOLET = '#e11d48', '#d97706', '#15803d', '#1d4ed8', '#7c3aed'

BADGE = {
    'green': ('#dcfce7', '#166534', '#bbf7d0'),
    'amber': ('#fef3c7', '#92400e', '#fde68a'),
    'rose':  ('#ffe4e6', '#9f1239', '#fecdd3'),
    'slate': ('#eef2f7', '#475569', '#e2e8f0'),
    'blue':  ('#dbeafe', '#1e40af', '#bfdbfe'),
}

_fc = {}


def rp(n):
    return 'Rp ' + f'{n:,}'.replace(',', '.')


def angka(n):
    return f'{n:,}'.replace(',', '.')


class P:
    def __init__(self, w=W, h=800, bg=BG):
        self.w, self.h = w, h
        self.img = Image.new('RGB', (w * S, h * S), bg)
        self.d = ImageDraw.Draw(self.img)

    # ---------- primitives ----------
    def font(self, size, bold=False, mono=False):
        key = (size, bold, mono)
        if key not in _fc:
            path = FDM if mono else (FDB if bold else FD)
            _fc[key] = ImageFont.truetype(path, int(round(size * S)))
        return _fc[key]

    def tw(self, s, size=13.0, bold=False, mono=False):
        b = self.d.textbbox((0, 0), s, font=self.font(size, bold, mono))
        return (b[2] - b[0]) / S

    def text(self, x, y, s, size=13.0, bold=False, color=INK, mono=False, anchor='la'):
        self.d.text((x * S, y * S), s, font=self.font(size, bold, mono),
                    fill=color, anchor=anchor)

    def right(self, x, y, s, size=13.0, bold=False, color=INK, mono=False):
        self.text(x, y, s, size, bold, color, mono, anchor='ra')

    def mid(self, x, y, s, size=13.0, bold=False, color=INK, mono=False):
        self.text(x, y, s, size, bold, color, mono, anchor='ma')

    def rect(self, x, y, w, h, fill=None, stroke=None, r=0, lw=1):
        if r:
            self.d.rounded_rectangle([x * S, y * S, (x + w) * S, (y + h) * S],
                                     radius=r * S, fill=fill, outline=stroke, width=lw * S)
        else:
            self.d.rectangle([x * S, y * S, (x + w) * S, (y + h) * S],
                             fill=fill, outline=stroke, width=lw * S)

    def hline(self, x1, x2, y, color=LINE, lw=1):
        self.d.line([x1 * S, y * S, x2 * S, y * S], fill=color, width=lw * S)

    def dashed(self, x1, x2, y, color=LINE, dash=5, gap=4):
        x = x1
        while x < x2:
            self.hline(x, min(x + dash, x2), y, color)
            x += dash + gap

    def btn(self, x, y, label, w=None, h=28, primary=False, disabled=False,
            size=11.5, right=False, stroke=LINE):
        bw = w if w else self.tw(label, size, bold=primary) + 22
        bx = x - bw if right else x
        if primary and not disabled:
            self.rect(bx, y, bw, h, fill=BRAND, r=8)
            self.mid(bx + bw / 2, y + (h - size * 1.15) / 2 - 0.5, label, size, True, '#ffffff')
        else:
            self.rect(bx, y, bw, h, fill=CARD, stroke=stroke, r=8)
            col = '#94a3b8' if disabled else INK
            self.mid(bx + bw / 2, y + (h - size * 1.15) / 2 - 0.5, label, size, primary, col)
        return bw

    def badge(self, x, y, label, kind='slate', size=10.0, right=False):
        bgs, fgs, rgs = BADGE[kind]
        bw = self.tw(label, size, bold=True) + 16
        bx = x - bw if right else x
        self.rect(bx, y, bw, 19, fill=bgs, stroke=rgs, r=9.5)
        self.mid(bx + bw / 2, y + (19 - size * 1.2) / 2, label, size, True, fgs)
        return bw

    def bar(self, x, y, w, pct, color=BRAND, h=6):
        self.rect(x, y, w, h, fill='#eef2f7', r=h / 2)
        if pct > 0:
            self.rect(x, y, max(h, w * pct), h, fill=color, r=h / 2)

    def sel(self, x, y, w, value, h=32, size=12.0):
        self.rect(x, y, w, h, fill=CARD, stroke=LINE, r=8)
        self.text(x + 10, y + (h - size * 1.2) / 2, value, size, color=INK)
        self.right(x + w - 10, y + (h - size * 1.2) / 2, '\u25be', size - 1, color=MUTE)

    def inp(self, x, y, w, value, h=32, size=12.0):
        self.rect(x, y, w, h, fill=CARD, stroke=LINE, r=8)
        self.text(x + 10, y + (h - size * 1.2) / 2, value, size, color=INK)

    def frame_top(self, y, h, r=12):
        self.rect(0, y, W, h, fill=CARD, stroke=LINE, r=r)
        self.rect(0, y + h - r, W, r + 1, fill=CARD)

    def watermark(self, label='DRAFT', size=92, color=(225, 29, 72, 30)):
        ov = Image.new('RGBA', (self.img.width, self.img.height), (0, 0, 0, 0))
        od = ImageDraw.Draw(ov)
        od.text((self.img.width / 2, self.img.height / 2), label,
                font=self.font(size, True), fill=color, anchor='mm')
        ov = ov.rotate(-28, resample=Image.BICUBIC,
                       center=(self.img.width / 2, self.img.height / 2))
        self.img = Image.alpha_composite(self.img.convert('RGBA'), ov).convert('RGB')
        self.d = ImageDraw.Draw(self.img)

    def watermark_in(self, x, y, w, h, label='DRAFT', size=64, color=(225, 29, 72, 34)):
        """Watermark hanya di dalam kotak tertentu (mis. area kertas cetak)."""
        layer = Image.new('RGBA', (int(w * S), int(h * S)), (0, 0, 0, 0))
        ld = ImageDraw.Draw(layer)
        ld.text((w * S / 2, h * S / 2), label, font=self.font(size, True),
                fill=color, anchor='mm')
        layer = layer.rotate(-28, resample=Image.BICUBIC,
                             center=(w * S / 2, h * S / 2))
        box = self.img.crop((int(x * S), int(y * S), int((x + w) * S), int((y + h) * S)))
        box = Image.alpha_composite(box.convert('RGBA'), layer).convert('RGB')
        self.img.paste(box, (int(x * S), int(y * S)))
        self.d = ImageDraw.Draw(self.img)

    def crop(self, pad=0):
        bg = self.img.getpixel((0, 0))
        diff = ImageChops.difference(self.img, Image.new('RGB', self.img.size, bg))
        bb = diff.getbbox()
        bottom = (bb[3] if bb else self.img.height) + pad * S
        self.img = self.img.crop((0, 0, self.img.width, min(bottom, self.img.height)))
        self.d = ImageDraw.Draw(self.img)
        return self.img

    def save(self, path, pad=0):
        os.makedirs(os.path.dirname(path), exist_ok=True)
        self.crop(pad).save(path, optimize=True)
        print(f'  {path}  ({self.img.width}x{self.img.height})')


def judul(p, no, teks, sub=None):
    """Judul panel di luar frame."""
    x = 0
    tw = p.tw(no, 10.5, True) + 18
    p.rect(x, 4, tw, 19, fill=BRAND, r=9.5)
    p.mid(x + tw / 2, 7, no, 10.5, True, '#ffffff')
    p.text(x + tw + 10, 4, teks, 14.5, True)
    if sub:
        p.text(x + tw + 10 + p.tw(teks, 14.5, True) + 10, 6, sub, 11.5, color=MUTE)


# =====================================================================
# PANEL 1 — top bar + kartu sumber dana
# =====================================================================
def panel1(path):
    TOP, PAD = 42, 16
    y_top = TOP
    y_note = y_top + 46
    y_head = y_note + 40
    y_grid = y_head + 52
    ch, gap = 196, 14
    cw = (W - PAD * 2 - gap) / 2
    y_end = y_grid + ch * 2 + gap + 16 + 30

    p = P(1200, y_end + 8)
    judul(p, '1', 'Top bar & kartu sumber dana', '· pengganti halaman depan Penganggaran (P1-4)')

    p.frame_top(TOP, y_end - TOP)

    # --- top bar ---
    p.rect(0, y_top, W, 46, fill=DARK, r=12)
    p.rect(0, y_top + 30, W, 16, fill=DARK)
    p.text(20, y_top + 15, 'SIAP', 13.5, True, '#e6fffb')
    p.text(20 + p.tw('SIAP', 13.5, True), y_top + 15, 'BOSP', 13.5, True, '#5eead4')

    nav = ['Data Sekolah', 'Penganggaran', 'Penatausahaan', 'Laporan', 'Arsip', 'Referensi', 'Pengaturan']
    nx = 108
    for i, item in enumerate(nav):
        twi = p.tw(item, 11.5, bold=(i == 1))
        if i == 1:
            p.rect(nx - 8, y_top + 11, twi + 16, 24, fill='#12564e', r=7)
            p.text(nx, y_top + 16, item, 11.5, True, '#ffffff')
        else:
            p.text(nx, y_top + 16, item, 11.5, color='#c7f9f2')
        nx += twi + 24

    # right chips
    cx = W - 20
    for label, dot in [('Bendahara \u25be', False), ('ARKAS 4.2.15 -> 4.3.0', True), ('2026 \u25be', False)]:
        cwid = p.tw(label, 10.5) + 18
        cx -= cwid
        p.rect(cx, y_top + 13, cwid, 20, fill='#14524a', r=10)
        if dot:
            p.d.ellipse([(cx + 7) * S, (y_top + 19) * S, (cx + 13) * S, (y_top + 25) * S],
                        fill='#fb7185')
            p.text(cx + 17, y_top + 17, label, 10.5, color='#e6fffb')
        else:
            p.mid(cx + cwid / 2, y_top + 17, label, 10.5, color='#e6fffb')
        cx -= 8

    # --- baris pengingat ---
    p.hline(0, W, y_note)
    p.text(20, y_note + 14, 'PENGINGAT', 9.5, True, MUTE)
    bw = p.badge(100, y_note + 10, 'Pajak September belum dilaporkan - jatuh tempo 20 Okt', 'rose')
    p.badge(100 + bw + 8, y_note + 10, 'BKU Juli belum ditutup', 'amber')
    p.badge(W - 20, y_note + 10, '2 sumber dana menunggu review Dinas', 'blue', right=True)
    p.hline(0, W, y_head)

    # --- header aksi ---
    p.text(20, y_head + 16, 'Aktivasi & Penyusunan Kertas Kerja', 13.0, True)
    p.text(20 + p.tw('Aktivasi & Penyusunan Kertas Kerja', 13.0, True) + 8, y_head + 18,
           '- Tahun 2026', 11.5, color=MUTE)
    x = W - 20
    w = p.btn(x, y_head + 13, '+ Aktivasi Sumber Dana', primary=True, right=True)
    x -= w + 8
    w = p.btn(x, y_head + 13, 'Salin dari 2025', right=True)
    x -= w + 8
    p.btn(x, y_head + 13, 'Riwayat 3 tahun \u25be', right=True)
    p.hline(0, W, y_head + 52)

    # --- kartu ---
    cards = [
        dict(nama='BOSP Reguler', sub='Dana pusat - DAU', badge=('Disahkan', 'green'),
             pagu=1234000000, sudah=1234000000, belum=0, pct=1.0, col=GREEN,
             aksi='Perubahan', hapus=True),
        dict(nama='BOSP Kinerja', sub='Tahap 1 sudah cair', badge=('Perlu Diajukan Ulang', 'amber'),
             pagu=180000000, sudah=155500000, belum=24500000, pct=.86, col=AMBER,
             aksi='Kirim Pengajuan', hapus=True),
        dict(nama='BOSP Afirmasi', sub='Sumber dana tambahan', badge=('Perlu Revisi', 'rose'),
             pagu=45000000, sudah=40000000, belum=5000000, pct=.89, col=ROSE,
             aksi='Revisi Kertas Kerja', hapus=True),
        dict(nama='BOSP Daerah', sub='Belum ada penetapan', badge=('Belum Aktif', 'slate'),
             pagu=None, sudah=None, belum=None, pct=0.0, col='#cbd5e1',
             aksi='Aktivasi', hapus=False),
    ]

    p.rect(0, y_grid - PAD + 2, W, y_end - (y_grid - PAD + 2), fill=SOFT)
    for i, c in enumerate(cards):
        x = PAD + (i % 2) * (cw + gap)
        y = y_grid + (i // 2) * (ch + gap)
        p.rect(x, y, cw, ch, fill=CARD, stroke=LINE, r=12)
        p.rect(x + 1, y + 12, 4, ch - 24, fill=c['col'], r=2)
        p.text(x + 16, y + 12, c['nama'], 13.5, True)
        p.text(x + 16, y + 32, c['sub'], 10.5, color=MUTE)
        p.badge(x + cw - 14, y + 12, c['badge'][0], c['badge'][1], right=True)

        rows = [('Pagu', c['pagu']), ('Sudah dianggarkan', c['sudah']),
                ('Belum dianggarkan', c['belum'])]
        for j, (lab, val) in enumerate(rows):
            yy = y + 56 + j * 22
            p.text(x + 16, yy, lab, 11.5, color=MUTE)
            txt = '-' if val is None else rp(val)
            col = INK
            if j == 2 and val is not None:
                col = GREEN if val == 0 else ROSE
            p.right(x + cw - 16, yy, txt, 11.5, True, col)
        p.bar(x + 16, y + 130, cw - 32, c['pct'], color=c['col'])
        p.dashed(x + 16, x + cw - 16, y + 150)

        bx = x + 16
        for lab, dis in [('Lihat', c['pagu'] is None), ('Cetak', c['pagu'] is None),
                         ('Hapus', not c['hapus'])]:
            bw2 = p.btn(bx, y + 160, lab, h=24, size=10.5, disabled=dis)
            bx += bw2 + 6
        p.btn(x + cw - 16, y + 160, c['aksi'], h=24, size=10.5,
              primary=(c['badge'][1] in ('amber', 'rose')), right=True)

    p.text(20, y_end - 40,
           'Chip status memakai kosakata Dinas; alur internal Kepsek tetap ada di dalam kartu, bukan sebagai status utama.',
           11.0, color=MUTE)
    p.save(path)


# =====================================================================
# PANEL 2 — kertas kerja + gerbang pra-pengajuan
# =====================================================================
def panel2(path):
    TOP = 42
    y = TOP
    y_head = y
    y_gate = y + 104
    y_tabs = y_gate + 86
    y_th = y_tabs + 42
    rows = [('komp', 30), ('keg', 30), ('warn2', 46), ('komp', 30), ('warn2', 46),
            ('flash', 30), ('warn1', 46)]
    y_rows = y_th + 30
    y_foot = y_rows + sum(h for _, h in rows)
    y_end = y_foot + 56

    p = P(1200, y_end + 8)
    judul(p, '2', 'Kertas Kerja: gerbang pra-pengajuan', '· tombol Ajukan nonaktif selama ada baris merah (P0-2)')
    p.frame_top(TOP, y_end - TOP)

    # header
    p.text(20, y_head + 14, 'BOSP Kinerja 2026', 15.0, True)
    p.badge(20, y_head + 38, 'Perlu Diajukan Ulang', 'amber')
    p.text(20, y_head + 66, 'Bendahara: Siti R.', 10.5, color=MUTE)

    for x, lab, val, col in [(290, 'PAGU', 180000000, INK),
                             (460, 'SUDAH DIANGGARKAN', 155500000, INK),
                             (630, 'BELUM DIANGGARKAN', 24500000, ROSE)]:
        p.text(x, y_head + 16, lab, 9.5, True, MUTE)
        p.text(x, y_head + 32, rp(val), 16.0, True, col)

    x = W - 20
    w = p.btn(x, y_head + 64, 'Ajukan Pengesahan', primary=True, right=True, disabled=True, w=150)
    x -= w + 8
    w = p.btn(x, y_head + 64, 'Review & Proporsi', right=True)
    x -= w + 8
    p.btn(x, y_head + 64, 'Panduan', right=True)
    p.hline(0, W, y_gate)

    # gate
    p.rect(0, y_gate, W, 86, fill='#fff1f2')
    p.text(20, y_gate + 12, 'Belum bisa diajukan - 3 hal harus dibereskan:', 12.0, True, '#9f1239')
    items = ['Belum Dianggarkan Rp 24.500.000 - harus Rp 0',
             'Uraian 612 karakter - batas 500',
             'Harga satuan Rp 1.850.000 melebihi batas Dinas Rp 1.500.000']
    for i, it in enumerate(items):
        p.text(26, y_gate + 36 + i * 17, f'{i+1}.', 11.0, True, '#9f1239')
        p.text(40, y_gate + 36 + i * 17, it, 11.0, color='#9f1239')
    p.hline(0, W, y_tabs)

    # tabs bulan
    bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
    bw = 46
    for i, b in enumerate(bulan):
        x = 14 + i * bw
        on = (i == 0)
        if on:
            p.rect(x, y_tabs, bw - 2, 42, fill=CARD)
            p.rect(x + 4, y_tabs + 38, bw - 10, 2.5, fill=BRAND, r=1)
        p.mid(x + (bw - 2) / 2, y_tabs + 14, b, 11.5, on, BRAND if on else MUTE)
    p.right(W - 20, y_tabs + 14, 'Per Tahun', 11.5, color=BRAND)
    p.right(W - 20 - p.tw('Per Tahun', 11.5) - 20, y_tabs + 14, 'Per Tahapan', 11.5, color=BRAND)
    p.rect(0, y_tabs, W, 42, fill=None, stroke=LINE)
    p.hline(0, W, y_tabs + 42)

    # header tabel
    p.rect(0, y_th, W, 30, fill=SOFT)
    col = dict(ur=20, vol=500, sat=520, har=640, jum=800, pan=830, aks=1170)
    p.text(col['ur'], y_th + 9, 'URAIAN', 9.5, True, MUTE)
    p.right(col['vol'], y_th + 9, 'VOL', 9.5, True, MUTE)
    p.text(col['sat'], y_th + 9, 'SAT', 9.5, True, MUTE)
    p.right(col['har'], y_th + 9, 'HARGA', 9.5, True, MUTE)
    p.right(col['jum'], y_th + 9, 'JUMLAH', 9.5, True, MUTE)
    p.text(col['pan'], y_th + 9, 'PANDUAN', 9.5, True, MUTE)
    p.hline(0, W, y_th + 30)

    yy = y_rows
    # komponen 1
    p.rect(0, yy, W, 30, fill=SOFT)
    p.rect(20, yy + 11, 9, 9, fill=BRAND2, r=2.5)
    p.text(36, yy + 8, 'Komponen 1 - Pengembangan Perpustakaan', 11.5, True)
    p.text(36 + p.tw('Komponen 1 - Pengembangan Perpustakaan', 11.5, True) + 10, yy + 8,
           '- 8,4% dari pagu', 10.5, color=MUTE)
    p.hline(0, W, yy + 30)
    yy += 30

    # kegiatan
    p.text(52, yy + 8, 'Kegiatan: Pengadaan Buku Pengayaan', 11.5, color='#334155')
    p.hline(0, W, yy + 30)
    yy += 30

    # warn 2 baris (uraian > 500)
    p.rect(0, yy, W, 46, fill='#fff7ed')
    p.text(70, yy + 7, 'Buku pengayaan literasi', 11.5)
    kode = '5.2.02.01.01.0024'
    p.text(70 + p.tw('Buku pengayaan literasi', 11.5) + 8, yy + 8, kode, 10.0, mono=True, color=MUTE)
    p.text(70, yy + 26, 'Uraian 612 karakter - maksimal 500', 10.5, True, ROSE)
    p.right(col['vol'], yy + 7, '240', 11.5)
    p.text(col['sat'], yy + 7, 'eks', 11.5)
    p.right(col['har'], yy + 7, '62.000', 11.5)
    p.right(col['jum'], yy + 7, '14.880.000', 11.5, mono=True)
    p.text(col['pan'], yy + 7, 'Juknis', 10.5, color=BLUE)
    p.text(col['aks'], yy + 7, '...', 11.5, color=MUTE)
    p.hline(0, W, yy + 46)
    yy += 46

    # komponen 2
    p.rect(0, yy, W, 30, fill=SOFT)
    p.rect(20, yy + 11, 9, 9, fill=VIOLET, r=2.5)
    p.text(36, yy + 8, 'Komponen 2 - Kegiatan Pembelajaran dan Ekstrakurikuler', 11.5, True)
    p.text(36 + p.tw('Komponen 2 - Kegiatan Pembelajaran dan Ekstrakurikuler', 11.5, True) + 10,
           yy + 8, '- 41,2% dari pagu', 10.5, color=MUTE)
    p.hline(0, W, yy + 30)
    yy += 30

    # warn 2 baris (harga > batas)
    p.rect(0, yy, W, 46, fill='#fff7ed')
    p.text(70, yy + 7, 'Honor narasumber pelatihan', 11.5)
    p.text(70 + p.tw('Honor narasumber pelatihan', 11.5) + 8, yy + 8, '5.2.02.01.01.0003',
           10.0, mono=True, color=MUTE)
    p.text(70, yy + 26, 'Rp 1.850.000 > batas Dinas Rp 1.500.000 / OJ', 10.5, True, ROSE)
    p.right(col['vol'], yy + 7, '4', 11.5)
    p.text(col['sat'], yy + 7, 'OJ', 11.5)
    p.right(col['har'], yy + 7, '1.850.000', 11.5, color=ROSE)
    p.right(col['jum'], yy + 7, '7.400.000', 11.5, mono=True)
    p.text(col['pan'], yy + 7, 'Juknis', 10.5, color=BLUE)
    p.text(col['aks'], yy + 7, '...', 11.5, color=MUTE)
    p.hline(0, W, yy + 46)
    yy += 46

    # flash (baru disimpan)
    p.rect(0, yy, W, 30, fill='#fef9c3')
    p.text(70, yy + 8, 'Alat tulis kantor - baru disimpan', 11.5)
    p.text(70 + p.tw('Alat tulis kantor - baru disimpan', 11.5) + 8, yy + 9, '5.2.02.01.01.0055',
           10.0, mono=True, color=MUTE)
    p.right(col['vol'], yy + 8, '12', 11.5)
    p.text(col['sat'], yy + 8, 'pak', 11.5)
    p.right(col['har'], yy + 8, '85.000', 11.5)
    p.right(col['jum'], yy + 8, '1.020.000', 11.5, mono=True)
    p.text(col['pan'], yy + 8, 'Juknis', 10.5, color=BLUE)
    p.text(col['aks'], yy + 8, '...', 11.5, color=MUTE)
    p.hline(0, W, yy + 30)
    yy += 30

    # warn 1 baris (belum dianggarkan)
    p.rect(0, yy, W, 46, fill='#fff7ed')
    p.text(70, yy + 7, 'CADANGAN - belum dirinci', 11.5)
    p.text(70, yy + 26, 'Rp 24.500.000 belum dialokasikan', 10.5, True, ROSE)
    p.right(col['jum'], yy + 7, '24.500.000', 11.5, mono=True)
    p.btn(col['aks'], yy + 10, '+ Masukkan', h=22, size=10.0, right=True)
    p.hline(0, W, yy + 46)

    # footer
    p.text(20, y_foot + 20, 'Autosave lokal aktif - perubahan belum dikirim ke Dinas', 11.0, color=MUTE)
    x = W - 20
    for lab in ['Perbarui Data Kegiatan', '+ Tambah Kegiatan', 'Sisip Uraian']:
        w = p.btn(x, y_foot + 14, lab, right=True)
        x -= w + 8
    p.save(path)


# =====================================================================
# PANEL 3 — lembar BKU
# =====================================================================
def panel3(path):
    TOP = 42
    y_head = TOP
    y_tabs = y_head + 104
    y_bar = y_tabs + 42
    y_th = y_bar + 34
    y_rows = y_th + 30
    y_foot = y_rows + 5 * 30
    y_end = y_foot + 54

    p = P(1200, y_end + 8)
    judul(p, '3', 'Lembar BKU: semua aksi di tempat yang sama', '· tab Jan-Des, tarik/setor, badge pajak (P1-5)')
    p.frame_top(TOP, y_end - TOP)

    p.text(20, y_head + 16, 'BOSP Reguler - BKU 2026', 15.0, True)
    p.text(20, y_head + 38, 'Bendahara: Siti R. - SDN 1 Contoh', 10.5, color=MUTE)
    p.badge(20, y_head + 58, 'Juni belum ditutup', 'amber')

    for x, lab, val in [(420, 'DANA TERSEDIA', 96240000), (620, 'SERAPAN JAN-JUN', None)]:
        p.text(x, y_head + 22, lab, 9.5, True, MUTE)
        p.text(x, y_head + 40, rp(val) if val else '41,7%', 18.0, True)
    p.bar(620, y_head + 68, 180, .417)

    x = W - 20
    w = p.btn(x, y_head + 34, '+ Tambah Pembelanjaan', primary=True, right=True, w=165)
    x -= w + 8
    w = p.btn(x, y_head + 34, 'Setor Tunai', right=True)
    x -= w + 8
    p.btn(x, y_head + 34, 'Tarik Tunai', right=True)
    p.hline(0, W, y_tabs)

    # tabs
    bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
    for i, b in enumerate(bulan):
        x = 14 + i * 46
        on = (i == 5)
        if on:
            p.rect(x, y_tabs, 44, 42, fill=CARD)
            p.rect(x + 4, y_tabs + 38, 36, 2.5, fill=BRAND, r=1)
        p.mid(x + 22, y_tabs + 14, b, 11.5, on, BRAND if on else MUTE)
    x = W - 20
    w = p.btn(x, y_tabs + 9, 'Tutup BKU Juni', right=True, h=26, size=11.0, stroke='#fecdd3')
    p.right(x - w - 10, y_tabs + 15, 'Cetak', 11.0, color=MUTE)
    p.right(x - w - 66, y_tabs + 15, 'Cari', 11.0, color=MUTE)
    p.rect(0, y_tabs, W, 42, stroke=LINE)
    p.hline(0, W, y_tabs + 42)
    p.text(20, y_bar + 11, '5 transaksi - 1 pajak wajib lapor - saldo akhir Rp 96.235.500', 10.5, color=MUTE)
    p.badge(W - 20, y_bar + 9, 'DRAFT - Juni belum ditutup', 'rose', right=True)

    # header tabel
    p.rect(0, y_th, W, 30, fill=SOFT)
    c = dict(tgl=20, ur=90, pen=780, peng=920, sal=1060, paj=1080)
    p.text(c['tgl'], y_th + 9, 'TGL', 9.5, True, MUTE)
    p.text(c['ur'], y_th + 9, 'URAIAN', 9.5, True, MUTE)
    p.right(760, y_th + 9, 'PENERIMAAN', 9.5, True, MUTE)
    p.right(900, y_th + 9, 'PENGELUARAN', 9.5, True, MUTE)
    p.right(1040, y_th + 9, 'SALDO', 9.5, True, MUTE)
    p.text(1060, y_th + 9, 'PAJAK', 9.5, True, MUTE)
    p.hline(0, W, y_th + 30)

    data = [
        ('01-06', 'Saldo bulan lalu', None, None, 104300000, None, None),
        ('03-06', 'Belanja ATK - Toko Berkah', None, 1020000, 103280000, ('wajib lapor', 'rose'), True),
        ('05-06', 'Tarik tunai bank -> kas', None, 5000000, 98280000, None, False),
        ('08-06', 'Belanja cetak modul - SIPLah', None, 2040000, 96240000, ('terlaporkan', 'green'), False),
        ('30-06', 'Pajak Bunga Bank', None, 4500, 96235500, None, False),
    ]
    yy = y_rows
    for tgl, ur, pen, peng, sal, badge, flash in data:
        if flash:
            p.rect(0, yy, W, 30, fill='#fef9c3')
        p.text(c['tgl'], yy + 8, tgl, 11.0, mono=True, color=MUTE)
        p.text(c['ur'], yy + 8, ur, 11.5)
        p.right(760, yy + 8, '-' if pen is None else angka(pen), 11.5)
        p.right(900, yy + 8, '-' if peng is None else angka(peng), 11.5)
        p.right(1040, yy + 8, angka(sal), 11.5, True, INK, mono=True)
        if badge:
            p.badge(1060, yy + 5, badge[0], badge[1])
        p.hline(0, W, yy + 30)
        yy += 30

    p.text(20, y_foot + 20, 'Watermark DRAFT aktif sampai BKU Juni ditutup', 11.0, color=MUTE)
    p.btn(W - 20, y_foot + 14, 'Hapus BKU (alasan + jejak log)', right=True, size=11.0)
    p.save(path)


# =====================================================================
# PANEL 4 — wizard belanja 3 langkah
# =====================================================================
def panel4(path):
    TOP = 42
    y_steps = TOP
    y_chk = y_steps + 54
    y_f1 = y_chk + 40
    y_f2 = y_f1 + 78
    y_foot = y_f2 + 70
    y_end = y_foot + 54

    p = P(1200, y_end + 8)
    judul(p, '4', 'Wizard belanja 3 langkah', '· urutan mengikuti ARKAS 4 (P1-7)')
    p.frame_top(TOP, y_end - TOP)

    # steps
    p.rect(0, y_steps, W, 54, fill=SOFT)
    labels = ['Detail Transaksi', 'Kegiatan & Rincian', 'Perhitungan Pajak']
    x = 20
    for i, lab in enumerate(labels):
        tw = p.tw(lab, 11.5, bold=(i == 0)) + 46
        if i == 0:
            p.rect(x, y_steps + 13, tw, 28, fill=CARD, stroke=BRAND, r=14, lw=1)
            p.d.ellipse([(x + 10) * S, (y_steps + 20) * S, (x + 28) * S, (y_steps + 34) * S], fill=BRAND)
            p.mid(x + 19, y_steps + 19, str(i + 1), 9.5, True, '#ffffff')
            p.text(x + 34, y_steps + 20, lab, 11.5, True, '#0b3b36')
        else:
            p.rect(x, y_steps + 13, tw, 28, fill=CARD, stroke=LINE, r=14)
            p.d.ellipse([(x + 10) * S, (y_steps + 19) * S, (x + 28) * S, (y_steps + 35) * S], fill='#eef2f7')
            p.mid(x + 19, y_steps + 19, str(i + 1), 9.5, True, MUTE)
            p.text(x + 34, y_steps + 20, lab, 11.5, color=MUTE)
        x += tw + 10
    p.hline(0, W, y_chk)

    # checkbox SIPLah
    p.rect(20, y_chk + 12, 13, 13, fill=BRAND, r=3)
    p.text(24.5, y_chk + 12.5, '\u2713', 8.5, True, '#ffffff')
    p.text(40, y_chk + 10, 'Belanja melalui SIPLah', 11.5, True)
    p.text(40 + p.tw('Belanja melalui SIPLah', 11.5, True) + 8, y_chk + 11,
           '- invoice ditarik otomatis, pajak wajib lapor dinolkan', 10.5, color=MUTE)
    p.hline(0, W, y_f1 - 4)

    cw = (W - 40 - 3 * 12) / 4
    colval = [
        [('Tanggal transaksi', '03-06-2026', 'diposting ke bulan pelunasan'),
         ('Metode bayar', 'Non-tunai (bank)', None),
         ('Nama toko / penyedia', 'Toko Berkah', None),
         ('Alamat', 'Jl. Pendidikan 12', None)],
        [('Telepon', '0812-3456-7890', None),
         ('NPWP (15) / NIK (16)', '00.000.000.0-000.000', 'validasi panjang otomatis'),
         ('Usaha perseorangan', '', None),
         ('Lampiran nota', 'nota-0123.pdf', None)],
    ]
    for r, rowitems in enumerate(colval):
        yy = y_f1 + r * 78
        for i, (lab, val, hint) in enumerate(rowitems):
            x = 20 + i * (cw + 12)
            p.text(x, yy, lab, 11.0, True, '#475569')
            if lab == 'Usaha perseorangan':
                p.rect(x, yy + 18, cw, 32, fill=CARD, stroke=LINE, r=8)
                p.rect(x + 10, yy + 27, 13, 13, fill=CARD, stroke='#94a3b8', r=3)
                p.text(x + 30, yy + 27, 'tanpa NPWP', 11.5, color=INK)
            else:
                p.inp(x, yy + 18, cw, val)
            if hint:
                p.text(x, yy + 54, hint, 9.5, color=MUTE)

    p.text(20, y_foot + 20, 'Langkah 1 dari 3', 11.0, color=MUTE)
    x = W - 20
    w = p.btn(x, y_foot + 14, 'Lanjut: Kegiatan & Rincian', primary=True, right=True, w=210)
    p.btn(x - w - 8, y_foot + 14, 'Batal', right=True)
    p.save(path)


# =====================================================================
# PANEL 5 — arsip terpadu + pratinjau cetak
# =====================================================================
def panel5(path):
    TOP = 42
    # arsip
    y_a = TOP
    y_filt = y_a + 44
    y_ath = y_filt + 84
    y_arows = y_ath + 30
    y_aend = y_arows + 4 * 34
    # cetak
    y_c = y_aend + 48
    y_ctool = y_c + 72
    y_cend = y_ctool + 230

    p = P(1200, y_cend + 10)
    judul(p, '5', 'Arsip terpadu & cetak berformat', '· 3 filter, 2 mode, watermark bersama (P0-3 / P1-6)')

    # ---------- arsip ----------
    p.frame_top(y_a, y_aend - y_a)
    p.text(20, y_a + 14, 'Arsip Dokumen Sah', 13.5, True)
    p.text(20 + p.tw('Arsip Dokumen Sah', 13.5, True) + 10, y_a + 17,
           '- hanya dokumen disahkan / BKU ditutup', 11.0, color=MUTE)
    p.btn(W - 20, y_a + 10, 'Cetak daftar arsip', right=True, h=26, size=11.0)
    p.hline(0, W, y_a + 44)

    p.rect(0, y_filt, W, 84, fill=SOFT)
    fw = (W - 40 - 3 * 12) / 4
    filters = [
        ('Jenis dokumen', 'BKU (ditutup)'),
        ('Sumber dana', 'Semua'),
        ('Tahun', '2026'),
        ('Mode tampilan', None),
    ]
    for i, (lab, val) in enumerate(filters):
        x = 20 + i * (fw + 12)
        p.text(x, y_filt + 14, lab, 11.0, True, '#475569')
        if val:
            p.sel(x, y_filt + 32, fw, val)
        else:
            segw = (fw - 4) / 2
            p.rect(x, y_filt + 32, fw, 32, fill=CARD, stroke=LINE, r=8)
            p.rect(x + 2, y_filt + 34, segw, 28, fill=BRAND, r=7)
            p.mid(x + 2 + segw / 2, y_filt + 41, 'Akumulasi', 10.5, True, '#ffffff')
            p.mid(x + 2 + segw + segw / 2, y_filt + 41, 'per Sumber Dana', 10.5, color=MUTE)
    p.hline(0, W, y_ath)

    p.rect(0, y_ath, W, 30, fill=SOFT)
    ac = dict(dok=20, sum=470, per=650, sah=830, berk=1090)
    for x, lab, anc in [(ac['dok'], 'DOKUMEN', 'l'), (ac['sum'], 'SUMBER DANA', 'l'),
                        (ac['per'], 'PERIODE', 'l'), (ac['sah'], 'DISAHKAN / DITUTUP', 'l')]:
        if anc == 'l':
            p.text(x, y_ath + 9, lab, 9.5, True, MUTE)
    p.right(ac['berk'], y_ath + 9, 'BERKAS', 9.5, True, MUTE)
    p.hline(0, W, y_ath + 30)

    rows = [
        ('BKU Tahunan', 'BOSP Reguler', '2026', '-', '1 PDF'),
        ('BKU Juni', 'BOSP Reguler', 'Jun 2026', '28-06-2026', '1 PDF'),
        ('Realisasi Penerimaan & Belanja', 'Semua', 'Semester I', '10-07-2026', '1 PDF'),
        ('RKAS Disahkan', 'BOSP Kinerja', '2026', '14-02-2026', 'PDF + Excel'),
    ]
    yy = y_arows
    for dok, sumb, per, sah, berk in rows:
        p.text(ac['dok'], yy + 10, dok, 11.5, True)
        p.text(ac['sum'], yy + 10, sumb, 11.5, color='#334155')
        p.text(ac['per'], yy + 10, per, 11.5, color='#334155')
        p.text(ac['sah'], yy + 10, sah, 11.5, color=MUTE if sah == '-' else '#334155')
        p.right(ac['berk'], yy + 10, berk, 11.0, color=MUTE)
        p.btn(1176, yy + 6, 'Lihat', h=22, size=10.0, right=True)
        p.hline(0, W, yy + 34)
        yy += 34

    # ---------- cetak ----------
    judul_y = y_c - 20
    p.text(0, judul_y, 'Pratinjau cetak: format + periode + watermark', 12.5, True, MUTE)

    p.frame_top(y_c, y_cend - y_c)
    p.rect(0, y_c, W, 72, fill=SOFT)
    p.rect(0, y_c + 60, W, 12, fill=SOFT)
    p.hline(0, W, y_c + 72)
    p.text(20, y_c + 12, 'FORMAT', 9.5, True, MUTE)
    p.sel(20, y_c + 28, 260, 'BKU Tahunan')
    p.text(300, y_c + 12, 'PERIODE', 9.5, True, MUTE)
    p.sel(300, y_c + 28, 200, 'Tahun 2026 (Jan-Jun)')
    p.btn(W - 20, y_c + 26, 'Cetak A4', primary=True, right=True, w=95)
    p.btn(W - 20 - 95 - 8, y_c + 26, 'Pratinjau', right=True)

    # kertas
    px, py, pw, ph = 24, y_ctool + 14, W - 48, 200
    p.rect(px, py, pw, ph, fill=CARD, stroke='#cbd5e1', r=2)
    p.mid(px + pw / 2, py + 16, 'BUKU KAS UMUM', 11.5, True)
    p.mid(px + pw / 2, py + 32, 'SDN 1 CONTOH - TAHUN ANGGARAN 2026', 11.5, True)
    p.mid(px + pw / 2, py + 48, 'Sumber Dana: BOSP Reguler - Periode: Januari-Juni 2026', 10.0, color=MUTE)

    # tabel kertas (bergrid, menyerupai cetak)
    tx, ty, tw2 = px + 20, py + 68, pw - 40
    cb = [tx, tx + 78, tx + 330, tx + 450, tx + 570, tx + tw2]
    p.rect(tx, ty, tw2, 22, fill='#f1f5f9', stroke='#cbd5e1')
    p.text(cb[0] + 6, ty + 6, 'TGL', 9.0, True, MUTE)
    p.text(cb[1] + 6, ty + 6, 'URAIAN', 9.0, True, MUTE)
    p.right(cb[3] - 8, ty + 6, 'PENERIMAAN', 9.0, True, MUTE)
    p.right(cb[4] - 8, ty + 6, 'PENGELUARAN', 9.0, True, MUTE)
    p.right(cb[5] - 6, ty + 6, 'SALDO', 9.0, True, MUTE)
    paper = [('01-06-2026', 'Saldo bulan lalu', '-', '-', '104.300.000'),
             ('03-06-2026', 'Belanja ATK', '-', '1.020.000', '103.280.000'),
             ('08-06-2026', 'Belanja cetak modul (SIPLah)', '-', '2.040.000', '96.240.000')]
    yy = ty + 22
    for row in paper:
        p.hline(tx, tx + tw2, yy, '#cbd5e1')
        p.text(cb[0] + 6, yy + 7, row[0], 9.5, mono=True, color='#334155')
        p.text(cb[1] + 6, yy + 7, row[1], 9.5)
        p.right(cb[3] - 8, yy + 7, row[2], 9.5)
        p.right(cb[4] - 8, yy + 7, row[3], 9.5)
        p.right(cb[5] - 6, yy + 7, row[4], 9.5)
        yy += 24
    p.hline(tx, tx + tw2, yy, '#cbd5e1')
    for bx in cb[1:5]:
        p.d.line([bx * S, ty * S, bx * S, yy * S], fill='#cbd5e1', width=S)
    p.rect(tx, ty, tw2, yy - ty, stroke='#94a3b8')

    p.text(px + 20, py + ph - 22,
           'Dicetak dari SiapBOSP pada 26-10-2026 10:14 - bukan dokumen resmi ARKAS', 9.0, color=MUTE)
    p.right(px + pw - 20, py + ph - 22, 'Halaman 1 / 4', 9.0, color=MUTE)
    p.hline(px + 20, px + pw - 20, py + ph - 30, '#e2e8f0')

    p.watermark_in(px + 40, py + 74, pw - 80, 126, 'DRAFT', size=50,
                   color=(225, 29, 72, 34))
    p.save(path)


# =====================================================================
def gabung(files, out):
    imgs = [Image.open(f) for f in files]
    gap = 44 * S // 2
    total_w = max(i.width for i in imgs)
    total_h = sum(i.height for i in imgs) + gap * (len(imgs) + 1)
    sheet = Image.new('RGB', (total_w + gap * 2, total_h), '#e2e8f0')
    y = gap
    for im in imgs:
        sheet.paste(im, (gap, y))
        y += im.height + gap
    # kecilkan 50% (dari 2x -> 1x) agar file ringkas
    sheet = sheet.resize((sheet.width // 2, sheet.height // 2), Image.LANCZOS)
    sheet.save(out, optimize=True)
    print(f'  {out}  ({sheet.width}x{sheet.height})')


if __name__ == '__main__':
    base = os.path.join(os.path.dirname(os.path.abspath(__file__)))
    print('Render mockup:')
    f1 = os.path.join(base, '01-penganggaran-kartu.png')
    f2 = os.path.join(base, '02-kertas-kerja-gerbang.png')
    f3 = os.path.join(base, '03-bku.png')
    f4 = os.path.join(base, '04-wizard-belanja.png')
    f5 = os.path.join(base, '05-arsip-cetak.png')
    panel1(f1)
    panel2(f2)
    panel3(f3)
    panel4(f4)
    panel5(f5)
    gabung([f1, f2, f3, f4, f5], os.path.join(base, '00-semua-tampilan.png'))
    print('Selesai.')
