@php
    use App\Support\Terbilang;
    $kepsek = $penandatangan->first(fn($p) => str_contains(strtolower($p->jabatan ?? ''), 'kepala'));
    $bendahara = $penandatangan->first(fn($p) => str_contains(strtolower($p->jabatan ?? ''), 'bendahara'));
    $komite = $penandatangan->first(fn($p) => str_contains(strtolower($p->jabatan ?? ''), 'komite'));
    $grup = $h->details->groupBy(fn($d) => $d->rekening->komponen->nama ?? '(tanpa komponen)')->sortKeys();
    $total = (int) $h->details->sum('jumlah');
    $kota = $sekolah->kab_kota ?? 'Makassar';
    $gKom = $komite ? \App\Services\TtdGambar::dataUri($komite->ttd_path) : null;
    $gBen = $bendahara ? \App\Services\TtdGambar::dataUri($bendahara->ttd_path) : null;
    $gKep = $kepsek ? \App\Services\TtdGambar::dataUri($kepsek->ttd_path) : null;
    $penerimaan = $penerimaan ?? collect();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 1.4cm 1.6cm; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #111; }
  .center { text-align: center; }
  h1 { font-size: 13pt; margin: 3px 0; }
  h2 { font-size: 11pt; margin: 2px 0; }
  .garis { border-bottom: 3px double #000; margin: 5px 0 10px; }
  table.tb { width: 100%; border-collapse: collapse; margin-top: 6px; }
  table.tb th, table.tb td { border: 1px solid #333; padding: 3px 5px; vertical-align: top; }
  table.tb th { background: #e5e7eb; font-size: 8pt; text-align: center; }
  .r { text-align: right; }
  .c { text-align: center; }
  .grup { background: #dbeafe; font-weight: bold; text-transform: uppercase; }
  .subtotal { background: #f3f4f6; font-style: italic; font-weight: bold; }
  .grand { background: #fef3c7; font-weight: bold; }
  table.kv { font-size: 9pt; }
  table.kv td { padding: 1px 6px 1px 0; }
  .ttd { width: 100%; margin-top: 30px; border-collapse: collapse; }
  .ttd td { width: 33%; text-align: center; vertical-align: bottom; }
  .spasi-ttd { height: 55px; }
  .nama { font-weight: bold; text-decoration: underline; }
  .terbilang { font-style: italic; font-size: 8.5pt; margin-top: 8px; }
  .meta { font-size: 7.5pt; color: #555; margin-top: 14px; }
</style>
</head>
<body>
  <div class="center">
    <h2>PEMERINTAH {{ strtoupper($sekolah->kab_kota ?? 'KOTA MAKASSAR') }}</h2>
    <h2>DINAS PENDIDIKAN</h2>
    <h1>RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS)</h1>
    <h2>DANA BANTUAN OPERASIONAL SEKOLAH (BOS) — TAHUN ANGGARAN {{ $h->tahun }}</h2>
    <div class="garis"></div>
  </div>

  <table class="kv">
    <tr>
      <td style="width:14%;">Nama Sekolah</td><td style="width:36%;">: <b>{{ $sekolah->nama }}</b></td>
      <td style="width:14%;">No. RKAS</td><td>: {{ $h->no_rkas ?? '-' }}</td>
    </tr>
    <tr>
      <td>NPSN</td><td>: {{ $sekolah->npsn }}</td>
      <td>Revisi ke</td><td>: {{ $h->revisi_ke }}</td>
    </tr>
    <tr>
      <td>Alamat</td><td>: {{ $sekolah->alamat }} {{ $sekolah->desa }}</td>
      <td>Status</td><td>: {{ \App\Models\RkasHeader::LABEL_STATUS[$h->status] ?? $h->status }}{{ $h->ditinjau_oleh ? ' (oleh ' . $h->ditinjau_oleh . ')' : '' }}</td>
    </tr>
    <tr>
      <td>Kecamatan</td><td>: {{ $sekolah->kecamatan }}</td>
      <td>{{ $h->status === 'disetujui' || $h->dikirim_dinas_at ? 'Dikirim ke Dinas' : 'Catatan' }}</td>
      <td>: {{ $h->dikirim_dinas_at ? $h->dikirim_dinas_at->format('d/m/Y H:i') : ($h->catatan ?? '—') }}</td>
    </tr>
  </table>

  <table class="tb">
    <tr><th colspan="3">A. PENERIMAAN</th></tr>
    @forelse($penerimaan as $alokasi)
    <tr><td>{{ $alokasi->bosType->nama ?? '—' }}</td><td class="r">{{ number_format($alokasi->nilai, 0, ',', '.') }}</td><td></td></tr>
    @empty
    <tr><td colspan="3" class="c">Belum ada pagu tercatat tahun {{ $h->tahun }}.</td></tr>
    @endforelse
    <tr class="subtotal"><td class="r">Total Penerimaan</td><td class="r">{{ number_format($penerimaan->sum('nilai'), 0, ',', '.') }}</td><td></td></tr>
  </table>

  <table class="tb">
    <tr>
      <th style="width:3%;">No</th>
      <th style="width:8%;">Kode Rekening</th>
      <th style="width:8%;">Kode Kegiatan</th>
      <th style="width:14%;">Rekening</th>
      <th style="width:22%;">Uraian</th>
      <th style="width:6%;">Satuan</th>
      <th style="width:5%;">Banyak</th>
      <th style="width:9%;">Harga Satuan (Rp)</th>
      <th style="width:10%;">Jumlah (Rp)</th>
      <th style="width:9%;">Sumber Dana</th>
    </tr>
    @php $no = 1; @endphp
    @foreach($grup as $komponen => $details)
    <tr><td colspan="10" class="grup">{{ $komponen }}</td></tr>
      @foreach($details->sortBy(fn($d) => $d->rekening->kode ?? '') as $d)
    <tr>
      <td class="c">{{ $no++ }}</td>
      <td class="c">{{ $d->rekening->kode ?? '-' }}</td>
      <td class="c">{{ $d->kode_kegiatan ?? '-' }}</td>
      <td>{{ $d->rekening->nama ?? '-' }}</td>
      <td>{{ $d->uraian }}</td>
      <td class="c">{{ $d->satuan }}</td>
      <td class="c">{{ $d->banyak }}</td>
      <td class="r">{{ number_format($d->harga_satuan, 0, ',', '.') }}</td>
      <td class="r">{{ number_format($d->jumlah, 0, ',', '.') }}</td>
      <td>{{ $d->sumber_dana }}</td>
    </tr>
      @endforeach
    <tr class="subtotal"><td colspan="8" class="r">Subtotal {{ $komponen }}</td><td class="r">{{ number_format($details->sum('jumlah'), 0, ',', '.') }}</td><td></td></tr>
    @endforeach
    <tr class="grand"><td colspan="8" class="r">TOTAL RKAS</td><td class="r">{{ number_format($total, 0, ',', '.') }}</td><td></td></tr>
  </table>
  <div class="terbilang">Terbilang: <i>{{ ucfirst(Terbilang::of($total)) }}</i></div>

  <table class="ttd">
    <tr>
      <td>Mengetahui,<br>Komite Sekolah</td>
      <td></td>
      <td>{{ $kota }}, {{ now()->translatedFormat('d F Y') }}<br>Kepala Sekolah</td>
    </tr>
    <tr>
      <td class="spasi-ttd">@if($gKom)<img src="{{ $gKom }}" style="max-height:52px">@endif</td>
      <td class="spasi-ttd">@if($gBen)<img src="{{ $gBen }}" style="max-height:52px">@endif</td>
      <td class="spasi-ttd">@if($gKep)<img src="{{ $gKep }}" style="max-height:52px">@endif</td>
    </tr>
    <tr>
      <td><span class="nama">{{ $komite->nama ?? '( ........................... )' }}</span><br>Komite Sekolah</td>
      <td><span class="nama">{{ $bendahara->nama ?? '' }}</span><br>{{ $bendahara ? 'Bendahara BOS<br>NIP. ' . ($bendahara->nip ?? '...........................') : '' }}</td>
      <td><span class="nama">{{ $kepsek->nama ?? '( ........................... )' }}</span><br>NIP. {{ $kepsek->nip ?? '...........................' }}</td>
    </tr>
  </table>
  <div class="meta">Dokumen ini dihasilkan oleh aplikasi SiapBOSP pada {{ now()->translatedFormat('d F Y H:i') }} WITA · RKAS TA {{ $h->tahun }} revisi ke-{{ $h->revisi_ke }} · {{ $sekolah->nama }}</div>
</body>
</html>
