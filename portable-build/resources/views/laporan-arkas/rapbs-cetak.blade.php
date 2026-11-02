@extends('layouts.print', ['title' => 'Kertas Kerja RAPBS ' . $tahun])
@section('content')
@php
  $isBulan = $format === 'bulan';
  $isTahap = $format === 'tahap';
  $angka = fn ($v) => number_format((int) $v, 0, ',', '.');
  $rpTitik = fn ($v) => 'Rp. '.number_format((int) $v, 0, ',', '.');
  $judul = match ($format) {
      'bulan' => 'RINCIAN KERTAS KERJA PERBULAN',
      'triwulan' => 'KERTAS KERJA RENCANA KEGIATAN DAN ANGGARAN SEKOLAH  (RKAS) PER TRIWULAN',
      default => 'KERTAS KERJA RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS) PER TAHAP',
  };
  $kataKaki = match ($format) {
      'bulan' => 'perBulan',
      'triwulan' => 'perTriwulan',
      default => 'perTahap',
  };
  $indentasi = ['program' => '0', 'kegiatan' => '1.1rem', 'sub' => '2.2rem', 'rincian' => '3.3rem'];
  $provinsi = trim((string) ($sekolah->provinsi ?? ''));
  $provinsiTeks = $provinsi === '' ? '' : (stripos($provinsi, 'prov') === 0 ? $provinsi : 'Prov. '.$provinsi);
  $alamat = trim((string) ($sekolah->desa ?? ''));
  $alamatTeks = $alamat !== '' ? $alamat.', Kec. '.($sekolah->kecamatan ?? '') : (string) ($sekolah->kecamatan ?? '');
  $komite = $penandatangan->first(fn ($p) => stripos((string) ($p->jabatan ?? ''), 'komite') !== false);
  $kepsek = $penandatangan->first(fn ($p) => stripos((string) ($p->jabatan ?? ''), 'kepala') !== false);
  $bendahara = $penandatangan->first(fn ($p) => stripos((string) ($p->jabatan ?? ''), 'bendahara') !== false);
  $kakiKiri = trim(preg_replace('/[^\p{L}\p{N} .,\-\/:()]/u', ' ',
    'Kertas Kerja '.$kataKaki.' - NPSN : '.$sekolah->npsn.', Nama Sekolah : '.$sekolah->nama));
  $statusTeks = $draf ? 'KERTAS KERJA (DRAF) — BELUM DISAHKAN' : 'RKAS — DOKUMEN BUKAN DRAF';
  $warnaStatus = $draf ? '#b45309' : '#047857';
  $tautanFormat = fn (string $formatTujuan) => route('laporan-arkas.rapbs-cetak',
    array_filter(['format' => $formatTujuan, 'bulan' => $formatTujuan === 'bulan' ? $bulan : null,
      'draf' => $drafDiminta ? 1 : 0], fn ($v) => $v !== null));
@endphp

<div class="toolbar nonprint">
  <button type="button" data-cetak-halaman>🖨️ Cetak / Simpan PDF</button>
  <a href="{{ $tautanFormat('tahap') }}">Per Tahap</a>
  <a href="{{ $tautanFormat('triwulan') }}">Per Triwulan</a>
  <a href="{{ $tautanFormat('bulan') }}">Per Bulan</a>
  <a href="{{ route('laporan-arkas.rapbs-cetak', ['format' => $format, 'bulan' => $bulan, 'draf' => 1]) }}"
     class="{{ $drafDiminta ? 'font-bold' : '' }}">Kertas Kerja (Draf)</a>
  <a href="{{ route('laporan-arkas.rapbs-cetak', ['format' => $format, 'bulan' => $bulan, 'draf' => 0]) }}"
     class="{{ ! $drafDiminta ? 'font-bold' : '' }}">RKAS Disahkan</a>
  @if($isBulan)
    <form method="get" action="{{ route('laporan-arkas.rapbs-cetak') }}" style="display:flex;gap:.4rem;align-items:center">
      <input type="hidden" name="format" value="bulan">
      <label for="pilih-bulan" style="font-size:.82rem;font-weight:600">Bulan</label>
      <select id="pilih-bulan" name="bulan" data-kirim-otomatis
        style="border:1px solid #cbd5e1;border-radius:.5rem;padding:.35rem .5rem;font-size:.82rem;background:#fff;font-family:inherit">
        @foreach($namaBulan as $n => $nm)
          <option value="{{ $n }}" @selected((int) $n === (int) $bulan)>{{ $nm }}</option>
        @endforeach
      </select>
      <button type="submit">Terapkan</button>
    </form>
  @endif
  <a href="{{ route('laporan-arkas.rkas-tahap') }}">← Kembali ke Rincian RKAS</a>
