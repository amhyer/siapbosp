@extends('layouts.app', ['title' => 'Pengumuman'])
@section('content')
<div class="grid lg:grid-cols-3 gap-5">
  <div class="card">
    <h3 class="font-semibold text-slate-700 mb-3">Terbitkan Pengumuman</h3>
    <form method="post" action="{{ route('dinas.pengumuman.store') }}" class="space-y-3">
      @csrf
      <div><label class="text-xs font-semibold text-slate-600">Judul</label><input name="judul" class="inp mt-1" required></div>
      <div><label class="text-xs font-semibold text-slate-600">Isi</label><textarea name="isi" class="inp mt-1" rows="5" required></textarea></div>
      <button class="btn">📣 Terbitkan (semua sekolah)</button>
    </form>
  </div>
  <div class="card lg:col-span-2 space-y-3">
    @forelse($items as $p)
    <div class="border border-slate-200 rounded-lg p-4">
      <div class="flex justify-between">
        <div class="font-semibold">{{ $p->judul }}</div>
        <form method="post" action="{{ route('dinas.pengumuman.destroy', $p) }}" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="badge bdel">hapus</button></form>
      </div>
      <div class="text-sm text-slate-600 mt-1">{{ $p->isi }}</div>
      <div class="text-[11px] text-slate-400 mt-2">{{ $p->created_at->format('d/m/Y H:i') }} · {{ $p->creator->name ?? '' }}</div>
    </div>
    @empty
    <p class="text-sm text-slate-400">Belum ada pengumuman.</p>
    @endforelse
    {{ $items->links() }}
  </div>
</div>
@endsection
