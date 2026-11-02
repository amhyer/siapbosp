@extends('layouts.print', ['title' => 'APBS (RKAS) ' . $tahun])
@section('content')
@php
  $angka = fn ($v) => number_format((int) $v, 0, ',', '.');
  $kakiKiri = trim(preg_replace('/[^\p{L}\p{N} .,\-\/:()]/u', ' ',
    'RKAS - NPSN : '.$sekolah->npsn.', Nama Sekolah : '.$sekolah->nama));
  $kepsek = $penandatangan->first(fn ($p) => stripos((string) $p->jabatan, 'kepala') !== false);
  $komite = $penandatangan->first(fn ($p) => stripos((string) $p->jabatan, 'komite') !== false);
  $bendahara = $penandatangan->first(fn ($p) => stripos((string) $p->jabatan, 'bendahara') !== false)
    ?? $penandatangan->last();
  $nKolom = count($kelompok) * 2;
@endphp

<div class="toolbar nonprint">
  <button type="button" data-cetak-halaman>🖨️ Cetak / Simpan PDF</button>
  <a href="{{ route('laporan-arkas.rkas-tahap') }}">← Rincian RKAS per Tahap</a>
  <span style="font-size:12px;color:#64748b">
    APBS seluruh sumber dana: penerimaan per sumber + pohon belanja program → rincian.
  </span>
</div>

<div style="text-align:center;line-height:1.35">
  <div style="font-weight:700;font-size:13px;letter-spacing:.04em">RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS)</div>
  <div style="font-weight:700;font-size:12px;margin-top:.2rem">TAHUN ANGGARAN : {{ $tahun }}</div>
</div>

<table class="tbl" style="border:0;margin-top:.5rem;font-size:11px">
  <tbody>
    <tr>
      <td style="border:0;width:8.5rem">Nama Sekolah</td>
      <td style="border:0">: {{ $sekolah->nama }}</td>
    </tr>
    <tr>
      <td style="border:0">Alamat</td>
      <td style="border:0">: {{ $sekolah->desa }}</td>
    </tr>
    <tr>
      <td style="border:0">Kabupaten</td>
      <td style="border:0">: {{ $sekolah->kab_kota }}</td>
    </tr>
    <tr>
      <td style="border:0">Provinsi</td>
      <td style="border:0">: Prov. {{ $sekolah->provinsi }}</td>
    </tr>
    <tr>
      <td style="border:0">NPSN</td>
      <td style="border:0">: {{ $sekolah->npsn }}</td>
    </tr>
  </tbody>
</table>

{{-- ===================== A. PENERIMAAN ===================== --}}
<div style="font-weight:700;font-size:12px;margin:1rem 0 .3rem">A. PENERIMAAN</div>
<div style="font-size:11px;margin-bottom:.25rem">Sumber Dana : {{ $kop['sumber'] }}</div>
<table class="tbl" style="font-size:10.5px">
  <thead>
    <tr>
      <th style="width:2.5rem">No.</th>
      <th>Kode Penerimaan</th>
      <th class="num" style="width:9rem">Jumlah</th>
    </tr>
  </thead>
  <tbody>
    @forelse($penerimaan as $i => $p)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>
          <span style="font-family:monospace">{{ $p['kode'] }}</span>
          {{ $p['nama'] }}
        </td>
        <td class="num">{{ $angka($p['nilai']) }}</td>
      </tr>
    @empty
      <tr><td colspan="3" class="text-center text-slate-400">Belum ada alokasi sumber dana tahun ini.</td></tr>
    @endforelse
  </tbody>
  <tfoot>
    <tr style="font-weight:700">
      <td colspan="2" style="text-align:right">Total Penerimaan</td>
      <td class="num">{{ $angka($totalPenerimaan) }}</td>
    </tr>
  </tfoot>
</table>

