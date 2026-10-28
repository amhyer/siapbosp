@extends('layouts.print', ['title' => 'Kertas Kerja RAPBS per Tahap ' . $tahun])
@section('content')
@php
  $angka = fn ($v) => number_format((int) $v, 0, ',', '.');
  $rpTitik = fn ($v) => 'Rp. '.number_format((int) $v, 0, ',', '.');
  $kakiKiri = trim(preg_replace('/[^\p{L}\p{N} .,\-\/:()]/u', ' ',
    'Kertas Kerja perTahap - NPSN : '.$sekolah->npsn.', Nama Sekolah : '.$sekolah->nama));
  $indentasi = ['program' => '0', 'kegiatan' => '1.1rem', 'sub' => '2.2rem', 'rincian' => '3.3rem'];
@endphp

<div class="toolbar nonprint">
  <button type="button" data-cetak-halaman>🖨️ Cetak / Simpan PDF</button>
  <a href="{{ route('laporan-arkas.rkas-tahap') }}">← Kembali ke Rincian RKAS per Tahap</a>
</div>

@include('partials._kop-dokumen-cetak', [
  'judul' => 'KERTAS KERJA RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS) PER TAHAP',
  'periode' => 'TAHUN ANGGARAN : '.$tahun,
  'sekolah' => $sekolah,
  'sumberDana' => 'Tahap : '.$labelTahap,
  'revisiKe' => 0,
  'spasi' => '.05em',
])

<h2 style="font-size:13px;margin:1rem 0 .3rem">A. PENERIMAAN</h2>
<div style="font-size:11.5px;margin-bottom:.25rem">Sumber Dana :</div>
<table class="tbl">
  <thead>
    <tr>
      <th style="width:4.5rem">No. Kode</th>
      <th>Penerimaan</th>
      <th class="num">Jumlah</th>
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
    <tr style="font-weight:700"><td colspan="2" style="text-align:right">Total Penerimaan</td>
      <td class="num">{{ $angka($totalPenerimaan) }}</td></tr>
  </tfoot>
</table>

<h2 style="font-size:13px;margin:1rem 0 .3rem">B. BELANJA</h2>
<table class="tbl">
  <thead>
    <tr>
      <th style="width:3rem">No.<br>Urut</th>
      <th style="width:9rem">Kode<br>Rekening</th>
      <th style="width:5rem">Kode<br>Program</th>
      <th>Uraian</th>
      <th class="num" style="width:5.5rem">Volume</th>
      <th style="width:4rem">Satuan</th>
      <th class="num" style="width:6rem">Tarif Harga</th>
      <th class="num" style="width:6.5rem">Jumlah</th>
      @for($i = 1; $i <= $nTahap; $i++)
        <th class="num" style="width:6rem">Tahap {{ $i }}</th>
      @endfor
    </tr>
  </thead>
  <tbody>
    @forelse($baris as $i => $b)
      <tr class="{{ $b['tipe'] === 'rincian' ? '' : 'bg-slate-50' }}">
        <td>{{ $i + 1 }}.</td>
        <td style="font-family:monospace;font-size:11px">{{ $b['rek'] }}</td>
        <td style="font-family:monospace;font-size:11px">{{ $b['tipe'] === 'rincian' ? '' : $b['kode'] }}</td>
        <td style="padding-left:{{ $indentasi[$b['tipe']] }};{{ $b['tipe'] === 'rincian' ? '' : 'text-transform:uppercase' }}">
          {{ $b['uraian'] !== '' ? $b['uraian'] : '—' }}
        </td>
        <td class="num">{{ $b['volume'] !== null ? rtrim(rtrim(number_format((float) $b['volume'], 2, ',', '.'), '0'), ',') : '' }}</td>
        <td>{{ $b['satuan'] }}</td>
        <td class="num">{{ $b['tarif'] !== null ? $angka($b['tarif']) : '' }}</td>
        <td class="num" style="font-weight:600">{{ $angka($b['jumlah']) }}</td>
        @for($i2 = 1; $i2 <= $nTahap; $i2++)
          <td class="num">{{ $b['tahap'][$i2] > 0 ? $angka($b['tahap'][$i2]) : '0' }}</td>
        @endfor
      </tr>
    @empty
      <tr><td colspan="{{ 8 + $nTahap }}" class="text-center text-slate-400">
        Belum ada rincian RKAS untuk tahun ini.
      </td></tr>
    @endforelse
  </tbody>
  <tfoot>
    <tr style="font-weight:700">
      <td colspan="7" style="text-align:right">Jumlah</td>
      <td class="num">{{ $angka($total) }}</td>
      @for($i = 1; $i <= $nTahap; $i++)
        <td class="num">{{ $angka($totalTahap[$i] ?? 0) }}</td>
      @endfor
    </tr>
  </tfoot>
</table>

<div style="font-size:12px;margin-top:1rem">
  Jumlah Belanja : <b>{{ $rpTitik($total) }}</b> · Jumlah Penerimaan : <b>{{ $rpTitik($totalPenerimaan) }}</b>
  · Selisih : <b>{{ $rpTitik($totalPenerimaan - $total) }}</b>
</div>

<div style="font-size:10.5px;color:#64748b;margin-top:1.5rem">
  ARKAS mencetak pohon program/kegiatan dari master kode kegiatan; baris di atas mengikuti kode kegiatan
  yang tersimpan pada rincian RKAS. Varian “per Bulan” belum dicetak karena SiapBOSP hanya menyimpan
  volume per tahap, bukan rencana per bulan.
</div>

<style>
  @media print {
    @page {
      @bottom-left { content: "{!! $kakiKiri !!}"; font-size: 8pt; color: #334155; }
    }
  }
</style>
@endsection
