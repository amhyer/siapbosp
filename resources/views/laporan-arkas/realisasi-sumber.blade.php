@extends('layouts.app', ['title' => 'Laporan Realisasi per Sumber Dana'])
@section('subtitle', 'Pagu, rencana, penerimaan, belanja, dan sisa tiap sumber dana')
@section('content')
<div class="card">
  <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
    <h3 class="card-title mb-0">🧾 Realisasi per Sumber Dana — TA {{ $tahun }}</h3>
    <a href="{{ route('laporan-arkas.realisasi') }}" class="btn2 btn-sm">📊 Realisasi Anggaran (ARKAS)</a>
  </div>
  <div class="overflow-x-auto">
    <table class="tbl">
      <thead><tr><th>Sumber Dana</th><th class="num">Pagu</th><th class="num">Rencana (RKAS)</th><th class="num">Penerimaan</th><th class="num">Belanja</th><th class="num">Sisa Pagu</th><th class="num">Serapan</th></tr></thead>
      <tbody>
        @forelse($baris as $b)
          <tr>
            <td class="font-semibold">{{ $b['sumber'] }}</td>
            <td class="num">{{ rp($b['pagu']) }}</td>
            <td class="num">{{ rp($b['rencana']) }}</td>
            <td class="num text-emerald-700">{{ rp($b['penerimaan']) }}</td>
            <td class="num font-semibold">{{ rp($b['belanja']) }}</td>
            <td class="num {{ $b['sisa'] < 0 ? 'text-rose-700' : '' }}">{{ rp($b['sisa']) }}</td>
            <td class="num">{{ $b['serapan'] === null ? '—' : $b['serapan'].'%' }}</td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-slate-400 py-6">Belum ada data sumber dana untuk TA {{ $tahun }}.</td></tr>
        @endforelse
      </tbody>
      @if($baris)
        <tfoot>
          <tr class="font-bold">
            <td>TOTAL</td>
            <td class="num">{{ rp($total['pagu']) }}</td>
            <td class="num">{{ rp($total['rencana']) }}</td>
            <td class="num">{{ rp($total['penerimaan']) }}</td>
            <td class="num">{{ rp($total['belanja']) }}</td>
            <td class="num">{{ rp($total['sisa']) }}</td>
            <td class="num">{{ $total['serapan'] === null ? '—' : $total['serapan'].'%' }}</td>
          </tr>
        </tfoot>
      @endif
    </table>
  </div>
  <p class="text-[11px] text-slate-400 mt-3">Belanja per sumber dana mengikuti jenis BOS pada transaksi; rencana mengikuti RKAS per sumber dana. Sisa = pagu − belanja.</p>
</div>
@endsection