{{-- ===================== B. BELANJA ===================== --}}
<div style="font-weight:700;font-size:12px;margin:1rem 0 .3rem">B. BELANJA</div>
<table class="tbl apbs" style="font-size:8.5px">
  <thead>
    <tr>
      <th rowspan="3" style="width:1.9rem">No.<br>Urut</th>
      <th rowspan="3" style="width:4.4rem">Kode<br>Kegiatan</th>
      <th rowspan="3" style="width:7.2rem">Kode Rekening</th>
      <th rowspan="3">Uraian</th>
      <th rowspan="3" class="num" style="width:4.6rem">Jumlah</th>
      <th colspan="{{ $nKolom }}">Sumber Dana dan Alokasi Anggaran</th>
    </tr>
    <tr>
      @foreach($kelompok as $label)
        <th colspan="2" style="text-align:center">{{ $label }}</th>
      @endforeach
    </tr>
    <tr>
      @foreach($kelompok as $label)
        <th class="num" style="width:3.2rem">Belanja<br>Operasi</th>
        <th class="num" style="width:3.2rem">Belanja<br>Modal</th>
      @endforeach
    </tr>
  </thead>
  <tbody>
    @forelse($baris as $i => $b)
      <tr class="{{ $b['level'] === 1 ? 'apbs-l1' : ($b['level'] === 2 ? 'apbs-l2' : '') }}">
        <td class="num">{{ $i + 1 }}</td>
        <td style="font-family:monospace;white-space:nowrap">{{ $b['keg'] }}</td>
        <td style="font-family:monospace;white-space:nowrap">{{ $b['rek'] }}</td>
        <td style="{{ $b['level'] <= 3 ? 'font-weight:700' : '' }}">{{ $b['uraian'] !== '' ? $b['uraian'] : '—' }}</td>
        <td class="num" style="{{ $b['level'] <= 3 ? 'font-weight:700' : '' }}">{{ $angka($b['jumlah']) }}</td>
        @foreach($kelompok as $kunci => $label)
          <td class="num">{{ $angka($b['dana'][$kunci]['operasi']) }}</td>
          <td class="num">{{ $angka($b['dana'][$kunci]['modal']) }}</td>
        @endforeach
      </tr>
    @empty
      <tr>
        <td colspan="{{ 5 + $nKolom }}" class="text-center text-slate-400">
          Belum ada rincian RKAS untuk tahun ini.
        </td>
      </tr>
    @endforelse
  </tbody>
  <tfoot>
    <tr style="font-weight:700">
      <td colspan="4" style="text-align:right">Jumlah</td>
      <td class="num">{{ $angka($total) }}</td>
      @foreach($kelompok as $kunci => $label)
        <td class="num">{{ $angka($totalDana[$kunci]['operasi']) }}</td>
        <td class="num">{{ $angka($totalDana[$kunci]['modal']) }}</td>
      @endforeach
    </tr>
  </tfoot>
</table>

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1.5rem;margin-top:1.75rem;font-size:11px">
  <div>
    <div>Mengetahui,</div>
    <div>Kepala Sekolah</div>
    <div style="height:3.2rem"></div>
    <div style="font-weight:700;text-decoration:underline">{{ $kepsek->nama ?? '( ……………………… )' }}</div>
    <div>NIP : {{ $kepsek->nip ?? '…' }}</div>
  </div>
  <div>
    <div>&nbsp;</div>
    <div>Komite Sekolah</div>
    <div style="height:3.2rem"></div>
    <div style="font-weight:700;text-decoration:underline">{{ $komite->nama ?? '( ……………………… )' }}</div>
    <div>&nbsp;</div>
  </div>
  <div>
    <div>{{ $tempatTtd }}, {{ $tanggalTtd }}</div>
    <div>Bendahara Sekolah</div>
    <div style="height:3.2rem"></div>
    <div style="font-weight:700;text-decoration:underline">{{ $bendahara->nama ?? '( ……………………… )' }}</div>
    <div>NIP : {{ $bendahara->nip ?? '…' }}</div>
  </div>
</div>

<div style="font-size:9px;color:#64748b;margin-top:1rem">
  Baris program (<code>SS.</code>), sub program (<code>SS.SP.</code>), kegiatan (<code>SS.SP.KG.</code>),
  dan kegiatan+rekening adalah rekap dari baris rincian di bawahnya. Nomor rincian (<code>001.</code>, <code>002.</code>)
  berulang per blok rekening. Kolom dana mengikuti sumber dana rincian; Belanja Modal = rekening <code>5.2…</code>,
  sisanya Belanja Operasi.
</div>

<style>
  table.apbs th, table.apbs td { padding: 2px 3px; }
  table.apbs .apbs-l1 td { background: #f1f5f9; }
  table.apbs .apbs-l2 td { background: #f8fafc; }
  @media print {
    @page {
      size: A4 landscape;
      @bottom-left { content: "{!! $kakiKiri !!}"; font-size: 8pt; color: #334155; }
    }
    table.apbs { font-size: 7.5pt; }
  }
</style>
@endsection