</div>

{{-- Kop mengikuti lembar RAPBS ARKAS: judul, TAHUN ANGGARAN, lalu identitas
     label:nilai (Nama Sekolah / Alamat / Kabupaten / Provinsi / Tahap|Triwulan|
     Bulan) dengan NPSN di sisi kanan. --}}
<div style="text-align:center;font-weight:700;font-size:13px;letter-spacing:.02em">{{ $judul }}</div>
<div style="text-align:center;font-size:11px;margin-top:.15rem;font-weight:700;color:{{ $warnaStatus }}">{{ $statusTeks }}</div>
@if($draf && ! $drafDiminta)
  <div style="text-align:center;font-size:10px;color:#b45309">
    Belum ada RKAS disahkan pada tahun {{ $tahun }}; yang ditampilkan adalah kertas kerja draf terakhir.
  </div>
@endif
<div style="text-align:center;font-size:12px;margin-top:.1rem">TAHUN ANGGARAN : {{ $tahun }}</div>

<div style="display:flex;justify-content:space-between;gap:1rem;margin:.55rem 0 .6rem;font-size:11.5px;line-height:1.45">
  <table class="tbl" style="border:0;width:auto">
    <tbody>
      <tr><td style="border:0;padding-left:0;width:7.5rem">Nama Sekolah</td><td style="border:0">: {{ $sekolah->nama }}</td></tr>
      <tr><td style="border:0;padding-left:0">Alamat</td><td style="border:0">: {{ $alamatTeks !== '' ? $alamatTeks : '—' }}</td></tr>
      <tr><td style="border:0;padding-left:0">Kabupaten</td><td style="border:0">: {{ $sekolah->kab_kota }}</td></tr>
      <tr><td style="border:0;padding-left:0">Provinsi</td><td style="border:0">: {{ $provinsiTeks }}</td></tr>
      <tr>
        <td style="border:0;padding-left:0">{{ $labelKopPeriode }}</td>
        <td style="border:0">: {{ $labelPeriode }}</td>
      </tr>
      @if($isBulan)
        <tr><td style="border:0;padding-left:0">Sumber Dana</td><td style="border:0">: {{ $kop['sumber'].$kop['revisiLabel'] }}</td></tr>
      @endif
    </tbody>
  </table>
  <div style="white-space:nowrap;font-size:11.5px">NPSN : {{ $sekolah->npsn }}</div>
</div>

<h2 style="font-size:12.5px;margin:.6rem 0 .2rem">A. PENERIMAAN</h2>
<div style="font-size:11.5px;margin-bottom:.2rem">Sumber Dana :</div>
<table class="tbl">
  <thead>
    <tr>
      <th style="width:6rem">No. Kode</th>
      <th>Penerimaan</th>
      <th class="num" style="width:8rem">Jumlah</th>
    </tr>
  </thead>
  <tbody>
    @forelse($penerimaan as $p)
      <tr>
        <td style="font-family:monospace;font-size:11px">{{ $p['kode'] !== '' ? $p['kode'] : '—' }}</td>
        <td>{{ $p['nama'] }}</td>
        <td class="num">{{ $angka($p['nilai']) }}</td>
      </tr>
    @empty
      <tr><td colspan="3" class="text-center text-slate-400">Belum ada alokasi sumber dana untuk tahun ini.</td></tr>
    @endforelse
  </tbody>
  <tfoot>
    <tr style="font-weight:700">
      <td colspan="2" style="text-align:right">Total Penerimaan</td>
      <td class="num">{{ $angka($totalPenerimaan) }}</td>
    </tr>
  </tfoot>
</table>

