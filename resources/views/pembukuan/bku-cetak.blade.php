@extends('layouts.print', ['title' => 'BKU ' . \Carbon\Carbon::parse($bulan)->translatedFormat('F Y')])
@section('content')
@php
  $sekolah = auth()->user()->school;
  $namaBulan = strtoupper(\Carbon\Carbon::parse($bulan)->translatedFormat('F'));
  $thnBulan = \Carbon\Carbon::parse($bulan)->format('Y');
  $tglAkhir = \Carbon\Carbon::parse($bulan)->endOfMonth()->translatedFormat('d F Y');
  $hariIni = \Carbon\Carbon::parse($bulan)->endOfMonth()->translatedFormat('l d F Y');
@endphp
<div class="toolbar nonprint"><button onclick="window.print()">🖨️ Cetak / Simpan PDF</button><a href="{{ route('bku', ['bulan' => $bulan]) }}">← Kembali ke BKU</a></div>
<div class="kop">
  <div style="font-weight:800;letter-spacing:.35em">BUKU KAS UMUM</div>
  <div style="font-size:11px">Nama Sekolah : {{ $sekolah->nama }}</div>
  <div style="font-size:11px">Desa/Kecamatan : {{ trim(($sekolah->desa ?? '' ? $sekolah->desa . ', ' : '') . ($sekolah->kecamatan ?? ''), ', ') ?: '—' }}</div>
  <div style="font-size:11px">Kabupaten / Kota : {{ $sekolah->kab_kota }} &nbsp; Provinsi : {{ $sekolah->provinsi }}</div>
</div>
<div style="font-size:11px;margin-bottom:.4rem">NPSN : {{ $sekolah->npsn }}</div>
<div style="display:flex;justify-content:space-between;font-size:12px;font-weight:700;margin-bottom:.4rem;flex-wrap:wrap;gap:.4rem">
  <span>BULAN : {{ $namaBulan }} &nbsp; TAHUN : {{ $thnBulan }}</span>
  <span>Sumber Dana : {{ $kopSumber }}{{ $revisiKe > 1 ? ' Perubahan Revisi Ke ' . ($revisiKe - 1) : '' }}</span>
</div>
<table class="tbl">
  <thead><tr><th>Tanggal</th><th>Kegiatan</th><th>Rekening</th><th>No. Bukti</th><th>Uraian</th><th class="num">Penerimaan (Rp)</th><th class="num">Pengeluaran (Rp)</th><th class="num">Saldo (Rp)</th></tr></thead>
  <tbody>
    <tr style="font-weight:600;background:#f8fafc"><td>{{ \Carbon\Carbon::parse($bulan)->format('d-m-Y') }}</td><td></td><td></td><td></td><td>Saldo Bulan Lalu</td><td class="num"></td><td class="num"></td><td class="num">{{ rp($saldoAwal) }}</td></tr>
    @php $saldo = $saldoAwal; @endphp
    @foreach($rows as $row)
    @php
      $saldo += $row['masuk'] - $row['keluar']; $t = $row['trx'];
      $keg = ($t->details->first(fn($d) => $d->kode_kegiatan) ?? $t->details->first())?->kode_kegiatan ?? '';
      $kegLabel = $keg !== '' ? trim($keg . ' ' . ($kodeNama[$keg] ?? '')) : '';
      $rek = $t->details->map(fn($d) => $d->rekening->kode ?? '')->filter()->unique()->implode(', ');
    @endphp
    <tr><td style="white-space:nowrap">{{ $t->tanggal->format('d-m-Y') }}</td><td>{{ $kegLabel }}</td>
      <td style="font-family:monospace;font-size:11px">{{ $rek }}</td>
      <td>{{ $t->arkas_bku ?? ($t->no_bku ? 'BKU#' . $t->no_bku : '') }}</td>
      <td>{{ $t->keterangan }}</td>
      <td class="num">{{ $row['masuk'] ? rp($row['masuk']) : '' }}</td><td class="num">{{ $row['keluar'] ? rp($row['keluar']) : '' }}</td>
      <td class="num">{{ rp($saldo) }}</td></tr>
    @endforeach
    <tr style="font-weight:600;background:#f8fafc"><td>{{ \Carbon\Carbon::parse($bulan)->endOfMonth()->format('d-m-Y') }}</td><td></td><td></td><td></td><td>Pajak Bunga</td><td class="num"></td><td class="num"></td><td class="num">{{ rp($saldo) }}</td></tr>
  </tbody>
  <tfoot>
    <tr style="font-weight:700"><td colspan="5" style="text-align:right">JUMLAH</td><td class="num">{{ rp(collect($rows)->sum('masuk')) }}</td><td class="num">{{ rp(collect($rows)->sum('keluar')) }}</td><td class="num">{{ rp($saldo) }}</td></tr>
  </tfoot>
</table>

<div style="font-size:12px;margin-top:1rem">Pada hari ini {{ $hariIni }} Buku Kas Umum Ditutup dengan keadaan/posisi buku sebagai berikut :</div>
<div style="font-size:13px;margin-top:.3rem">Saldo Buku Kas Umum : <b>{{ rp($saldo) }}</b><br>Terdiri Dari :<br>— Saldo Bank : <b>{{ rp($bankAkhir) }}</b><br>— Saldo Kas Tunai : <b>{{ rp($tunaiAkhir) }}</b><br>Jumlah : <b>{{ rp($bankAkhir + $tunaiAkhir) }}</b></div>

@php
  $kepsek = $penandatangan->first(fn($p) => stripos($p->jabatan ?? '', 'kepala') !== false);
  $bendahara = $penandatangan->first(fn($p) => stripos($p->jabatan ?? '', 'bendahara') !== false);
@endphp
<div class="ttd">
  <div class="ttd-grid" style="grid-template-columns:1fr 1fr 1fr">
    <div><div style="margin-bottom:4rem">Menyetujui,<br>Kepala Sekolah</div><div style="font-weight:700;text-decoration:underline">{{ $kepsek->nama ?? '( ……………………… )' }}</div><div>NIP. {{ $kepsek->nip ?? '…' }}</div></div>
    <div><div style="margin-bottom:4rem">{{ $sekolah->kab_kota ?? '' }}, {{ $tglAkhir }}<br>Bendahara</div><div style="font-weight:700;text-decoration:underline">{{ $bendahara->nama ?? '( ……………………… )' }}</div><div>NIP. {{ $bendahara->nip ?? '…' }}</div></div>
    <div><div style="margin-bottom:4rem"><br>Komite Sekolah</div><div style="font-weight:700;text-decoration:underline">{{ $penandatangan->first(fn($p) => stripos($p->jabatan ?? '', 'komite') !== false)?->nama ?? '( ……………………… )' }}</div><div>&nbsp;</div></div>
  </div>
</div>
@endsection
