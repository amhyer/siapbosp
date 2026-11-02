@extends('layouts.print', ['title' => 'BHM ' . $tglAkhirTeks])
@section('content')
@php
  $angka = fn ($v) => number_format((int) $v, 0, ',', '.');
  $angkaDesimal = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
  $rpTitik = fn ($v) => 'Rp. '.number_format((int) $v, 0, ',', '.');
  $kepsek = $penandatangan->first(fn ($p) => stripos($p->jabatan ?? '', 'kepala') !== false);
  $bendahara = $penandatangan->first(fn ($p) => stripos($p->jabatan ?? '', 'bendahara') !== false);
  $tempatTtd = trim((string) ($sekolah->kecamatan ?? '')) !== ''
    ? 'Kec. '.$sekolah->kecamatan
    : (string) ($sekolah->kab_kota ?? '');
  $kakiKiri = trim(preg_replace('/[^\p{L}\p{N} .,\-\/:()]/u', ' ',
    'BHM '.$tglAkhirTeks.' - NPSN : '.$sekolah->npsn.', Nama Sekolah : '.$sekolah->nama));
@endphp

<div class="toolbar nonprint">
  <form method="get" action="{{ route('laporan-arkas.bhm') }}" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
    <label for="pilih-bulan" style="font-size:.82rem;font-weight:600">Bulan</label>
    <select id="pilih-bulan" name="bulan" data-kirim-otomatis
      style="border:1px solid #cbd5e1;border-radius:.5rem;padding:.35rem .5rem;font-size:.82rem;background:#fff;font-family:inherit">
      @foreach($namaBulan as $n => $nm)
        <option value="{{ $n }}" @selected((int) $n === (int) $bulan)>{{ $nm }}</option>
      @endforeach
    </select>
    <button type="submit">Terapkan</button>
  </form>
  <button type="button" data-cetak-halaman>🖨️ Cetak / Simpan PDF</button>
  <a href="{{ route('laporan-arkas.modal', ['bulan' => $bulan]) }}">← Kembali ke Realisasi Modal</a>
</div>

@include('partials._kop-dokumen-cetak', [
  'judul' => 'REKAPITULASI REALISASI BELANJA DANA BOSP ( BARANG MODAL / ASET )',
  'periode' => $labelPeriode,
  'penanda' => 'BHM',
  'sekolah' => $sekolah,
  'sumberDana' => $kop['sumber'].$kop['revisiLabel'],
  'revisiKe' => 0,
  'spasi' => '.06em',
  'catatan' => 'Kolom "ID Barang" milik ARKAS tidak dicetak: SiapBOSP belum menyimpan master barang/aset.',
])

<table class="tbl">
  <thead>
    <tr>
      <th>TANGGAL</th>
      <th>KODE<br>KEGIATAN</th>
      <th>KODE<br>REKENING</th>
      <th>NO.<br>BUKTI</th>
      <th>URAIAN</th>
      <th class="num">JUMLAH<br>BARANG</th>
      <th>SATUAN</th>
      <th class="num">HARGA<br>SATUAN</th>
      <th class="num">REALISASI</th>
    </tr>
  </thead>
  <tbody>
    @forelse($rows as $row)
      <tr>
        <td style="white-space:nowrap">{{ \Carbon\Carbon::parse($row['tanggal'])->format('d-m-Y') }}</td>
        <td>{{ $row['kegiatan'] }}</td>
        <td style="font-family:monospace;font-size:11px">{{ $row['rekening'] }}</td>
        <td>{{ $row['no_bukti'] }}</td>
        <td>{{ $row['uraian'] }}</td>
        <td class="num">{{ $row['volume'] !== null ? $angkaDesimal($row['volume']) : '—' }}</td>
        <td>{{ $row['satuan'] !== '' ? $row['satuan'] : '—' }}</td>
        <td class="num">{{ $row['harga'] !== null ? $angka($row['harga']) : '—' }}</td>
        <td class="num">{{ $angka($row['jumlah']) }}</td>
      </tr>
    @empty
      <tr>
        <td colspan="9" class="text-center text-slate-400">
          Tidak ada belanja barang modal (kode rekening 5.2) pada bulan ini.
        </td>
      </tr>
    @endforelse
  </tbody>
  <tfoot>
    <tr style="font-weight:700">
      <td colspan="5" style="text-align:right">Jumlah</td>
      <td class="num">{{ $angkaDesimal($totalVolume) }}</td>
      <td></td>
      <td></td>
      <td class="num">{{ $angka($total) }}</td>
    </tr>
  </tfoot>
</table>

<div style="font-size:12px;margin-top:1rem">
  Realisasi belanja barang modal / aset s/d {{ $tglAkhirTeks }} : <b>{{ $rpTitik($total) }}</b>
</div>

<div class="ttd">
  <div class="ttd-grid" style="grid-template-columns:1fr 1fr">
    <div>
      <div style="margin-bottom:4rem">Menyetujui,<br>Kepala Sekolah</div>
      <div style="font-weight:700;text-decoration:underline">{{ $kepsek->nama ?? '( ……………………… )' }}</div>
      <div>NIP. {{ $kepsek->nip ?? '…' }}</div>
    </div>
    <div>
      <div style="margin-bottom:4rem">{{ $tempatTtd }}, {{ $tglAkhirTeks }}<br>Bendahara</div>
      <div style="font-weight:700;text-decoration:underline">{{ $bendahara->nama ?? '( ……………………… )' }}</div>
      <div>NIP. {{ $bendahara->nip ?? '…' }}</div>
    </div>
  </div>
</div>

<style>
  @media print {
    @page {
      @bottom-left { content: "{!! $kakiKiri !!}"; font-size: 8pt; color: #334155; }
    }
  }
</style>
@endsection
