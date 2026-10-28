@extends('layouts.print', ['title' => 'RKA Unit Kerja 2.2.1 ' . $tahun])
@section('content')
@php
  $angka = fn ($v) => number_format((int) $v, 0, ',', '.');
  $rp = fn ($v) => 'Rp. '.number_format((int) $v, 0, ',', '.');
  $kakiKiri = trim(preg_replace('/[^\p{L}\p{N} .,\-\/:()]/u', ' ',
    'RKAS Unit Kerja 2.2.1 - NPSN : '.$sekolah->npsn.', Nama Sekolah : '.$sekolah->nama));
@endphp

<div class="toolbar nonprint">
  <button type="button" data-cetak-halaman>🖨️ Cetak / Simpan PDF</button>
  <a href="{{ route('laporan-arkas.rka-unit', ['varian' => 'rka221']) }}">RKA 2.2.1</a>
  <a href="{{ route('laporan-arkas.rka-unit', ['varian' => 'dpa221']) }}">DPA 2.2.1</a>
  <a href="{{ route('laporan-arkas.rka-unit', ['varian' => 'rka22']) }}">RKA 2.2</a>
  <a href="{{ route('laporan-arkas.rkas-tahap') }}">← Rincian RKAS per Tahap</a>
</div>

<div style="text-align:center;line-height:1.35">
  <div style="font-weight:700;font-size:13px;letter-spacing:.04em">{{ $judulVarian }}</div>
  <div style="font-weight:700;font-size:13px;letter-spacing:.32em">UNIT KERJA</div>
  <div style="font-weight:700;font-size:12px;margin-top:.25rem">{{ $pemerintah }}</div>
  <div style="font-size:12px">TAHUN ANGGARAN {{ $tahun }}</div>
</div>

<table class="tbl" style="margin-top:.6rem;border:0;font-size:11px">
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

{{-- Blok identitas kegiatan, sama seperti kepala lembar ARKAS 2.2.1. --}}
<table class="tbl" style="margin-top:.5rem;border:0;font-size:11px">
  <tbody>
    <tr>
      <td style="border:0;width:10rem;padding-left:0">Program</td>
      <td style="border:0">: -</td>
    </tr>
    <tr>
      <td style="border:0;padding-left:0">Sub Program</td>
      <td style="border:0">:</td>
    </tr>
    <tr>
      <td style="border:0;padding-left:0">Lokasi Kegiatan</td>
      <td style="border:0">: {{ $lokasi }}</td>
    </tr>
    <tr>
      <td style="border:0;padding-left:0">Jumlah Tahun n-1</td>
      <td style="border:0">: {{ $rp(0) }},00</td>
    </tr>
    <tr>
      <td style="border:0;padding-left:0">Jumlah Tahun n</td>
      <td style="border:0">: {{ $rp($total) }}</td>
    </tr>
    <tr>
      <td style="border:0;padding-left:0">Jumlah Tahun n+1</td>
      <td style="border:0">: {{ $rp(0) }},00</td>
    </tr>
    <tr>
      <td style="border:0;padding-left:0">Kegiatan</td>
      <td style="border:0">: -</td>
    </tr>
  </tbody>
</table>

<div style="font-weight:700;font-size:11.5px;margin:1rem 0 .3rem">
  Indikator &amp; Tolok Ukur Kinerja Belanja Langsung
</div>
<table class="tbl" style="font-size:10.5px">
  <thead>
    <tr>
      <th style="width:30%">Indikator</th>
      <th>Tolok Ukur Kinerja</th>
      <th class="num" style="width:28%">Target Kinerja</th>
    </tr>
  </thead>
  <tbody>
    <tr><td>Capaian Program</td><td></td><td></td></tr>
    <tr><td>Masukan</td><td>Dana</td><td class="num">{{ $rp($total) }}</td></tr>
    <tr><td>Keluaran</td><td></td><td></td></tr>
    <tr><td>Hasil</td><td></td><td></td></tr>
    <tr><td>Kelompok Sasaran Kegiatan</td><td></td><td></td></tr>
  </tbody>
</table>

<div style="text-align:center;font-weight:700;font-size:11.5px;margin:1.1rem 0 .15rem">
  {{ $judulRincian }}
</div>
<div style="text-align:center;font-size:11px;margin-bottom:.35rem">
  Menurut Program dan Per Kegiatan Unit Kerja
</div>

