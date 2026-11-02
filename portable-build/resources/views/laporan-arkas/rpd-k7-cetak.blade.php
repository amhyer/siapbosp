@extends('layouts.print', ['title' => 'RPD K7 ' . $tahun])
@section('content')
@php
  $angka = fn ($v) => number_format((int) $v, 0, ',', '.');
  $rp = fn ($v) => 'Rp. '.number_format((int) $v, 0, ',', '.');
  $kepsek = $penandatangan->first(fn ($p) => stripos((string) ($p->jabatan ?? ''), 'kepala') !== false);
  $bendahara = $penandatangan->first(fn ($p) => stripos((string) ($p->jabatan ?? ''), 'bendahara') !== false)
    ?? $penandatangan->first(fn ($p) => stripos((string) ($p->jabatan ?? ''), 'komite') === false && $p->id !== ($kepsek->id ?? 0));
  $kakiKiri = trim(preg_replace('/[^\p{L}\p{N} .,\-\/:()]/u', ' ',
    'RPD K7 - NPSN : '.$sekolah->npsn.', Nama Sekolah : '.$sekolah->nama));
@endphp

<div class="toolbar nonprint">
  <button type="button" data-cetak-halaman>🖨️ Cetak / Simpan PDF</button>
  <a href="{{ route('laporan-arkas.rkas-tahap') }}">← Rincian RKAS per Tahap</a>
</div>

<div style="text-align:center;line-height:1.35;margin-bottom:.6rem">
  <div style="font-weight:700;font-size:13px;letter-spacing:.04em">REKAPITULASI REALISASI PENGGUNAAN DANA BOSP</div>
  <div style="font-weight:700;font-size:12px">{{ strtoupper('TAHUN '.$tahun) }}</div>
  <div style="font-size:11.5px">PERIODE TANGGAL : {{ $periodeTeks }}</div>
</div>

<table class="tbl" style="border:0;margin-bottom:.6rem">
  <tbody>
    <tr><td style="border:0;width:11rem;padding-left:0">Nama Sekolah</td><td style="border:0">: {{ $sekolah->nama }}</td></tr>
    <tr><td style="border:0;padding-left:0">Kecamatan</td><td style="border:0">: {{ $sekolah->kecamatan !== null && $sekolah->kecamatan !== '' ? 'Kec. '.$sekolah->kecamatan : '' }}</td></tr>
    <tr><td style="border:0;padding-left:0">Kabupaten/Kota</td><td style="border:0">: {{ $sekolah->kab_kota }}</td></tr>
    <tr><td style="border:0;padding-left:0">Provinsi</td><td style="border:0">: {{ $sekolah->provinsi }}</td></tr>
    <tr><td style="border:0;padding-left:0">NPSN</td><td style="border:0">: {{ $sekolah->npsn }}</td></tr>
  </tbody>
</table>

<div style="overflow-x:auto">
<table class="tbl" style="font-size:.6rem">
  <thead>
    <tr>
      <th rowspan="2" style="width:2.4rem">No. Urut</th>
      <th rowspan="2" style="width:9rem">8 STANDAR</th>
      <th colspan="{{ count($subProgram) }}" style="text-align:center">SUB PROGRAM</th>
      <th rowspan="2" class="num" style="width:5.5rem">Jumlah</th>
    </tr>
    <tr>
      @foreach($subProgram as $no => $nama)
        <th class="num" style="width:4.2rem">{{ $no }}</th>
      @endforeach
    </tr>
    <tr class="bku-nomor">
      <th colspan="2" style="text-align:left">Uraian sub program:</th>
      @foreach($subProgram as $no => $nama)
        <th style="text-align:left;font-weight:500">{{ $nama }}</th>
      @endforeach
      <th></th>
    </tr>
  </thead>
  <tbody>
    @foreach($baris as $i => $b)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $b['nama'] }}</td>
        @foreach($subProgram as $no => $nama)
          <td class="num">{{ $angka($b['nilai'][$no] ?? 0) }}</td>
        @endforeach
        <td class="num" style="font-weight:600">{{ $angka($b['total']) }}</td>
      </tr>
    @endforeach
  </tbody>
  <tfoot>
    <tr style="font-weight:700">
      <td colspan="2">JUMLAH</td>
      @foreach($subProgram as $no => $nama)
        <td class="num">{{ $angka($totalKolom[$no] ?? 0) }}</td>
      @endforeach
      <td class="num">{{ $angka($jumlah) }}</td>
    </tr>
  </tfoot>
</table>
</div>

<table class="tbl" style="width:auto;margin-top:.8rem;font-size:11.5px">
  <tbody>
    <tr><td style="border:0;padding-left:0;width:16rem">Saldo periode sebelumnya</td><td style="border:0">: {{ $rp($saldoAwal) }}</td></tr>
    <tr><td style="border:0;padding-left:0">Total penerimaan dana BOSP periode ini</td><td style="border:0">: {{ $rp($penerimaan) }}</td></tr>
    <tr><td style="border:0;padding-left:0">Total penggunaan dana BOSP periode ini</td><td style="border:0">: {{ $rp($penggunaan) }}</td></tr>
    <tr><td style="border:0;padding-left:0">Akhir saldo BOSP periode ini</td><td style="border:0">: {{ $rp($saldoAkhir) }}</td></tr>
  </tbody>
</table>

<div class="ttd-grid" style="grid-template-columns:1fr 1fr;margin-top:1.75rem">
  <div>
    <div>Menyetujui,</div>
    <div>Kepala Sekolah</div>
    <div style="height:3.5rem"></div>
    <div style="font-weight:700;text-decoration:underline">{{ $kepsek->nama ?? '( ……………………… )' }}</div>
    <div>NIP. {{ $kepsek->nip ?? '…' }}</div>
  </div>
  <div>
    <div>{{ $tempatTtd }}, {{ $tanggalTtd }}</div>
    <div>Bendahara / Penanggungjawab Kegiatan</div>
    <div style="height:3.5rem"></div>
    <div style="font-weight:700;text-decoration:underline">{{ $bendahara->nama ?? '( ……………………… )' }}</div>
    <div>NIP. {{ $bendahara->nip ?? '…' }}</div>
  </div>
</div>

<div style="font-size:10.5px;color:#64748b;margin-top:1rem">
  Sel matriks = realisasi belanja tahun berjalan dengan kode kegiatan
  <b>SS.SP.KK</b> (SS = standar, SP = sub program) — bentuk kode yang diimpor dari ARKAS.
  Baris standar memakai master kode kegiatan bila sudah terimpor; bila belum, dipakai
  daftar baku yang sama dengan lembar RPD ARKAS.
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
