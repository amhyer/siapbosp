@extends('layouts.print', ['title' => 'Rekapitulasi Realisasi Belanja'])
@section('content')
@php
  $namaSem = $semester === 1 ? 'Semester 1 (Januari–Juni)' : 'Semester 2 (Juli–Desember)';
  $bendahara = $penandatangan->first(fn ($p) => str_contains(strtolower($p->jabatan), 'bendahara'));
  $kepsek = $penandatangan->first(fn ($p) => str_contains(strtolower($p->jabatan), 'kepala')) ?: $penandatangan->last();
@endphp

<div class="toolbar nonprint">
  <button onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
  <a href="{{ route('bku') }}">← Kembali BKU</a>
  @if ($semester === 1)
    <a href="{{ route('pembukuan.rekapRealisasi', ['semester' => 2]) }}">Semester 2 →</a>
  @else
    <a href="{{ route('pembukuan.rekapRealisasi', ['semester' => 1]) }}">← Semester 1</a>
  @endif
  <a href="{{ route('pembukuan.bukuPembantu', ['semester' => $semester]) }}">Buku Pembantu Semester →</a>
</div>

<div class="kop">
  <h1 style="font-size:1.15rem;margin:0">{{ $sekolah->nama }}</h1>
  <div style="font-size:.85rem"><b>REKAPITULASI REALISASI BELANJA</b></div>
  <div style="font-size:.8rem">{{ $namaSem }} · Tahun Anggaran {{ $tahun }}</div>
  <div style="font-size:.78rem;color:#64748b">
    Sisa pagu semester ini menjadi bahan usulan SiLPA semester berikutnya.
  </div>
</div>

<table class="tbl">
  <thead>
    <tr>
      <th style="width:2rem">No.</th>
      <th style="width:9.5rem">Kode Rekening</th>
      <th>Uraian Rekening</th>
      <th class="num" style="width:7rem">Anggaran</th>
      <th class="num" style="width:7rem">Realisasi Semester</th>
      <th class="num" style="width:6.5rem">Sisa Pagu</th>
      <th class="num" style="width:3.5rem">%</th>
    </tr>
  </thead>
  <tbody>
    @forelse ($rows as $i => $x)
    <tr>
      <td>{{ $i + 1 }}</td>
      <td>{{ $x['kode'] }}</td>
      <td>{{ $x['nama'] ?? '—' }}</td>
      <td class="num">{{ $x['punyaAnggaran'] ? rp($x['anggaran']) : '—' }}</td>
      <td class="num">{{ rp($x['realisasi']) }}</td>
      <td class="num">{{ $x['sisa'] === null ? '—' : rp($x['sisa']) }}</td>
      <td class="num">{{ $x['punyaAnggaran'] && $x['anggaran'] > 0 ? round($x['realisasi'] / $x['anggaran'] * 100, 1).'%' : '—' }}</td>
    </tr>
    @empty
    <tr><td colspan="7" style="text-align:center;color:#64748b">Tidak ada rekening dengan anggaran maupun realisasi pada semester ini.</td></tr>
    @endforelse
  </tbody>
  <tfoot>
    <tr>
      <td colspan="3" style="background:#0f172a;color:#fff;font-weight:700">TOTAL SEMESTER</td>
      <td class="num" style="background:#0f172a;color:#fff;font-weight:700">{{ rp($sumAnggaran) }}</td>
      <td class="num" style="background:#0f172a;color:#fff;font-weight:700">{{ rp($sumRealisasi) }}</td>
      <td class="num" style="background:#0f172a;color:#fff;font-weight:700">{{ $sumAnggaran > 0 ? rp($sumAnggaran - $sumRealisasi) : '—' }}</td>
      <td class="num" style="background:#0f172a;color:#fff;font-weight:700">{{ $sumAnggaran > 0 ? round($sumRealisasi / $sumAnggaran * 100, 1).'%' : '—' }}</td>
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
