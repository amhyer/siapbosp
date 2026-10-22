@extends('layouts.app', ['title' => 'Sumber Dana'])
@section('subtitle', 'Pagu, rencana, dan realisasi per sumber dana sekolah ini')
@section('content')
<div class="card">
  <h3 class="card-title mb-3">💼 Sumber Dana</h3>
  <div class="overflow-x-auto">
    <table class="tbl">
      <thead><tr><th>Sumber Dana</th><th class="num">Pagu</th><th class="num">Rencana (RKAS)</th><th class="num">Realisasi Belanja</th><th class="num">Sisa</th><th class="num">Rincian</th></tr></thead>
      <tbody>
        @forelse($baris as $b)
          <tr>
            <td><span class="badge bg-slate-100 text-slate-700">{{ $b['ikon'] }} {{ $b['label'] }}</span></td>
            <td class="num">{{ rp($b['pagu']) }}</td>
            <td class="num">{{ rp($b['rencana']) }}</td>
            <td class="num font-semibold">{{ rp($b['realisasi']) }}</td>
            <td class="num {{ $b['pagu'] - $b['realisasi'] < 0 ? 'text-rose-700' : '' }}">{{ rp($b['pagu'] - $b['realisasi']) }}</td>
            <td class="num">{{ $b['rincian'] }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-slate-400 py-6">Belum ada sumber dana untuk TA {{ $tahun }}.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <p class="text-[11px] text-slate-400 mt-3">Sumber dana termasuk <b>BOSP Reguler, BOS Kinerja, BOSP Daerah, BOSP Afirmasi</b> (ARKAS 4.2.16+), SILPA, dan Lainnya — master jenis BOS diatur Dinas.</p>
</div>
@endsection