<h2 style="font-size:12.5px;margin:1rem 0 .2rem">B. BELANJA</h2>
<table class="tbl" style="font-size:.72rem">
  <thead>
    <tr>
      <th rowspan="2" style="width:2.6rem">No.<br>Urut</th>
      <th rowspan="2" style="width:8rem">Kode<br>Rekening</th>
      <th rowspan="2" style="width:4.5rem">Kode<br>Program</th>
      <th rowspan="2">Uraian</th>
      <th colspan="3" style="text-align:center">Rincian Perhitungan</th>
      <th rowspan="2" class="num" style="width:6rem">Jumlah</th>
      @if($nPeriode > 0)
        <th colspan="{{ $nPeriode }}" style="text-align:center">{{ $labelKopPeriode }}</th>
      @endif
    </tr>
    <tr>
      <th class="num" style="width:4.5rem">Volume</th>
      <th style="width:4rem">Satuan</th>
      <th class="num" style="width:5.5rem">Tarif Harga</th>
      @if($nPeriode > 0)
        @for($p = 1; $p <= $nPeriode; $p++)
          <th class="num" style="width:5.5rem">{{ $p }}</th>
        @endfor
      @endif
    </tr>
  </thead>
  <tbody>
    @forelse($baris as $i => $b)
      <tr class="{{ $b['tipe'] === 'rincian' ? '' : 'bg-slate-50' }}">
        <td>{{ $i + 1 }}.</td>
        <td style="font-family:monospace;font-size:10.5px">{{ $b['rek'] }}</td>
        <td style="font-family:monospace;font-size:10.5px;text-align:right">{{ $b['tipe'] === 'rincian' ? '' : $b['kode'] }}</td>
        <td style="padding-left:{{ $indentasi[$b['tipe']] }}">{{ $b['uraian'] !== '' ? $b['uraian'] : '—' }}</td>
        <td class="num">{{ $b['volume'] !== null ? rtrim(rtrim(number_format((float) $b['volume'], 2, ',', '.'), '0'), ',') : '' }}</td>
        <td>{{ $b['satuan'] }}</td>
        <td class="num">{{ $b['tarif'] !== null ? $angka($b['tarif']) : '' }}</td>
        <td class="num" style="font-weight:600">{{ $angka($isBulan ? $b['bulan'] : $b['jumlah']) }}</td>
        @if($nPeriode > 0)
          @for($p = 1; $p <= $nPeriode; $p++)
            <td class="num">{{ $angka($b['periode'][$p] ?? 0) }}</td>
          @endfor
        @endif
      </tr>
    @empty
      <tr><td colspan="{{ 8 + $nPeriode }}" class="text-center text-slate-400">
        Belum ada rincian RKAS untuk tahun ini.
      </td></tr>
    @endforelse
  </tbody>
  <tfoot>
    <tr style="font-weight:700">
      <td colspan="{{ $isBulan ? 7 : 7 }}" style="text-align:right">Jumlah</td>
      <td class="num">{{ $angka($isBulan ? $totalBulan : $total) }}</td>
      @if($nPeriode > 0)
        @for($p = 1; $p <= $nPeriode; $p++)
          <td class="num">{{ $angka($totalPeriode[$p] ?? 0) }}</td>
        @endfor
      @endif
    </tr>
  </tfoot>
</table>

@if($isBulan || $format === 'triwulan')
  {{-- ARKAS: tiga kolom tanda tangan, tanggal di atas kolom kanan.
       Dipakai lembar per Bulan dan per Triwulan (rapbs-tri-output.pdf hlm. 11:
       Kepala Sekolah / Komite Sekolah / Bendahara Sekolah + tanggal). --}}
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:2rem;margin-top:1.5rem;font-size:11.5px;text-align:center">
    <div>
      <div style="margin-bottom:3.5rem">Komite Sekolah</div>
      <div style="font-weight:700">{{ $komite->nama ?? '( ……………………… )' }}</div>
      <div>{{ ! empty($komite?->nip) ? 'NIP: '.$komite->nip : '' }}</div>
    </div>
    <div>
      <div style="margin-bottom:3.5rem">Kepala Sekolah</div>
      <div style="font-weight:700">{{ $kepsek->nama ?? '( ……………………… )' }}</div>
      <div>NIP: {{ $kepsek->nip ?? '…' }}</div>
    </div>
    <div>
      <div style="margin-bottom:.2rem">{{ $tempatTtd }}, {{ $tanggalTtd }}</div>
      <div style="margin-bottom:3.3rem">Bendahara Sekolah</div>
      <div style="font-weight:700">{{ $bendahara->nama ?? '( ……………………… )' }}</div>
      <div>NIP: {{ $bendahara->nip ?? '…' }}</div>
    </div>
  </div>
@endif

<style>
  @media print {
    @page {
      @bottom-left { content: "{!! $kakiKiri !!}"; font-size: 8pt; color: #334155; }
    }
  }
</style>
@endsection
