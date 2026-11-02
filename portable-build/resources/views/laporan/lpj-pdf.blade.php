@php
    $kepsek = $penandatangan->first(fn($p) => str_contains(strtolower($p->jabatan ?? ''), 'kepala'));
    $bendahara = $penandatangan->first(fn($p) => str_contains(strtolower($p->jabatan ?? ''), 'bendahara'));
    $saldoAkhir = $saldoAwal + $totalMasuk - $totalBelanja;
    $kota = $sekolah->kab_kota ?? 'Makassar';
    $gKep = $kepsek ? \App\Services\TtdGambar::dataUri($kepsek->ttd_path) : null;
    $gBen = $bendahara ? \App\Services\TtdGambar::dataUri($bendahara->ttd_path) : null;
    $namaTahap = $cakupan ?? $stage->nama;
    $ketSaldo = $cakupan ? 'Saldo awal tahun (SILPA)' : 'Saldo awal tahap (sisa dana tahap sebelumnya)';
    $ketTerima = $cakupan ? 'Penerimaan dana BOS (kumulatif Tahap 1 s.d. tahap ini)' : 'Penerimaan dana BOS tahap ini';
    use App\Support\Terbilang;
    // Rincian belanja dikelompokkan per rekening
    $perRekDetail = [];
    foreach ($items->where('type', 'belanja') as $t) {
        foreach ($t->details as $d) {
            $perRekDetail[$d->rekening_id]['rekening'] = $d->rekening;
            $perRekDetail[$d->rekening_id]['rows'][] = ['trx' => $t, 'nilai' => $d->nilai, 'uraian' => $d->uraian ?? $t->keterangan];
        }
    }
    $perRekDetail = collect($perRekDetail)->sortBy(fn($g) => $g['rekening']->kode ?? 'z');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 1.6cm 1.8cm; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #111; }
  .page { page-break-after: always; }
  .page:last-child { page-break-after: avoid; }
  .center { text-align: center; }
  h1 { font-size: 15pt; margin: 4px 0; }
  h2 { font-size: 12pt; margin: 3px 0; }
  h3 { font-size: 10.5pt; margin: 14px 0 6px; }
  .garis { border-bottom: 3px double #000; margin: 6px 0 14px; }
  table.tb { width: 100%; border-collapse: collapse; margin-top: 6px; }
  table.tb th, table.tb td { border: 1px solid #333; padding: 3px 5px; vertical-align: top; }
  table.tb th { background: #e5e7eb; font-size: 8.5pt; text-align: center; }
  table.tb td.r, table.tb th.r { text-align: right; }
  table.tb td.c { text-align: center; }
  table.kv td { padding: 2px 4px; }
  .ttd { width: 100%; margin-top: 26px; border-collapse: collapse; }
  .ttd td { width: 50%; text-align: center; vertical-align: bottom; padding: 0 10px; }
  .spasi-ttd { height: 58px; }
  .nama { font-weight: bold; text-decoration: underline; }
  .total-row td { font-weight: bold; background: #f3f4f6; }
  .muted { color: #555; font-size: 8pt; }
  .kop { letter-spacing: .5px; }
</style>
</head>
<body>

{{-- ============ HALAMAN 1: SAMPUL ============ --}}
<div class="page">
  <div class="center" style="margin-top: 70px;">
    <div class="kop">
      <h2>PEMERINTAH {{ strtoupper($sekolah->kab_kota ?? 'KOTA MAKASSAR') }}</h2>
      <h2>DINAS PENDIDIKAN</h2>
    </div>
    <div style="margin: 46px 0;">
      <h1>LAPORAN PERTANGGUNGJAWABAN (LPJ)</h1>
      <h1>PENGGUNAAN DANA BANTUAN OPERASIONAL SEKOLAH (BOS)</h1>
      <h2 style="margin-top: 20px;">{{ strtoupper($namaTahap) }} — TAHUN ANGGARAN {{ $tahun }}</h2>
    </div>
    <table class="kv" style="margin: 0 auto; font-size: 11pt;">
      <tr><td style="text-align:left;">Nama Sekolah</td><td>:</td><td style="text-align:left;"><b>{{ $sekolah->nama }}</b></td></tr>
      <tr><td style="text-align:left;">NPSN</td><td>:</td><td style="text-align:left;">{{ $sekolah->npsn }}</td></tr>
      <tr><td style="text-align:left;">Alamat</td><td>:</td><td style="text-align:left;">{{ $sekolah->alamat }} {{ $sekolah->desa }}</td></tr>
      <tr><td style="text-align:left;">Kecamatan</td><td>:</td><td style="text-align:left;">{{ $sekolah->kecamatan }}</td></tr>
      <tr><td style="text-align:left;">Kota/Kabupaten</td><td>:</td><td style="text-align:left;">{{ $sekolah->kab_kota }}</td></tr>
      <tr><td style="text-align:left;">Provinsi</td><td>:</td><td style="text-align:left;">{{ $sekolah->provinsi }}</td></tr>
    </table>
    <div style="margin-top: 90px; font-size: 11pt;">
      <b>{{ strtoupper($sekolah->kab_kota ?? 'KOTA MAKASSAR') }}, TAHUN {{ $tahun }}</b>
    </div>
  </div>
</div>

{{-- ============ HALAMAN 2: PERNYATAAN & PENGESAHAN ============ --}}
<div class="page">
  <div class="center">
    <h2>SURAT PERNYATAAN TANGGUNG JAWAB BELANJA (SPTB)</h2>
    <div class="garis"></div>
  </div>
  <p>Yang bertanda tangan di bawah ini, Kepala {{ $sekolah->nama }}, menyatakan dengan sesungguhnya bahwa pelaksanaan
     penggunaan dana BOS Reguler <b>{{ $namaTahap }} Tahun Anggaran {{ $tahun }}</b> telah dilaksanakan sesuai dengan RKAS
     dan ketentuan petunjuk teknis BOS yang berlaku, dengan perincian sebagai berikut:</p>
  <table class="tb">
    <tr><th style="width:70%;">Uraian</th><th>Jumlah (Rp)</th></tr>
    <tr><td>{{ $ketSaldo }}</td><td class="r">{{ number_format($saldoAwal, 0, ',', '.') }}</td></tr>
    <tr><td>{{ $ketTerima }}</td><td class="r">{{ number_format($totalMasuk, 0, ',', '.') }}</td></tr>
    <tr><td>Belanja pada {{ $cakupan ? 'periode kumulatif' : 'tahap ini' }}</td><td class="r">{{ number_format($totalBelanja, 0, ',', '.') }}</td></tr>
    <tr class="total-row"><td>{{ $cakupan ? 'Saldo akhir (kumulatif)' : 'Saldo akhir tahap' }}</td><td class="r">{{ number_format($saldoAkhir, 0, ',', '.') }}</td></tr>
    <tr><td colspan="2"><i>Terbilang: {{ ucfirst(Terbilang::of($totalBelanja)) }}</i> (jumlah belanja {{ $cakupan ? 'kumulatif' : 'tahap ini' }})</td></tr>
  </table>
  <p>Apabila di kemudian hari terdapat kekeliruan dalam pernyataan ini, kami bersedia bertanggung jawab
     sesuai ketentuan peraturan perundang-undangan.</p>
  <table class="ttd">
    <tr>
      <td>Mengetahui,<br>Kepala Sekolah</td>
      <td>{{ $kota }}, {{ now()->translatedFormat('d F Y') }}<br>Bendahara BOS</td>
    </tr>
    <tr>
      <td class="spasi-ttd">@if($gKep)<img src="{{ $gKep }}" style="max-height:55px">@endif</td>
      <td class="spasi-ttd">@if($gBen)<img src="{{ $gBen }}" style="max-height:55px">@endif</td>
    </tr>
    <tr>
      <td><span class="nama">{{ $kepsek->nama ?? '( ........................... )' }}</span><br>
          NIP. {{ $kepsek->nip ?? '...........................' }}</td>
      <td><span class="nama">{{ $bendahara->nama ?? '( ........................... )' }}</span><br>
          NIP. {{ $bendahara->nip ?? '...........................' }}</td>
    </tr>
  </table>
</div>

{{-- ============ HALAMAN 3: BKU ============ --}}
<div class="page">
  <div class="center">
    <h2>BUKU KAS UMUM (BKU)</h2>
    <h3 style="margin:2px 0;">{{ $namaTahap }} Tahun {{ $tahun }} — {{ $sekolah->nama }}</h3>
    <div class="garis"></div>
  </div>
  <table class="tb">
    <tr>
      <th style="width:4%;">No</th><th style="width:9%;">Tanggal</th><th style="width:7%;">No BKU</th>
      <th style="width:12%;">No Bukti</th><th>Uraian</th>
      <th style="width:12%;">Masuk (Rp)</th><th style="width:12%;">Keluar (Rp)</th><th style="width:13%;">Saldo (Rp)</th>
    </tr>
    <tr><td colspan="7"><b>Saldo awal tahap</b></td><td class="r"><b>{{ number_format($saldoAwal, 0, ',', '.') }}</b></td></tr>
    @foreach($bkuRows as $i => $row)
    <tr>
      <td class="c">{{ $i + 1 }}</td>
      <td class="c">{{ $row['trx']->tanggal->format('d/m/Y') }}</td>
      <td class="c">{{ $row['trx']->no_bku }}</td>
      <td>{{ $row['trx']->no_bukti ?? '-' }}</td>
      <td>{{ $row['trx']->labelType() }} — {{ Str::limit($row['trx']->keterangan, 80) }}</td>
      <td class="r">{{ $row['masuk'] ? number_format($row['masuk'], 0, ',', '.') : '' }}</td>
      <td class="r">{{ $row['keluar'] ? number_format($row['keluar'], 0, ',', '.') : '' }}</td>
      <td class="r">{{ number_format($row['saldo'], 0, ',', '.') }}</td>
    </tr>
    @endforeach
    <tr class="total-row">
      <td colspan="5">TOTAL TAHAP INI</td>
      <td class="r">{{ number_format($totalMasuk, 0, ',', '.') }}</td>
      <td class="r">{{ number_format($totalKeluar, 0, ',', '.') }}</td>
      <td class="r">{{ number_format($saldoAkhir, 0, ',', '.') }}</td>
    </tr>
  </table>
</div>

{{-- ============ HALAMAN 4: K7 REKAPITULASI ============ --}}
<div class="page">
  <div class="center">
    <h2>KERTAS KERJA REALISASI BELANJA PER REKENING (K7)</h2>
    <h3 style="margin:2px 0;">{{ $namaTahap }} Tahun {{ $tahun }} — {{ $sekolah->nama }}</h3>
    <div class="garis"></div>
  </div>
  <table class="tb">
    <tr><th style="width:5%;">No</th><th style="width:14%;">Kode Rekening</th><th>Uraian Belanja</th><th style="width:22%;">Komponen</th><th style="width:16%;">Realisasi (Rp)</th></tr>
    @foreach($rekapRekening as $i => $rek)
    <tr>
      <td class="c">{{ $i + 1 }}</td>
      <td class="c">{{ $rek['rekening']->kode ?? '-' }}</td>
      <td>{{ $rek['rekening']->nama ?? '-' }}</td>
      <td>{{ $rek['rekening']->komponen->nama ?? '-' }}</td>
      <td class="r">{{ number_format($rek['jumlah'], 0, ',', '.') }}</td>
    </tr>
    @endforeach
    <tr class="total-row"><td colspan="4">TOTAL BELANJA {{ strtoupper($namaTahap) }}</td><td class="r">{{ number_format($totalBelanja, 0, ',', '.') }}</td></tr>
  </table>

  <h3>Rincian Belanja per Rekening</h3>
  @foreach($perRekDetail as $g)
  <table class="tb" style="margin-top:10px;">
    <tr>
      <th colspan="4" style="text-align:left; background:#dbeafe;">
        {{ $g['rekening']->kode ?? '' }} — {{ $g['rekening']->nama ?? '' }}
        ({{ $g['rekening']->komponen->nama ?? '' }})
      </th>
    </tr>
    <tr><th style="width:11%;">Tanggal</th><th style="width:14%;">No Bukti</th><th>Uraian</th><th style="width:16%;">Nilai (Rp)</th></tr>
    @php $sub = 0; @endphp
    @foreach($g['rows'] as $rd)
    <tr>
      <td class="c">{{ $rd['trx']->tanggal->format('d/m/Y') }}</td>
      <td>{{ $rd['trx']->no_bukti ?? '-' }}</td>
      <td>{{ Str::limit($rd['uraian'], 90) }}</td>
      <td class="r">{{ number_format($rd['nilai'], 0, ',', '.') }}</td>
    </tr>
    @php $sub += $rd['nilai']; @endphp
    @endforeach
    <tr class="total-row"><td colspan="3">Subtotal</td><td class="r">{{ number_format($sub, 0, ',', '.') }}</td></tr>
  </table>
  @endforeach

  <h3>Register Pajak</h3>
  <table class="tb">
    <tr><th>Jenis Pajak</th><th style="width:18%;">Dipungut (Rp)</th><th style="width:18%;">Disetor (Rp)</th><th style="width:18%;">Belum Disetor (Rp)</th></tr>
    @forelse($rekapPajak as $p)
    <tr>
      <td>{{ $p['nama'] }}</td>
      <td class="r">{{ number_format($p['dipungut'], 0, ',', '.') }}</td>
      <td class="r">{{ number_format($p['disetor'], 0, ',', '.') }}</td>
      <td class="r">{{ number_format($p['belum'], 0, ',', '.') }}</td>
    </tr>
    @empty
    <tr><td colspan="4" class="center muted">Tidak ada pajak dipungut pada tahap ini.</td></tr>
    @endforelse
    @if($rekapPajak)
    <tr class="total-row">
      <td>TOTAL</td>
      <td class="r">{{ number_format(array_sum(array_column($rekapPajak, 'dipungut')), 0, ',', '.') }}</td>
      <td class="r">{{ number_format(array_sum(array_column($rekapPajak, 'disetor')), 0, ',', '.') }}</td>
      <td class="r">{{ number_format(array_sum(array_column($rekapPajak, 'belum')), 0, ',', '.') }}</td>
    </tr>
    @endif
  </table>
  <p class="muted" style="margin-top:18px;">Dokumen ini dihasilkan oleh aplikasi SiapBOSP pada {{ now()->translatedFormat('d F Y H:i') }} WITA.</p>
</div>

</body>
</html>
