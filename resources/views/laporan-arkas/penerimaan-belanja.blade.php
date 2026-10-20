@extends('layouts.app', ['title' => 'Realisasi Penerimaan Dan Belanja'])
@section('content')
@include('laporan-arkas._header', ['judul' => 'Realisasi Penerimaan Dan Belanja — ' . ($namaBulan[$bulan] ?? '') . ' ' . $kop['tahun']])
<div class="card">
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr>
      <th>Uraian</th><th class="num">Jumlah Anggaran</th>
      <th class="num">Realisasi s/d Bulan Sebelumnya</th><th class="num">Realisasi Bulan Ini</th>
      <th class="num">Jumlah Realisasi s/d Bulan Ini</th><th class="num">Selisih/ Kurang</th>
    </tr></thead>
    <tbody>
      @foreach($rows as $row)
      <tr>
        <td>{{ $row['uraian'] }}</td>
        <td class="num">{{ rp($row['ang']) }}</td>
        <td class="num">{{ rp($row['lalu']) }}</td>
        <td class="num {{ $row['ini'] > 0 ? 'text-emerald-700 font-semibold' : '' }}">{{ rp($row['ini']) }}</td>
        <td class="num font-semibold">{{ rp($row['sd']) }}</td>
        <td class="num {{ $row['selisih'] < 0 ? 'text-rose-700' : '' }}">{{ rp($row['selisih']) }}</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr class="font-bold bg-slate-50"><td class="text-right">Jumlah</td>
        <td class="num">{{ rp($total['ang']) }}</td><td class="num">{{ rp($total['lalu']) }}</td>
        <td class="num">{{ rp($total['ini']) }}</td><td class="num">{{ rp($total['sd']) }}</td>
        <td class="num">{{ rp($total['selisih']) }}</td></tr>
    </tfoot>
  </table></div>
</div>
@endsection
