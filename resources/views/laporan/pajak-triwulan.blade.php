@extends('layouts.app', ['title' => 'Kertas Kerja Pajak Triwulan'])
@php
  $nmBulan = fn($b) => ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][$b];
  $slsNeF = fn($n) => \Carbon\Carbon::parse($n)->format('d/m/Y');
@endphp
@section('content')
<div class="flex justify-between items-center flex-wrap gap-2"> <p class="text-sm text-slate-500"> Kertas kerja pajak <b>Triwulan{{ $namaTw }}</b> TA {{ $tahun }}
    ({{ $slsNeF($awal) }} s/d {{ $slsNeF($akhir) }}): pungutan lewat kode billing vs setoran NTPN.
  </p> <div class="flex items-end gap-2"> <form method="get" class="flex items-end gap-1 text-xs"> <select name="tw" class="inp !w-auto"> @for($i = 1; $i <= 4; $i++)<option value="{{ $i }}" @selected($tw === $i)>Triwulan {{ $i }}</option>@endfor
      </select> <button class="btn text-xs"> Buka</button> </form> <a href="?tw={{ $tw }}&csv=1" class="btn2 btn"> CSV</a> </div>
</div> <div class="grid grid-cols-2 lg:grid-cols-4 gap-4"> <div class="card"><div class="text-xs text-slate-500">Dipungut (triwulan ini)</div><div class="text-xl font-bold text-indigo-700">{{ rp(collect($jenis)->sum('td')) }}</div></div> <div class="card"><div class="text-xs text-slate-500">Disetor (triwulan ini)</div><div class="text-xl font-bold text-emerald-700">{{ rp(collect($jenis)->sum('ts')) }}</div></div> <div class="card"><div class="text-xs text-slate-500">Sisa kewajiban akhir triwulan</div><div class="text-xl font-bold {{ ($totPungutAkhir - $totSetorAkhir) > 0 ? 'text-amber-600' : 'text-slate-400' }}">{{ rp($totPungutAkhir - $totSetorAkhir) }}</div></div> <div class="card"><div class="text-xs text-slate-500">Billing / setoran (triwulan)</div><div class="text-xl font-bold text-slate-700">{{ $pungut->count() }} / {{ $setoris->count() }}</div></div>
</div> <div class="card overflow-x-auto"> <h3 class="font-semibold text-slate-700 mb-2">Rekap per Jenis Pajak</h3> <table class="tbl"> <thead><tr><th>Jenis Pajak</th>@foreach($bulan as $b)<th class="num">{{ $nmBulan($b) }}</th>@endforeach<th class="num">Dipungut (TW)</th><th class="num">Disetor (TW)</th><th class="num">Sisa</th></tr></thead> <tbody> @forelse($jenis as $j)
      <tr> <td class="font-medium">{{ $j['nama'] }}</td> @foreach($bulan as $b)<td class="num">{{ ($j['dipungut'][$b] ?? 0) > 0 ? rp($j['dipungut'][$b]) : '—' }}</td>@endforeach
        <td class="num font-semibold">{{ rp($j['td']) }}</td> <td class="num text-emerald-700">{{ rp($j['ts']) }}</td> <td class="num {{ $j['sisa'] > 0 ? 'text-amber-600 font-semibold' : 'text-slate-300' }}">{{ rp(max(0, $j['sisa'])) }}</td> </tr> @empty
      <tr><td colspan="{{ count($bulan) + 4 }}" class="text-center text-slate-400 py-6">Tidak ada pungutan/setoran pajak pada triwulan ini.</td></tr> @endforelse
    </tbody> @if($jenis)
    <tfoot><tr class="font-bold"> <td class="text-right">TOTAL</td> @foreach($bulan as $b)<td class="num">{{ rp(collect($jenis)->sum(fn($j) => $j['dipungut'][$b] ?? 0)) }}</td>@endforeach
      <td class="num">{{ rp(collect($jenis)->sum('td')) }}</td> <td class="num">{{ rp(collect($jenis)->sum('ts')) }}</td> <td class="num">{{ rp(max(0, collect($jenis)->sum('sisa'))) }}</td> </tr></tfoot> @endif
  </table>
</div> <div class="grid lg:grid-cols-2 gap-4"> <div class="card overflow-x-auto"> <h3 class="font-semibold text-slate-700 mb-2"> Rincian Pungutan (Kode Billing)</h3> <table class="tbl"> <thead><tr><th>Tanggal</th><th>Jenis</th><th>Kode Billing</th><th class="num">Nilai</th><th>Status</th></tr></thead> <tbody> @forelse($pungut as $p)
        <tr> <td class="whitespace-nowrap">{{ $p->transaksi?->tanggal?->format('d/m') }}</td> <td>{{ $p->rekeningPajak?->nama }}</td> <td class="font-mono text-xs">{{ $p->kode_billing ?: '—' }}</td> <td class="num">{{ rp($p->nilai) }}</td> <td>@if($p->status === 'disetor')<span class="badge" style="background:#dcfce7;color:#166534">LUNAS</span>@else<span class="badge" style="background:#fef3c7;color:#92400e">BELUM</span>@endif</td> </tr> @empty
        <tr><td colspan="5" class="text-center text-slate-400 py-4">—</td></tr> @endforelse
      </tbody> </table> </div> <div class="card overflow-x-auto"> <h3 class="font-semibold text-slate-700 mb-2"> Rincian Setoran (NTPN)</h3> <table class="tbl"> <thead><tr><th>Jenis</th><th>Kode Billing</th><th>NTPN</th><th class="num">Nilai</th></tr></thead> <tbody> @forelse($setoris as $s)
        <tr> <td>{{ $s->rekeningPajak?->nama }}</td> <td class="font-mono text-xs">{{ $s->kode_billing ?: '—' }}</td> <td class="font-mono text-xs">{{ $s->ntpn ?: '—' }}</td> <td class="num text-emerald-700">{{ rp($s->nilai) }}</td> </tr> @empty
        <tr><td colspan="4" class="text-center text-slate-400 py-4">—</td></tr> @endforelse
      </tbody> </table> </div>
</div>
@endsection
