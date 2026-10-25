@extends('layouts.app', ['title' => 'LPJ Masuk dari Sekolah'])
@section('content')
@php
  $badge = [
    'dikirim'  => 'bg-amber-100 text-amber-700',
    'diterima' => 'bg-emerald-100 text-emerald-700',
    'ditolak'  => 'bg-rose-100 text-rose-700',
  ];
@endphp
<div class="card">
  <div class="flex items-center justify-between mb-3">
    <h3 class="font-semibold text-slate-700">📥 Antrean Pertanggungjawaban (LPJ)</h3>
    @if($pending > 0)
      <span class="badge bg-amber-100 text-amber-700">⏳ {{ $pending }} menunggu tinjauan</span>
    @endif
  </div>
  <div class="overflow-x-auto">
  <table class="tbl">
    <thead><tr><th>#</th><th>Sekolah</th><th>Tahap</th><th>Dikirim</th><th>Status</th><th>Catatan Dinas</th><th>Aksi</th></tr></thead>
    <tbody>
      @forelse($subs as $sub)
      <tr>
        <td class="text-xs">{{ $sub->id }}</td>
        <td class="font-semibold text-sm">{{ $sub->school->nama }}
          <div class="text-[11px] text-slate-400 font-normal">{{ $sub->school->npsn }} · TA {{ $sub->tahun }}</div></td>
        <td class="text-sm">{{ $sub->stage->nama ?? '?' }}</td>
        <td class="text-xs">{{ $sub->created_at->format('d/m/Y H:i') }}<div class="text-slate-400">{{ $sub->dikirim_oleh }}</div></td>
        <td><span class="badge {{ $badge[$sub->status] ?? 'bg-slate-100' }}">{{ strtoupper($sub->status) }}</span>
          @if($sub->ditinjau_at)<div class="text-[10px] text-slate-400 mt-1">{{ $sub->ditinjau_oleh }} · {{ $sub->ditinjau_at->format('d/m/Y') }}</div>@endif
        </td>
        <td class="text-xs text-slate-500 max-w-52">{{ $sub->catatan_dinas ?? '—' }}</td>
        <td class="whitespace-nowrap">
          <a href="{{ route('dinas.lpj.show', $sub) }}" class="btn2 btn text-xs">👁️ Tinjau</a>          <a href="{{ route('dinas.lpj.pdf', $sub) }}" class="btn2 btn text-xs" title="Unduh PDF">📕</a>
          <a href="{{ route('dinas.lpj.xlsx', $sub) }}" class="btn2 btn text-xs" title="Unduh Excel">📗</a>

          @if($sub->status !== 'diterima')
          <form method="post" action="{{ route('dinas.lpj.terima', $sub) }}" class="inline" data-konfirmasi="Terima LPJ ini? Sekolah akan dinotifikasi.">@csrf
            <button class="btn text-xs !bg-emerald-600 hover:!bg-emerald-700">✅ Terima</button>
          </form>
          @endif
          @if($sub->status === 'dikirim')
          <details class="inline-block relative">
            <summary class="btn text-xs !bg-rose-600 hover:!bg-rose-700 cursor-pointer list-none inline-block">❌ Tolak</summary>
            <form method="post" action="{{ route('dinas.lpj.tolak', $sub) }}" class="absolute right-0 mt-2 w-72 bg-white border border-slate-200 rounded-xl shadow-xl p-3 z-50">
              @csrf
              <label class="text-xs font-semibold text-slate-600">Alasan penolakan (wajib)</label>
              <textarea name="catatan_dinas" rows="3" class="inp mt-1 text-xs" required minlength="5" placeholder="mis. BKU belum sesuai bukti, rincian pajak kurang..."></textarea>
              <button class="btn mt-2 text-xs w-full justify-center !bg-rose-600 hover:!bg-rose-700">Kirim Penolakan</button>
            </form>
          </details>
          @endif
        </td>
      </tr>
      @empty
      <tr><td colspan="7" class="text-center text-slate-400 py-8">Belum ada LPJ yang dikirim sekolah.<br><span class="text-xs">Sekolah mengirim dari menu Laporan → LPJ per Tahap setelah menutup buku.</span></td></tr>
      @endforelse
    </tbody>
  </table>
  </div>
  {{ $subs->links() }}
</div>
@endsection
