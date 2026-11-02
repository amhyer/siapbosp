@extends('layouts.app', ['title' => 'Data Penanggung Jawab'])
@section('subtitle', 'Penandatangan dokumen sekolah, data profil sekolah, tahun anggaran, sumber dana, dan status sinkronisasi ke pusat')

@section('content')
{{-- Pintasan Data Sekolah — di ARKAS 4 item ini ada di dropdown profil;
     di SiapBOSP dipindah ke halaman ini agar dropdown tetap dua item. --}}
<div class="card">
  <h3 class="card-title">Data Sekolah</h3>
  <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
    <a href="{{ route('profil.edit') }}" class="btn2 justify-start">
      <x-icon name="book" class="h-4 w-4"/> Profil Sekolah
    </a>
    <a href="{{ route('datasekolah.tahun') }}" class="btn2 justify-start">
      <x-icon name="calendar" class="h-4 w-4"/> Tahun Anggaran
    </a>
    <a href="{{ route('datasekolah.sumber-dana') }}" class="btn2 justify-start">
      <x-icon name="bank" class="h-4 w-4"/> Sumber Dana
    </a>
    <a href="{{ route('datasekolah.sinkronisasi') }}" class="btn2 justify-start">
      <x-icon name="refresh" class="h-4 w-4"/> Status Sinkronisasi ke Pusat
    </a>
  </div>
</div>

<div class="grid lg:grid-cols-3 gap-5">
  <div class="card">
    <h3 class="card-title mb-3">Tambah Penandatangan</h3>
    <form method="post" action="{{ route('penandatangan.store') }}" class="space-y-3">
      @csrf
      <div><label class="lbl">Jabatan</label>
        <select name="jabatan" class="inp mt-1">
          <option>Kepala Sekolah</option><option>Bendahara</option>
          <option>Ketua Tim Manajemen BOS</option><option>Komite Sekolah</option>
        </select></div>
      <div><label class="lbl">Nama</label><input name="nama" class="inp mt-1" required></div>
      <div><label class="lbl">NIP</label><input name="nip" class="inp mt-1"></div>
      <div><label class="lbl">Pangkat/Gol.</label><input name="pangkat" class="inp mt-1"></div>
      <button class="btn"><x-icon name="plus" class="h-4 w-4"/> Tambah</button>
    </form>
  </div>
  <div class="card lg:col-span-2">
    <h3 class="card-title mb-1">Daftar Penandatangan</h3>
    <p class="text-xs text-slate-500 mb-3">Unggah <b>gambar tanda tangan basah</b> (PNG/JPG, latar transparan paling bagus, maks. 1 MB) — akan tercetak otomatis di <b>LPJ &amp; RKAS (cetak/PDF)</b>. Saran: tanda tangan di kertas putih, difoto, lalu dipangkas rapat.</p>
    <div class="tbl-wrap">
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
                    <button class="text-[11px] text-red-600 font-semibold">Hapus</button>
                  </form>
                </div>
              @endif
              <form method="post" action="{{ route('penandatangan.ttd', $p) }}" enctype="multipart/form-data" class="flex items-center gap-1 mt-1">
                @csrf
                <input type="file" name="ttd" accept="image/png,image/jpeg" class="text-[10px] w-40" required>
                <button class="badge bdan whitespace-nowrap">Unggah</button>
              </form>
            </td>
            <td class="text-right"><form method="post" action="{{ route('penandatangan.destroy', $p) }}" data-konfirmasi="Hapus?">@csrf @method('DELETE')<button class="badge bdel">Hapus</button></form></td></tr>
          @empty
          <tr><td colspan="6" class="text-center text-slate-400 py-6">Belum ada penandatangan.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
