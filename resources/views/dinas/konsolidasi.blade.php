@extends('layouts.app', ['title' => 'Konsolidasi Laporan Wilayah'])
@section('content')
<div class="flex flex-wrap justify-between items-center gap-2">
  <p class="text-sm text-slate-500">Rekap lintas semua sekolah aktif — Tahun {{ $tahun }}.</p>
  <a href="{{ route('dinas.konsolidasi.csv') }}" class="btn2 btn">⬇ Ekspor CSV</a>  <a href="{{ route('dinas.konsolidasi.xlsx') }}" class="btn2 btn bg-green-50 border-green-300 font-semibold">📗 Ekspor Excel 2 Sheet</a>
</div>
<div class="card overflow-x-auto">
  <table class="tbl">
    <thead><tr><th>Sekolah</th><th>Jenjang</th><th>Kecamatan</th><th class="num">Pagu</th><th class="num">Diterima</th><th class="num">Belanja</th><th class="num">Pajak Belum Setor</th><th>Serapan</th></tr></thead>
    <tbody>
      @foreach($schools as $s)
      <tr>
        <td><a href="{{ route('dinas.sekolah.show', $s) }}" class="font-semibold text-indigo-700 hover:underline">{{ $s->nama }}</a>
          <div class="text-[11px] text-slate-400">{{ $s->npsn }}</div></td>
        <td>{{ $s->jenjang }}</td><td>{{ $s->kecamatan ?? '—' }}</td>
        <td class="num">{{ rp($s->pagu) }}</td>
        <td class="num">{{ rp($s->pendapatan) }}</td>
        <td class="num text-rose-700">{{ rp($s->belanja) }}</td>
        <td class="num {{ $s->pajakBelum>0?'text-amber-600 font-semibold':'text-slate-300' }}">{{ rp($s->pajakBelum) }}</td>
        <td><span class="badge {{ $s->serapan>=80?'bg-emerald-100 text-emerald-700':($s->serapan>=40?'bg-amber-100 text-amber-700':'bg-rose-100 text-rose-700') }}">{{ $s->serapan }}%</span></td>
      </tr>
      @endforeach
    </tbody>
    <tfoot><tr class="font-bold">
      <td colspan="3" class="text-right">TOTAL WILAYAH</td>
      <td class="num">{{ rp($schools->sum('pagu')) }}</td>
      <td class="num">{{ rp($schools->sum('pendapatan')) }}</td>
      <td class="num">{{ rp($schools->sum('belanja')) }}</td>
      <td class="num">{{ rp($schools->sum('pajakBelum')) }}</td>
      <td>{{ $schools->sum('pagu')>0 ? round($schools->sum('belanja')/$schools->sum('pagu')*100,1) : 0 }}%</td>
    </tr></tfoot>
  </table>
</div>
@endsection
