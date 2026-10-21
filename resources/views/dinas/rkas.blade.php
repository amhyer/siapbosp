@extends('layouts.app', ['title' => 'RKAS Masuk'])
@section('content')
@php
  $badge = [
    'dikirim_dinas' => 'bg-indigo-100 text-indigo-700',
    'revisi'        => 'bg-rose-100 text-rose-700',
    'disahkan'      => 'bg-emerald-100 text-emerald-700',
    'dikunci'       => 'bg-slate-200 text-slate-600',
    'disetujui'     => 'bg-sky-100 text-sky-700',
  ];
  $label = [
    'dikirim_dinas' => 'MENUNGGU TINJAUAN', 'revisi' => 'MINTA REVISI',
    'disahkan' => 'DISAHKAN', 'dikunci' => 'DIKUNCI', 'disetujui' => 'DISETUJUI KEPSEK',
  ];
@endphp
<div class="flex flex-wrap justify-between items-center gap-2">
  <p class="text-sm text-slate-500">Antrean RKAS yang dikirim sekolah untuk disahkan Dinas.</p>
  @if($pending > 0)
    <span class="badge bg-amber-100 text-amber-700">⏳ {{ $pending }} menunggu tinjauan</span>
  @endif
</div>
<div class="card overflow-x-auto">
  <table class="tbl">
    <thead><tr><th>#</th><th>Sekolah</th><th>TA</th><th>Revisi</th><th class="num">Total RKAS</th><th>Dikirim</th><th>Status</th><th>Catatan Dinas</th><th></th></tr></thead>
    <tbody>
      @forelse($items as $h)
      <tr>
        <td class="text-xs">{{ $h->id }}</td>
        <td class="font-semibold text-sm">{{ $h->school->nama ?? '?' }}
          <div class="text-[11px] text-slate-400 font-normal">{{ $h->school->npsn ?? '' }}</div></td>
        <td class="text-sm">{{ $h->tahun }}</td>
        <td class="text-sm">ke-{{ $h->revisi_ke }}</td>
        <td class="num font-semibold text-indigo-700">{{ rp($h->total()) }}</td>
        <td class="text-xs">{{ $h->dikirim_dinas_at?->format('d/m/Y H:i') ?? '—' }}</td>
        <td><span class="badge {{ $badge[$h->status] ?? 'bg-slate-100' }}">{{ $label[$h->status] ?? strtoupper($h->status) }}</span>
          @if($h->ditinjau_at)<div class="text-[10px] text-slate-400 mt-1">oleh {{ $h->ditinjau_oleh }} · {{ $h->ditinjau_at->format('d/m/Y') }}</div>@endif
        </td>
        <td class="text-xs text-slate-500 max-w-52">{{ $h->catatan_dinas ?? '—' }}</td>
        <td class="whitespace-nowrap">
          <a href="{{ route('dinas.rkas.pdf', $h->id) }}" class="btn2 btn text-xs" title="Unduh PDF">📕</a>
          <a href="{{ route('dinas.rkas.xlsx', $h->id) }}" class="btn2 btn text-xs" title="Unduh Excel">📗</a>
          <a href="{{ route('dinas.rkas.show', $h->id) }}" class="btn2 btn text-xs">👁️ Tinjau</a>
        </td>
      </tr>
      @empty
      <tr><td colspan="9" class="text-center text-slate-400 py-8">Belum ada RKAS yang dikirim sekolah.<br>
        <span class="text-xs">Sekolah mengirim dari halaman RKAS setelah disetujui Kepala Sekolah.</span></td></tr>
      @endforelse
    </tbody>
  </table>
  {{ $items->links() }}
</div>
@endsection
