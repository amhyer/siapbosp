@extends('layouts.app', ['title' => 'RKAS Bulanan'])
@section('content')
@include('laporan-arkas._header', ['judul' => 'Rencana Kegiatan Anggaran Sekolah (RKAS) - Bulanan'])
<div class="card overflow-x-auto">
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr>
      <th>No</th><th>Program</th><th>Kegiatan</th><th>Rekening Belanja</th><th>Uraian</th>
      <th class="num">Anggaran</th><th class="num">Belanja</th><th>Satuan</th><th class="num">Harga Satuan</th>
      <th class="num">Total</th><th>Status - Bulan</th>
    </tr></thead>
    <tbody>
      @php $ta=0; $tb=0; @endphp
      @forelse($rows as $row)
      @php $ta += $row['anggaran']; $tb += $row['belanja']; @endphp
      <tr>
        <td>{{ $row['no'] }}</td>
        <td class="text-xs">{{ $row['program'] }}</td>
        <td class="text-xs">{{ $row['kegiatan'] }}</td>
        <td class="font-mono text-xs whitespace-nowrap">{{ $row['rek'] }}</td>
        <td>{{ \Illuminate\Support\Str::limit($row['uraian'], 60) }}</td>
        <td class="num">{{ rp($row['anggaran']) }}</td>
        <td class="num {{ $row['belanja'] > 0 ? 'text-emerald-700 font-semibold' : 'text-slate-400' }}">{{ $row['belanja'] > 0 ? rp($row['belanja']) : '0' }}</td>
        <td>{{ $row['satuan'] }}</td>
        <td class="num">{{ rp($row['harga']) }}</td>
        <td class="num">{{ rp($row['total']) }}</td>
        <td><span class="px-1.5 py-0.5 rounded text-[11px] {{ $row['status'] === 'Sudah' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500' }}">{{ $row['status'] }}</span></td>
      </tr>
      @empty
      <tr><td colspan="11" class="text-center text-slate-400">Belum ada RKAS yang disahkan / diimpor untuk tahun ini.</td></tr>
      @endforelse
    </tbody>
    <tfoot>
      <tr class="font-bold bg-slate-50"><td colspan="5" class="text-right">Jumlah</td>
        <td class="num">{{ rp($ta) }}</td><td class="num">{{ rp($tb) }}</td><td colspan="4"></td></tr>
    </tfoot>
  </table></div>
</div>
@endsection
