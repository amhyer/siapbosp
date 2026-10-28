@extends('layouts.print', ['title' => 'RKA Unit Kerja 2.2 ' . $tahun])
@section('content')
@php
  $angka = fn ($v) => number_format((int) $v, 0, ',', '.');
  // Kaki halaman ARKAS lembar 2.2. Nomor "Halaman X dari Y" dari layouts.print.
  $kakiKiri = trim(preg_replace('/[^\p{L}\p{N} .,\-\/:()]/u', ' ',
    'RKAS Unit Kerja 2.2 - NPSN : '.$sekolah->npsn.', Nama Sekolah : '.$sekolah->nama));
@endphp

<div class="toolbar nonprint">
  <button type="button" data-cetak-halaman>🖨️ Cetak / Simpan PDF</button>
  <a href="{{ route('laporan-arkas.rka-unit', ['varian' => 'rka', 'format' => 'triwulan']) }}">RKA Unit (ringkas)</a>
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

<div style="text-align:center;font-weight:700;font-size:12px;margin:1rem 0 .35rem">
  Rincian Anggaran Belanja Langsung Berdasarkan Program dan Kegiatan
</div>

<table class="tbl" style="font-size:.68rem">
  <thead>
    <tr>
      <th style="width:7rem">Kode<br>Rekening</th>
      <th style="width:3.2rem">Kode<br>Program</th>
      <th style="width:3.2rem">Kode<br>Sub Prog</th>
      <th style="width:3.2rem">Kode<br>Kegiatan</th>
      <th>Uraian</th>
      <th style="width:7.5rem">Lokasi Kegiatan</th>
      <th style="width:5.5rem">Target Kinerja<br>(Kuantitatif)</th>
      <th class="num" style="width:5.5rem">Barang &amp;<br>Jasa</th>
      <th class="num" style="width:5rem">Modal</th>
      <th class="num" style="width:6rem">Jumlah<br>(9 = 7 + 8)</th>
    </tr>
    <tr class="bku-nomor">
      <th>1</th><th>2</th><th>3</th><th>4</th><th>5</th><th>6</th><th>7</th>
      <th class="num">8</th><th class="num">9</th><th class="num">10</th>
    </tr>
  </thead>
  <tbody>
    @forelse($baris as $b)
      @if($b['tipe'] === 'rincian')
        <tr>
          <td style="font-family:monospace;font-size:10px">{{ $b['rekening'] }}</td>
          <td>{{ $b['program'] }}</td>
          <td>{{ $b['subprogram'] }}</td>
          <td>{{ $b['kegiatan'] }}</td>
          <td>{{ $b['uraian'] !== '' ? $b['uraian'] : '—' }}</td>
          <td>{{ $b['lokasi'] }}</td>
          <td>{{ $b['target'] }}</td>
          <td class="num">{{ $angka($b['barang_jasa']) }}</td>
          <td class="num">{{ $angka($b['modal']) }}</td>
          <td class="num" style="font-weight:600">{{ $angka($b['jumlah']) }}</td>
        </tr>
      @else
        <tr class="bg-slate-50" style="font-weight:700">
          <td>{{ $b['kode'] }}</td>
          <td colspan="3"></td>
          <td>{{ $b['uraian'] !== '' ? $b['uraian'] : $b['kode'] }}</td>
          <td></td>
          <td></td>
          <td class="num">{{ $angka($b['barang_jasa']) }}</td>
          <td class="num">{{ $angka($b['modal']) }}</td>
          <td class="num">{{ $angka($b['jumlah']) }}</td>
        </tr>
      @endif
    @empty
      <tr>
        <td colspan="10" class="text-center text-slate-400">
          Belum ada rincian RKAS untuk tahun ini.
        </td>
      </tr>
    @endforelse
  </tbody>
  <tfoot>
    <tr style="font-weight:700">
      <td colspan="7" style="text-align:right">Jumlah</td>
      <td class="num">{{ $angka($totalBarangJasa) }}</td>
      <td class="num">{{ $angka($totalModal) }}</td>
      <td class="num">{{ $angka($total) }}</td>
    </tr>
  </tfoot>
</table>

<div style="display:flex;justify-content:space-between;align-items:flex-end;gap:2rem;margin-top:2rem;font-size:12px">
  <div>
    <div>Mengetahui,</div>
    <div>Kepala Sekolah</div>
    <div style="height:3.5rem"></div>
    <div style="font-weight:700;text-decoration:underline">{{ $kepsek->nama ?? '( …………………………… )' }}</div>
    <div>NIP : {{ $kepsek->nip ?? '…' }}</div>
  </div>
  <div>{{ $tempatTtd }}, {{ $tanggalTtd }}</div>
</div>

<div style="font-size:10.5px;color:#64748b;margin-top:1.25rem">
  Baris program (<code>SS.</code>) dan sub program (<code>SS.SP.</code>) adalah rekap dari rincian di bawahnya;
  uraian rincian memakai nama kegiatan dari master kode kegiatan ARKAS.
  Kolom <b>Lokasi Kegiatan</b> dan <b>Target Kinerja</b> diambil dari rincian RKAS bila diisi,
  selain itu memakai nama sekolah dan volume + satuan rincian (sama seperti lembar ARKAS).
</div>

<style>
  @media print {
    @page {
      size: A4 landscape;
      @bottom-left { content: "{!! $kakiKiri !!}"; font-size: 8pt; color: #334155; }
    }
  }
</style>
@endsection
