@extends('layouts.app', ['title' => 'Tahun Anggaran'])
@section('subtitle', 'Pagu, rencana, dan realisasi tiap tahun anggaran sekolah ini')
@section('content')
<div class="card">
  <h3 class="card-title mb-3 flex items-center gap-2"><x-icon name="calendar" class="h-4 w-4 text-slate-500"/> Tahun Anggaran</h3>
  <div class="overflow-x-auto">
    <table class="tbl">
      <thead><tr><th>Tahun</th><th class="num">Pagu</th><th class="num">Rencana (RKAS)</th><th class="num">Realisasi</th><th class="num">Sisa Pagu</th><th class="num">Serapan</th><th class="num">Transaksi</th><th>Tahap Tertutup</th></tr></thead>
      <tbody>
        @forelse($baris as $b)
          <tr class="{{ $b['aktif'] ? 'bg-indigo-50/60' : '' }}">
            <td class="font-semibold">{{ $b['tahun'] }} @if($b['aktif'])<span class="badge bg-indigo-100 text-indigo-700">aktif</span>@endif</td>
            <td class="num">{{ rp($b['pagu']) }}</td>
            <td class="num">{{ rp($b['rencana']) }}</td>
            <td class="num font-semibold">{{ rp($b['realisasi']) }}</td>
            <td class="num {{ $b['sisa'] < 0 ? 'text-rose-700' : '' }}">{{ rp($b['sisa']) }}</td>
            <td class="num">{{ $b['serapan'] }}%</td>
            <td class="num">{{ $b['trx'] }}</td>
            <td class="text-[11px] text-slate-500">{{ $b['tutup_tahap'] }} tahap</td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-slate-400 py-6">Belum ada pagu yang terdaftar. Isi Master Pagu (Dinas) atau tarik data ARKAS.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <p class="text-[11px] text-slate-400 mt-3">Tahun anggaran aktif dipakai seluruh aplikasi; ganti lewat pemilih tahun di header.</p>
</div>
@endsection
