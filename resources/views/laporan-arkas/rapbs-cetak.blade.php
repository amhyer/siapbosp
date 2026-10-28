@extends('layouts.print', ['title' => ($format === 'bulan' ? 'Kertas Kerja RAPBS per Bulan ' : 'Kertas Kerja RAPBS per Tahap ') . $tahun])
@section('content')
@php
  $isBulan = $format === 'bulan';
  $angka = fn ($v) => number_format((int) $v, 0, ',', '.');
  $rpTitik = fn ($v) => 'Rp. '.number_format((int) $v, 0, ',', '.');
  $kakiKiri = trim(preg_replace('/[^\p{L}\p{N} .,\-\/:()]/u', ' ',
    'Kertas Kerja '.($isBulan ? 'perBulan' : 'perTahap').' - NPSN : '.$sekolah->npsn
    .', Nama Sekolah : '.$sekolah->nama));
  $indentasi = ['program' => '0', 'kegiatan' => '1.1rem', 'sub' => '2.2rem', 'rincian' => '3.3rem'];
  $komite = $penandatangan->first(fn ($p) => stripos($p->jabatan ?? '', 'komite') !== false);
  $kepsek = $penandatangan->first(fn ($p) => stripos($p->jabatan ?? '', 'kepala') !== false);
  $bendahara = $penandatangan->first(fn ($p) => stripos($p->jabatan ?? '', 'bendahara') !== false);
  $kolomAngka = $isBulan ? 1 : $nTahap;
@endphp

<div class="toolbar nonprint">
  <button type="button" data-cetak-halaman>🖨️ Cetak / Simpan PDF</button>
  <a href="{{ route('laporan-arkas.rapbs-cetak') }}">Per Tahap</a>
  <a href="{{ route('laporan-arkas.rapbs-cetak', ['format' => 'bulan', 'bulan' => $bulan]) }}">Per Bulan</a>
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

@include('partials._kop-dokumen-cetak', [
  'judul' => $isBulan
    ? 'RINCIAN KERTAS KERJA PERBULAN'
    : 'KERTAS KERJA RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS) PER TAHAP',
  'periode' => 'TAHUN ANGGARAN : '.$tahun,
  'sekolah' => $sekolah,
  'sumberDana' => $isBulan
    ? 'Bulan : '.$labelBulan.' '.$tahun.' · '.$kop['sumber'].$kop['revisiLabel']
    : 'Tahap : '.$labelTahap,
  'revisiKe' => 0,
  'spasi' => $isBulan ? '.08em' : '.05em',
])

<h2 style="font-size:13px;margin:1rem 0 .3rem">A. PENERIMAAN</h2>
<div style="font-size:11.5px;margin-bottom:.25rem">Sumber Dana :</div>
<table class="tbl">
  <thead>
    <tr>
      <th style="width:5rem">No. Kode</th>
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
      @if(! $isBulan)
        @for($i = 1; $i <= $nTahap; $i++)
          <th class="num" style="width:6rem">Tahap {{ $i }}</th>
        @endfor
      @endif
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
        <td class="num" style="font-weight:600">{{ $angka($isBulan ? $b['bulan'] : $b['jumlah']) }}</td>
        @if(! $isBulan)
          @for($i2 = 1; $i2 <= $nTahap; $i2++)
            <td class="num">{{ $b['tahap'][$i2] > 0 ? $angka($b['tahap'][$i2]) : '0' }}</td>
          @endfor
        @endif
      </tr>
    @empty
      <tr><td colspan="{{ 8 + $kolomAngka }}" class="text-center text-slate-400">
        Belum ada rincian RKAS untuk tahun ini.
      </td></tr>
    @endforelse
  </tbody>
  <tfoot>
    <tr style="font-weight:700">
      <td colspan="7" style="text-align:right">Jumlah</td>
      <td class="num">{{ $angka($isBulan ? $totalBulan : $total) }}</td>
      @if(! $isBulan)
        @for($i = 1; $i <= $nTahap; $i++)
          <td class="num">{{ $angka($totalTahap[$i] ?? 0) }}</td>
        @endfor
      @endif
    </tr>
  </tfoot>
</table>

<div style="font-size:12px;margin-top:1rem">
  @if($isBulan)
    Rencana belanja {{ $labelBulan }} {{ $tahun }} : <b>{{ $rpTitik($totalBulan) }}</b>
    · Pagu setahun : <b>{{ $rpTitik($total) }}</b>
    · Penerimaan setahun : <b>{{ $rpTitik($totalPenerimaan) }}</b>
  @else
    Jumlah Belanja : <b>{{ $rpTitik($total) }}</b> · Jumlah Penerimaan : <b>{{ $rpTitik($totalPenerimaan) }}</b>
    · Selisih : <b>{{ $rpTitik($totalPenerimaan - $total) }}</b>
  @endif
</div>

@if($isBulan)
  {{-- ARKAS: tiga kolom tanda tangan, tanggal di atas kolom kanan. --}}
  <div class="ttd">
    <div class="ttd-grid" style="grid-template-columns:1fr 1fr 1fr">
      <div>
        <div style="margin-bottom:4rem">Komite Sekolah</div>
        <div style="font-weight:700;text-decoration:underline">{{ $komite->nama ?? '( ……………………… )' }}</div>
        <div>{{ ! empty($komite?->nip) ? 'NIP: '.$komite->nip : '&nbsp;' }}</div>
      </div>
      <div>
        <div style="margin-bottom:4rem">Kepala Sekolah</div>
        <div style="font-weight:700;text-decoration:underline">{{ $kepsek->nama ?? '( ……………………… )' }}</div>
        <div>NIP: {{ $kepsek->nip ?? '…' }}</div>
      </div>
      <div>
        <div style="margin-bottom:4rem">{{ $tempatTtd }}, {{ $tanggalTtd }}<br>Bendahara Sekolah</div>
        <div style="font-weight:700;text-decoration:underline">{{ $bendahara->nama ?? '( ……………………… )' }}</div>
        <div>NIP: {{ $bendahara->nip ?? '…' }}</div>
      </div>
    </div>
  </div>
@endif

<div style="font-size:10.5px;color:#64748b;margin-top:1.5rem">
  ARKAS mencetak pohon program/kegiatan dari master kode kegiatan; baris di atas mengikuti kode kegiatan
  yang tersimpan pada rincian RKAS.
  @if($isBulan)
    Angka kolom Jumlah = rencana bulan terpilih (volume bulan itu × tarif), bukan realisasi.
  @endif
</div>

<style>
  @media print {
    @page {
      @bottom-left { content: "{!! $kakiKiri !!}"; font-size: 8pt; color: #334155; }
    }
  }
</style>
@endsection
