@extends('layouts.print', ['title' => 'BKU Pajak ' . $labelBulan . ' ' . $tahun])
@section('content')
@php
  // Angka kolom tanpa awalan Rp (pemisah ribuan titik), seperti cetakan ARKAS.
  $angka = fn ($v) => number_format((int) $v, 0, ',', '.');
  $rpTitik = fn ($v) => 'Rp. '.number_format((int) $v, 0, ',', '.');
  // Teks kaki kiri halaman; dibersihkan ke karakter aman agar valid di string CSS.
  $kakiKiri = trim(preg_replace('/[^\p{L}\p{N} .,\-\/:()]/u', ' ',
    'BKU PAJAK '.$labelBulan.' '.$tahun.' - NPSN : '.$sekolah->npsn.', Nama Sekolah : '.$sekolah->nama));
@endphp

<div class="toolbar nonprint">
  <form method="get" action="{{ route('bku.pajak') }}" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
    <label for="pilih-periode" style="font-size:.82rem;font-weight:600">Periode</label>
    <input type="month" id="pilih-periode" name="bulan" value="{{ $bulan }}" data-kirim-otomatis
      style="border:1px solid #cbd5e1;border-radius:.5rem;padding:.35rem .5rem;font-size:.82rem;background:#fff;font-family:inherit">
    <button type="submit">Terapkan</button>
  </form>
  <button type="button" data-cetak-halaman>🖨️ Cetak / Simpan PDF</button>
  <a href="{{ route('bku', ['bulan' => $bulan]) }}">← Kembali ke BKU</a>
</div>

@include('partials._kop-dokumen-cetak', [
  'judul' => 'B U K U   P E M B A N T U   P A J A K',
  'periode' => $labelPeriode,
  'penanda' => 'BKU - PAJAK',
  'sekolah' => $sekolah,
  'sumberDana' => $kopSumber,
  'revisiKe' => $revisiKe,
  'catatan' => 'Pajak yang dipungut dari belanja dicatat pada kolom jenisnya (Penerimaan/Debit); '
    .'penyetoran tercatat di kolom Pengeluaran/Kredit; Saldo = utang pajak berjalan.',
])

<table class="tbl">
  <thead>
    <tr>
      <th rowspan="2">TANGGAL</th>
      <th rowspan="2">NO. KODE</th>
      <th rowspan="2">URAIAN</th>
      <th colspan="{{ count($kolom) }}">PENERIMAAN / DEBIT</th>
      <th rowspan="2" class="num">PENGELUARAN/<br>KREDIT</th>
      <th rowspan="2" class="num">SALDO</th>
    </tr>
    <tr>
      @forelse($kolom as $k)
        <th class="num">{{ $labelPajak[$k] ?? strtoupper($k) }}</th>
      @empty
        <th class="num">—</th>
      @endforelse
    </tr>
  </thead>
  <tbody>
    @forelse($rows as $row)
      <tr>
        <td style="white-space:nowrap">{{ $row['tanggal']->format('d-m-Y') }}</td>
        <td>{{ $row['no'] }}</td>
        <td>{{ $row['uraian'] }}</td>
        @foreach($kolom as $k)
          <td class="num">{{ $row['jenis'] === $k ? $angka($row['nilai']) : 0 }}</td>
        @endforeach
        <td class="num">{{ $angka($row['keluar']) }}</td>
        <td class="num">{{ $angka($row['saldo']) }}</td>
      </tr>
    @empty
      <tr>
        <td colspan="{{ count($kolom) + 5 }}" class="text-center text-slate-400">
          Tidak ada pungutan atau penyetoran pajak pada periode ini.
        </td>
      </tr>
    @endforelse
  </tbody>
  <tfoot>
    <tr style="font-weight:700">
      <td colspan="3" style="text-align:right">Jumlah</td>
      @foreach($kolom as $k)
        <td class="num">{{ $angka($totalJenis[$k] ?? 0) }}</td>
      @endforeach
      <td class="num">{{ $angka($totalKeluar) }}</td>
      <td class="num">{{ $angka($saldoAkhir) }}</td>
    </tr>
  </tfoot>
</table>

<div style="font-size:12px;margin-top:1rem">
  Utang pajak yang belum disetor s/d {{ $tglAkhirTeks }} : <b>{{ $rpTitik($saldoAkhir) }}</b>
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
