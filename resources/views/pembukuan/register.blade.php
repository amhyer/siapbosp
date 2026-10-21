@extends('layouts.print', ['title' => 'Register Kas ' . ucfirst($jenis) . ' ' . $bulan])
@section('content')
@php
  $kepsek = $penandatangan->firstWhere('jabatan', 'Kepala Sekolah') ?? $penandatangan->first(fn($p) => stripos($p->jabatan, 'kepala') !== false);
  $bendahara = $penandatangan->first(fn($p) => stripos($p->jabatan, 'bendahara') !== false) ?? $penandatangan->skip(1)->first();
  $namaBulan = \Carbon\Carbon::createFromFormat('Y-m', $bulan)->translatedFormat('F Y');
@endphp

<div class="toolbar nonprint">
  <form style="display:flex;gap:.5rem;align-items:center;font-size:.82rem" method="get">
    <input type="hidden" name="jenis" value="{{ $jenis }}">
    <span>Bulan:</span>
    <input type="month" name="bulan" value="{{ $bulan }}" style="border:1px solid #cbd5e1;border-radius:.5rem;padding:.35rem .6rem" onchange="this.form.submit()">
  </form>
  <a href="{{ route('pembukuan.register', ['jenis' => $jenis === 'tunai' ? 'bank' : 'tunai', 'bulan' => $bulan]) }}">🔁 Register {{ $jenis === 'tunai' ? 'Bank' : 'Tunai' }}</a>
  <button onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
  <a href="{{ route('kas') }}">← Kembali</a>
</div>

<div class="kop">
  <div style="font-size:11px;text-transform:uppercase">{{ $sekolah->nama }} — NPSN {{ $sekolah->npsn }}</div>
  <div style="font-weight:800">BUKU PEMBANTU KAS {{ strtoupper($jenis) }} — BULAN {{ strtoupper($namaBulan) }}</div>
</div>

<table class="tbl">
  <thead><tr><th>No BKU</th><th>Tanggal</th><th>Uraian</th><th class="num">Masuk (Rp)</th><th class="num">Keluar (Rp)</th><th class="num">Saldo (Rp)</th></tr></thead>
  <tbody>
    <tr class="font-semibold" style="font-weight:600;background:#f8fafc"><td></td><td></td><td>SALDO AWAL BULAN (termasuk SILPA)</td><td></td><td></td><td class="num">{{ rp($saldoAwal) }}</td></tr>
    @php $saldo = $saldoAwal; $tm = 0; $tk = 0; @endphp
    @forelse($rows as $row)
      @php $saldo += $row['masuk'] - $row['keluar']; $tm += $row['masuk']; $tk += $row['keluar']; $t = $row['trx']; @endphp
      <tr>
        <td>#{{ $t->no_bku }}</td><td style="white-space:nowrap">{{ $t->tanggal->format('d/m/Y') }}</td>
        <td>{{ $t->keterangan }} <span style="color:#64748b;font-size:10px">({{ $t->labelType() }})</span></td>
        <td class="num">{{ $row['masuk'] ? rp($row['masuk']) : '' }}</td>
        <td class="num">{{ $row['keluar'] ? rp($row['keluar']) : '' }}</td>
        <td class="num">{{ rp($saldo) }}</td>
      </tr>
    @empty
      <tr><td colspan="6" style="text-align:center;color:#64748b;font-style:italic;padding:1rem">Tidak ada mutasi kas {{ $jenis }} bulan ini.</td></tr>
    @endforelse
  </tbody>
  <tfoot><tr style="font-weight:700"><td colspan="3" style="text-align:right">JUMLAH / SALDO AKHIR</td><td class="num">{{ rp($tm) }}</td><td class="num">{{ rp($tk) }}</td><td class="num">{{ rp($saldoAwal + $tm - $tk) }}</td></tr></tfoot>
</table>

<div class="ttd">
  <div class="ttd-grid">
    <div><div style="margin-bottom:4rem">Mengetahui,<br>Kepala Sekolah</div><div style="font-weight:700;text-decoration:underline">{{ $kepsek->nama ?? '( ……………………… )' }}</div><div>NIP. {{ $kepsek->nip ?? '…' }}</div></div>
    <div><div style="margin-bottom:4rem">{{ $sekolah->kab_kota ?? '' }}, {{ now()->translatedFormat('d F Y') }}<br>Bendahara BOSP</div><div style="font-weight:700;text-decoration:underline">{{ $bendahara->nama ?? '( ……………………… )' }}</div><div>NIP. {{ $bendahara->nip ?? '…' }}</div></div>
  </div>
</div>
@endsection
