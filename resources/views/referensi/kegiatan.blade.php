@extends('layouts.app', ['title' => 'Daftar Kegiatan'])
@section('subtitle', 'Kode kegiatan resmi ARKAS (Ref_Kode) — acuan menyusun RKAS')
@section('content')
<div class="card">
  <div class="flex flex-wrap items-end gap-2 mb-3">
    <form method="get" action="{{ route('penganggaran.kegiatan') }}" class="flex gap-2 items-end">
      <div>
        <label class="lbl">Cari kode / uraian</label>
        <input name="cari" value="{{ $cari }}" class="inp" placeholder="mis. 05.08 atau Pemeliharaan">
      </div>
      <button class="btn2 btn">🔍 Cari</button>
    </form>
    @if($cari !== '')
      <a href="{{ route('penganggaran.kegiatan') }}" class="btn2 btn-sm">Reset</a>
    @endif
    <span class="text-[11px] text-slate-500">{{ number_format($kegiatan->total()) }} kode kegiatan</span>
  </div>

  <div class="overflow-x-auto">
    <table class="tbl">
      <thead><tr><th>Kode Kegiatan</th><th>Uraian</th><th>Level</th></tr></thead>
      <tbody>
        @forelse($kegiatan as $k)
          <tr>
            <td class="font-mono font-semibold">{{ $k->kode }}</td>
            <td>{{ $k->uraian }}</td>
            <td class="text-[11px] text-slate-500">{{ $k->level }}</td>
          </tr>
        @empty
          <tr><td colspan="3" class="text-center text-slate-400 py-6">Tidak ada kode kegiatan yang cocok. Data ini berasal dari impor/tarik ARKAS.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($kegiatan->hasPages())
    <div class="mt-3">{{ $kegiatan->links() }}</div>
  @endif

  <p class="text-[11px] text-slate-400 mt-3">Kode kegiatan ini dipakai untuk cek kepatuhan Juknis BOSP 2026 (honor 07.12.x, buku 05.02.x, sarpras 05.08.x) dan otomatis terisi saat data ARKAS ditarik.</p>
</div>
@endsection
