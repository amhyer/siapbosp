@extends('layouts.app', ['title' => 'Realisasi Modal'])
@section('content')
@include('laporan-arkas._header', ['judul' => 'Realisasi Modal — ' . ($namaBulan[$bulan] ?? '') . ' ' . $kop['tahun']])
<div class="card overflow-x-auto">
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr>
      <th>Kode Rekening</th><th>Uraian</th><th>Tanggal Transaksi</th>
      <th class="num">Volume</th><th class="num">Harga Satuan</th><th class="num">Jumlah</th>
    </tr></thead>
    <tbody>
      @forelse($rows as $row)
      <tr>
        <td class="font-mono text-xs whitespace-nowrap">{{ $row['kode'] }}</td>
        <td>{{ \Illuminate\Support\Str::limit($row['uraian'], 60) }}</td>
        <td class="whitespace-nowrap">{{ $row['tanggal'] }}</td>
        <td class="num">{{ $row['volume'] ?? '—' }}</td>
        <td class="num">{{ $row['harga'] !== null ? rp($row['harga']) : '—' }}</td>
        <td class="num font-semibold">{{ rp($row['jumlah']) }}</td>
      </tr>
      @empty
      <tr><td colspan="6" class="text-center text-slate-400">Tidak ada belanja modal (5.2) di bulan ini.</td></tr>
      @endforelse
    </tbody>
    <tfoot>
      <tr class="font-bold bg-slate-50"><td colspan="5" class="text-right">Total</td><td class="num">{{ rp($total) }}</td></tr>
    </tfoot>
  </table></div>
</div>
@endsection
