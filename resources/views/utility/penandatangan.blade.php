@extends('layouts.app', ['title' => 'Penandatangan'])
@section('content')
<div class="grid lg:grid-cols-3 gap-5">
  <div class="card">
    <h3 class="font-semibold text-slate-700 mb-3">Tambah Penandatangan</h3>
    <form method="post" action="{{ route('penandatangan.store') }}" class="space-y-3">
      @csrf
      <div><label class="text-xs font-semibold text-slate-600">Jabatan</label>
        <select name="jabatan" class="inp mt-1">
          <option>Kepala Sekolah</option><option>Bendahara</option>
          <option>Ketua Tim Manajemen BOS</option><option>Komite Sekolah</option>
        </select></div>
      <div><label class="text-xs font-semibold text-slate-600">Nama</label><input name="nama" class="inp mt-1" required></div>
      <div><label class="text-xs font-semibold text-slate-600">NIP</label><input name="nip" class="inp mt-1"></div>
      <div><label class="text-xs font-semibold text-slate-600">Pangkat/Gol.</label><input name="pangkat" class="inp mt-1"></div>
      <button class="btn">➕ Tambah</button>
    </form>
  </div>
  <div class="card lg:col-span-2">
    <h3 class="font-semibold text-slate-700 mb-1">Daftar Penandatangan</h3>
    <p class="text-xs text-slate-500 mb-3">Unggah <b>gambar tanda tangan basah</b> (PNG/JPG, latar transparan paling bagus, maks. 1 MB) — akan tercetak otomatis di <b>LPJ & RKAS (cetak/PDF)</b>. Saran: tanda tangan di kertas putih, difoto, lalu dipangkas rapat.</p>
    <table class="tbl">
      <thead><tr><th>Jabatan</th><th>Nama</th><th>NIP</th><th>Pangkat</th><th>TTD Basah</th><th></th></tr></thead>
      <tbody>
        @forelse($items as $p)
        @php($g = \App\Services\TtdGambar::dataUri($p->ttd_path))
        <tr><td>{{ $p->jabatan }}</td><td>{{ $p->nama }}</td><td>{{ $p->nip ?? '—' }}</td><td>{{ $p->pangkat ?? '—' }}</td>
          <td class="min-w-44">
            @if($g)
              <div class="flex items-center gap-2">
                <img src="{{ $g }}" style="height:36px;max-width:110px;border:1px solid #e2e8f0;border-radius:4px;background:#fff">
                <form method="post" action="{{ route('penandatangan.ttd.hapus', $p) }}">@csrf @method('DELETE')
                  <button class="text-[11px] text-red-500 font-semibold">hapus ✕</button>
                </form>
              </div>
            @endif
            <form method="post" action="{{ route('penandatangan.ttd', $p) }}" enctype="multipart/form-data" class="flex items-center gap-1 mt-1">
              @csrf
              <input type="file" name="ttd" accept="image/png,image/jpeg" class="text-[10px] w-40" required>
              <button class="badge bdan whitespace-nowrap">⬆ unggah</button>
            </form>
          </td>
          <td class="text-right"><form method="post" action="{{ route('penandatangan.destroy', $p) }}" onsubmit="return confirm('Hapus?')">@csrf @method('DELETE')<button class="badge bdel">hapus</button></form></td></tr>
        @empty
        <tr><td colspan="6" class="text-center text-slate-400 py-6">Belum ada penandatangan.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