<table class="tbl" style="font-size:.68rem">
  <thead>
    <tr>
      <th rowspan="2" style="width:7.5rem">Kode Rekening</th>
      <th rowspan="2">Uraian</th>
      <th colspan="3" style="text-align:center">Rincian Perhitungan</th>
      <th rowspan="2" class="num" style="width:6rem">Jumlah (Rp)</th>
    </tr>
    <tr>
      <th class="num" style="width:4.5rem">Volume</th>
      <th style="width:5rem">Satuan</th>
      <th class="num" style="width:6rem">Harga Satuan</th>
    </tr>
    <tr class="bku-nomor">
      <th>1</th><th>2</th><th class="num">3</th><th>4</th><th class="num">5</th>
      <th class="num">6=(3 x 5)</th>
    </tr>
  </thead>
  <tbody>
    @forelse($baris as $b)
      <tr class="{{ in_array($b['tipe'], ['total', 'seg1'], true) ? 'rka221-utama' : ($b['tipe'] === 'prefix' ? 'rka221-prefix' : '') }}">
        <td style="font-family:monospace;font-size:10px;white-space:nowrap">{{ $b['kode'] }}</td>
        <td>{{ $b['uraian'] !== '' ? $b['uraian'] : '' }}</td>
        <td class="num">{{ $b['volume'] === '' ? '' : rtrim(rtrim(number_format((float) $b['volume'], 2, ',', '.'), '0'), ',') }}</td>
        <td>{{ $b['satuan'] }}</td>
        <td class="num">{{ $b['harga'] === '' ? '' : $angka($b['harga']) }}</td>
        <td class="num">{{ $angka($b['jumlah']) }}</td>
      </tr>
    @empty
      <tr>
        <td colspan="6" class="text-center text-slate-400">Belum ada rincian RKAS untuk tahun ini.</td>
      </tr>
    @endforelse
  </tbody>
  <tfoot>
    <tr style="font-weight:700">
      <td colspan="5" style="text-align:right">Jumlah</td>
      <td class="num">{{ $angka($total) }}</td>
    </tr>
  </tfoot>
</table>

{{-- Keterangan & lembar pembahasan TAPD, seperti bagian penutup lembar 2.2.1. --}}
<table class="tbl" style="margin-top:1rem;font-size:10.5px">
  <tbody>
    <tr>
      <td style="width:28%">Keterangan</td>
      <td></td>
    </tr>
    <tr>
      <td>Tanggal Pembahasan</td>
      <td></td>
    </tr>
    <tr>
      <td style="vertical-align:top">Catatan Hasil Pembahasan</td>
      <td>1.<br>2.<br>dst.</td>
    </tr>
  </tbody>
</table>

<table class="tbl" style="margin-top:.6rem;font-size:10.5px;width:60%">
  <thead>
    <tr>
      <th colspan="3">TAPD PD</th>
    </tr>
    <tr>
      <th style="width:2.5rem">NO</th>
      <th>NAMA</th>
      <th style="width:7rem">TANDATANGAN</th>
    </tr>
  </thead>
  <tbody>
    <tr><td>1.</td><td></td><td></td></tr>
    <tr><td>2.</td><td></td><td></td></tr>
  </tbody>
</table>

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:2rem;margin-top:1.5rem;font-size:11px">
  <div>
    <div>Mengetahui,</div>
    <div>Kepala Sekolah</div>
    <div style="height:3.2rem"></div>
    <div style="font-weight:700;text-decoration:underline">{{ $kepsek->nama ?? '( ……………………… )' }}</div>
    <div>NIP : {{ $kepsek->nip ?? '…' }}</div>
  </div>
  <div style="text-align:left">
    <div>{{ $tempatTtd }}, {{ $tanggalTtd }}</div>
    <div>&nbsp;</div>
    <div style="height:3.2rem"></div>
    <div>&nbsp;</div>
    <div>&nbsp;</div>
  </div>
</div>

<div style="font-size:10.5px;color:#64748b;margin-top:1rem">
  Baris <code>5.</code> adalah total belanja, <code>5.1.</code>/<code>5.2.</code> subtotal belanja
  operasi/modal, lalu subtotal per 4 segmen kode rekening, subtotal rekening, dan baris rincian
  (Volume x Harga Satuan = Jumlah). Program/Sub Program/Kegiatan dicetak <code>-</code> karena lembar
  ini tidak diikat ke satu kegiatan, sama seperti ARKAS.
</div>

<style>
  table.tbl .rka221-utama td { background: #e2e8f0; font-weight: 700; }
  table.tbl .rka221-prefix td { background: #f1f5f9; font-weight: 600; }
  @media print {
    @page {
      size: A4 portrait;
      @bottom-left { content: "{!! $kakiKiri !!}"; font-size: 8pt; color: #334155; }
    }
  }
</style>
@endsection
