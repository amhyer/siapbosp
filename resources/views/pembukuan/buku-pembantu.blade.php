@extends('layouts.print', ['title' => 'Buku Pembantu Rincian Objek Belanja'])
@section('content')
@php
  $namaSem = $semester === 1 ? 'Semester 1 (Januari–Juni)' : 'Semester 2 (Juli–Desember)';
  $bendahara = $penandatangan->first(fn ($p) => str_contains(strtolower($p->jabatan), 'bendahara'));
  $kepsek = $penandatangan->first(fn ($p) => str_contains(strtolower($p->jabatan), 'kepala')) ?: $penandatangan->last();
@endphp
<style>
  .grp th{background:#dbeafe;text-align:left}
  .sub td{background:#f1f5f9;font-weight:600}
</style>

<div class="toolbar nonprint">
  <button type="button" data-cetak-halaman>🖨️ Cetak / Simpan PDF</button>
  <a href="{{ route('bku') }}">← Kembali BKU</a>
  @if ($semester === 1)
    <a href="{{ route('pembukuan.bukuPembantu', ['semester' => 2]) }}">Semester 2 →</a>
  @else
    <a href="{{ route('pembukuan.bukuPembantu', ['semester' => 1]) }}">← Semester 1</a>
  @endif
  <a href="{{ route('pembukuan.rekapRealisasi', ['semester' => $semester]) }}">Rekap Realisasi Semester →</a>
</div>

<div class="kop">
  <h1 style="font-size:1.15rem;margin:0">{{ $sekolah->nama }}</h1>
  <div style="font-size:.85rem"><b>BUKU PEMBANTU RINCIAN OBJEK BELANJA</b></div>
  <div style="font-size:.8rem">{{ $namaSem }} · Tahun Anggaran {{ $tahun }}</div>
  <div style="font-size:.78rem;color:#64748b">Periode {{ \Carbon\Carbon::parse($awal)->translatedFormat('d F Y') }} s.d. {{ \Carbon\Carbon::parse($akhir)->translatedFormat('d F Y') }}</div>
</div>

@forelse ($kelompok as $kode => $g)
  <table class="tbl">
    <thead>
      <tr class="grp">
        <th colspan="4">{{ $kode }} — {{ $g['nama'] }}</th>
        <th class="num">Saldo Awal</th>
        <th class="num">{{ rp($g['awalSaldo']) }}</th>
      </tr>
      <tr><th style="width:2rem">No.</th><th style="width:6rem">No. Bukti</th><th style="width:5.5rem">Tanggal</th><th>Uraian</th><th class="num" style="width:7rem">Pengeluaran</th><th class="num" style="width:7rem">Saldo</th></tr>
    </thead>
    <tbody>
      @forelse ($g['rows'] as $i => $row)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $row['bukti'] ?: '—' }}</td>
        <td>{{ \Carbon\Carbon::parse($row['tgl'])->format('d/m/Y') }}</td>
        <td>{{ $row['uraian'] }}</td>
        <td class="num">{{ rp($row['nilai']) }}</td>
        <td class="num">{{ rp($row['saldo']) }}</td>
      </tr>
      @empty
      <tr><td colspan="6" style="text-align:center;color:#64748b">Tidak ada pengeluaran pada semester ini.</td></tr>
      @endforelse
    </tbody>
    <tfoot>
      <tr class="sub">
        <td colspan="5">Jumlah Mutasi Semester · Saldo Akhir</td>
        <td class="num">{{ rp($g['mutasi']) }} / {{ rp($g['akhir']) }}</td>
      </tr>
    </tfoot>
  </table>
  <div style="height:.6rem"></div>
@empty
  <p style="text-align:center;color:#64748b">Belum ada belanja pada tahun anggaran {{ $tahun }}.</p>
@endforelse

<table class="tbl" style="margin-top:.5rem">
  <tfoot>
    <tr>
      <td style="background:#0f172a;color:#fff;font-weight:700">TOTAL MUTASI PENGELUARAN SEMESTER</td>
      <td class="num" style="background:#0f172a;color:#fff;font-weight:700">{{ rp($totalMutasi) }}</td>
    </tr>
  </tfoot>
</table>

<div class="ttd-grid mt-8">
  <div>
    Mengetahui/Menyetujui,<br>Bendahara Sekolah<br><br><br><br>
    <b>{{ $bendahara?->nama ?? '..................' }}</b><br>
    {{ $bendahara?->nip ? 'NIP. '.$bendahara->nip : '' }}
  </div>
  <div>
    Mengetahui/Menyetujui,<br>Kepala Sekolah<br><br><br><br>
    <b>{{ $kepsek?->nama ?? '..................' }}</b><br>
    {{ $kepsek?->nip ? 'NIP. '.$kepsek->nip : '' }}
  </div>
</div>
@endsection
