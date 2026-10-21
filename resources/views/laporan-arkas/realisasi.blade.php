@extends('layouts.app', ['title' => 'Realisasi Anggaran'])
@section('content')
@include('laporan-arkas._header', ['judul' => 'Realisasi Anggaran — ' . ($namaBulan[$bulan] ?? '') . ' ' . $kop['tahun']])
<div class="card overflow-x-auto">
  <div class="flex gap-2 mb-2">
    <a href="{{ route('laporan-arkas.realisasi.csv', ['bulan' => $bulan]) }}" class="btn2 btn text-sm">⬇ Ekspor CSV</a>
  </div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr>
      <th>Kode Rekening</th><th>Uraian</th><th>Komponen</th><th class="num">Jumlah Anggaran</th>
      <th class="num">Realisasi s.d. Bulan Lalu</th><th class="num">Realisasi Bulan Ini</th>
      <th class="num">Jumlah Realisasi s.d. Bulan Ini</th><th class="num">Selisih/ Kurang</th><th class="num">%</th>
    </tr></thead>
    <tbody>
      @forelse($rows as $row)
      <tr>
        <td class="font-mono text-xs whitespace-nowrap">{{ $row['kode'] }}</td>
        <td>{{ $row['nama'] ?: '—' }}</td>
        <td class="text-xs">{{ $row['komponen'] ?: '—' }}</td>
        <td class="num">{{ rp($row['ang']) }}</td>
        <td class="num">{{ rp($row['lalu']) }}</td>
        <td class="num {{ $row['ini'] > 0 ? 'text-emerald-700 font-semibold' : 'text-slate-400' }}">{{ rp($row['ini']) }}</td>
        <td class="num font-semibold">{{ rp($row['sd']) }}</td>
        <td class="num {{ $row['selisih'] < 0 ? 'text-rose-700' : '' }}">{{ rp($row['selisih']) }}</td>
        <td class="num {{ $row['persen'] >= 100 ? 'text-emerald-700' : '' }}">{{ $row['ang'] > 0 ? $row['persen'].'%' : '—' }}</td>
      </tr>
      @empty
      <tr><td colspan="9" class="text-center text-slate-400">Belum ada anggaran maupun belanja tahun ini.</td></tr>
      @endforelse
    </tbody>
    <tfoot>
      <tr class="font-bold bg-slate-50"><td colspan="3" class="text-right">Total</td>
        <td class="num">{{ rp($total['ang']) }}</td><td class="num">{{ rp($total['lalu']) }}</td>
        <td class="num">{{ rp($total['ini']) }}</td><td class="num">{{ rp($total['sd']) }}</td>
        <td class="num">{{ rp($total['selisih']) }}</td>
        <td class="num">{{ $total['ang'] > 0 ? $total['persen'].'%' : '—' }}</td></tr>
    </tfoot>
  </table></div>
</div>
<p class="text-xs text-slate-400 mt-1">Realisasi = belanja tercatat di kas (hasil impor ARKAS). Anggaran dari RKAS terakhir yang disahkan.</p>
@endsection
