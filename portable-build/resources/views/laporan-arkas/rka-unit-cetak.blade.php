@extends('layouts.print', ['title' => ($varian === 'dpa' ? 'DPA Unit Kerja ' : 'RKA Unit Kerja ') . $tahun])
@section('content')
@php
  $angka = fn ($v) => number_format((int) $v, 0, ',', '.');
  $romawi = [1 => 'I', 'II', 'III', 'IV'];
  $judulVarian = $varian === 'dpa'
    ? 'LEMBAR RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS)'
    : 'LEMBAR KERTAS KERJA';
  $pemerintah = trim((string) ($sekolah->kab_kota ?? '')) !== ''
    ? 'PEMERINTAH '.strtoupper($sekolah->kab_kota)
    : 'PEMERINTAH '.strtoupper((string) ($sekolah->provinsi ?? ''));
  $indentasi = ['5' => '0', '5.1' => '1.1rem', '5.2' => '1.1rem'];
@endphp

<div class="toolbar nonprint">
  <button type="button" data-cetak-halaman>🖨️ Cetak / Simpan PDF</button>
  <a href="{{ route('laporan-arkas.rka-unit', ['varian' => $varian, 'format' => 'triwulan']) }}">Per Triwulan</a>
  <a href="{{ route('laporan-arkas.rka-unit', ['varian' => $varian, 'format' => 'tahap']) }}">Per Tahap</a>
  <a href="{{ route('laporan-arkas.rka-unit', ['varian' => 'rka', 'format' => $format]) }}">RKA</a>
  <a href="{{ route('laporan-arkas.rka-unit', ['varian' => 'dpa', 'format' => $format]) }}">DPA (RKAS)</a>
  <a href="{{ route('laporan-arkas.rka-unit', ['varian' => 'rka22']) }}">RKA 2.2</a>
  <a href="{{ route('laporan-arkas.rka-unit', ['varian' => 'dpa22']) }}">DPA 2.2</a>
  <a href="{{ route('laporan-arkas.rkas-tahap') }}">← Rincian RKAS per Tahap</a>
</div>

<div style="text-align:center;line-height:1.35">
  <div style="font-weight:700;font-size:13px;letter-spacing:.04em">{{ $judulVarian }}</div>
  <div style="font-weight:700;font-size:13px;letter-spacing:.32em">UNIT KERJA</div>
  <div style="font-weight:700;font-size:12px;margin-top:.25rem">{{ $pemerintah }}</div>
  <div style="font-size:12px">TAHUN ANGGARAN {{ $tahun }}</div>
</div>

<table class="tbl" style="margin-top:.6rem;border:0">
  <tbody>
    <tr>
      <td style="border:0;width:11rem;padding-left:0">Urusan Pemerintahan</td>
      <td style="border:0">: 1.01 - PENDIDIKAN</td>
    </tr>
    <tr>
      <td style="border:0;padding-left:0">Organisasi</td>
      <td style="border:0">: {{ $sekolah->npsn }} - {{ strtoupper((string) $sekolah->nama) }}</td>
    </tr>
  </tbody>
</table>

<div style="text-align:center;font-weight:700;font-size:12px;margin:1rem 0 .1rem">
  Rincian Anggran Pendapatan dan Belanja
</div>
<div style="text-align:center;font-weight:700;font-size:12px;margin-bottom:.35rem">Unit Kerja</div>

<table class="tbl">
  <thead>
    <tr class="bku-nomor">
      <th style="width:2.6rem">1</th>
      <th>2</th>
      <th style="width:8rem">3</th>
    </tr>
    <tr>
      <th colspan="2" style="text-align:center">Kode Rekening &nbsp; Uraian</th>
      <th class="num">Jumlah (Rp)</th>
    </tr>
  </thead>
  <tbody>
    <tr style="font-weight:700">
      <td></td>
      <td>JUMLAH PENDAPATAN</td>
      <td class="num">{{ $penerimaan > 0 ? $angka($penerimaan) : '' }}</td>
    </tr>
    @foreach($akun as $kode => $nama)
      <tr>
        <td style="padding-left:{{ $indentasi[$kode] ?? '2.2rem' }}">{{ $kode }}</td>
        <td>{{ $nama }}</td>
        <td class="num">{{ $nilaiAkun[$kode] > 0 ? $angka($nilaiAkun[$kode]) : '' }}</td>
      </tr>
    @endforeach
    <tr style="font-weight:700">
      <td></td>
      <td>JUMLAH BELANJA</td>
      <td class="num">{{ $totalBelanja > 0 ? $angka($totalBelanja) : '' }}</td>
    </tr>
    <tr style="font-weight:700">
      <td></td>
      <td>DEFISIT</td>
      <td class="num">{{ $penerimaan > 0 || $totalBelanja > 0 ? $angka($defisit) : '' }}</td>
    </tr>
  </tbody>
</table>

<div style="text-align:center;font-weight:700;font-size:12px;margin:1rem 0 .1rem">
  Rencana Pelaksanaan Anggaran
</div>
<div style="text-align:center;font-weight:700;font-size:12px;margin-bottom:.35rem">
  Unit Kerja per {{ $labelPeriode }}
</div>

<table class="tbl">
  <thead>
    <tr>
      <th rowspan="2" style="width:2.6rem">No.</th>
      <th rowspan="2">Uraian</th>
      <th colspan="{{ $nPeriode }}" style="text-align:center">{{ $labelPeriode }}</th>
      <th rowspan="2" class="num" style="width:7rem">Jumlah</th>
    </tr>
    <tr>
      @for($p = 1; $p <= $nPeriode; $p++)
        <th class="num" style="width:6.5rem">{{ $romawi[$p] }}</th>
      @endfor
    </tr>
  </thead>
  <tbody>
    @foreach($barisPeriode as $b)
      <tr class="{{ $b['tebal'] ? 'font-semibold' : '' }}">
        <td style="text-align:right">{{ $b['no'] }}</td>
        <td>{{ $b['uraian'] }}</td>
        @for($p = 1; $p <= $nPeriode; $p++)
          <td class="num">{{ $angka($b['nilai'][$p] ?? 0) }}</td>
        @endfor
        <td class="num">{{ $angka($b['total']) }}</td>
      </tr>
    @endforeach
  </tbody>
</table>

<div style="display:flex;justify-content:space-between;align-items:flex-end;gap:2rem;margin-top:2rem;font-size:12px">
  <div>
    <div>Mengetahui,</div>
    <div>Kepala Sekolah</div>
    <div style="height:3.5rem"></div>
    <div style="font-weight:700;text-decoration:underline">{{ $kepsek->nama ?? '( …………………………… )' }}</div>
    <div>NIP : {{ $kepsek->nip ?? '…' }}</div>
  </div>
  <div style="text-align:left">{{ $tempatTtd }}, {{ $tanggalTtd }}</div>
</div>

<div style="font-size:10.5px;color:#64748b;margin-top:1.25rem">
  Subtotal akun dihitung dari kode rekening rincian RKAS sampai 4 segmen (batas yang
  dipakai ARKAS pada lembar ini). Angka per {{ strtolower($labelPeriode) }} berasal dari
  rencana bulanan rincian RKAS ({{ $format === 'tahap' ? '6 bulan per tahap' : '3 bulan per triwulan' }});
  pendapatan mengikuti tahap pencairan pada alokasi sumber dana.
</div>
@endsection
