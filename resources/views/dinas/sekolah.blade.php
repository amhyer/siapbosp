@extends('layouts.app', ['title' => 'Data Sekolah'])
@section('content')
<div class="grid lg:grid-cols-3 gap-5">
  <div class="card">
    <h3 class="font-semibold text-slate-700 mb-3">Tambah Sekolah</h3>
    <form method="post" action="{{ route('dinas.sekolah.store') }}" class="space-y-3">
      @csrf
      <div><label class="text-xs font-semibold text-slate-600">NPSN</label><input name="npsn" class="inp mt-1" required></div>
      <div><label class="text-xs font-semibold text-slate-600">Nama</label><input name="nama" class="inp mt-1" required></div>
      <div><label class="text-xs font-semibold text-slate-600">Jenjang</label>
        <select name="jenjang" class="inp mt-1">@foreach(['SD','SMP','SMA','SMK','SLB'] as $j)<option>{{ $j }}</option>@endforeach</select></div>
      <div><label class="text-xs font-semibold text-slate-600">Desa/Kelurahan</label><input name="desa" class="inp mt-1"></div>
      <div><label class="text-xs font-semibold text-slate-600">Kecamatan</label><input name="kecamatan" class="inp mt-1"></div>
      <div><label class="text-xs font-semibold text-slate-600">Kab/Kota</label><input name="kab_kota" class="inp mt-1" value="Kota Makassar"></div>
      <div><label class="text-xs font-semibold text-slate-600">Provinsi</label><input name="provinsi" class="inp mt-1" value="Sulawesi Selatan"></div>
      <div><label class="text-xs font-semibold text-slate-600">Alamat</label><input name="alamat" class="inp mt-1"></div>
      <button class="btn">➕ Simpan Sekolah</button>
    </form>
    <div class="border-t border-slate-200 mt-5 pt-4">
      <h4 class="font-semibold text-slate-700 text-sm mb-1">📥 Impor Massal (CSV)</h4>
      <p class="text-[11px] text-slate-400 mb-2">Format kolom: <code>npsn;nama;jenjang;kecamatan</code> (pemisah ; atau ,). Baris pertama berisi judul akan dilewati.</p>
      <form method="post" action="{{ route('dinas.sekolah.impor') }}" enctype="multipart/form-data" class="space-y-2">
        @csrf
        <input type="file" name="berkas" accept=".csv,.txt" class="inp" required>
        <button class="btn2 btn w-full justify-center">Unggah & Impor</button>
      </form>
    </div>
  </div>
  <div class="card lg:col-span-2 overflow-x-auto">
    <h3 class="font-semibold text-slate-700 mb-3">Daftar Sekolah ({{ $schools->count() }})</h3>
    <table class="tbl">
      <thead><tr><th>NPSN</th><th>Nama</th><th>Jenjang</th><th>Kecamatan</th><th>Operator/Kepsek</th><th>Status</th><th></th></tr></thead>
      <tbody>
        @foreach($schools as $s)
        <tr>
          <td class="font-mono">{{ $s->npsn }}</td><td><a href="{{ route('dinas.sekolah.show', $s) }}" class="text-indigo-700 hover:underline font-semibold">{{ $s->nama }}</a></td><td>{{ $s->jenjang }}</td><td>{{ $s->kecamatan ?? '—' }}</td>
          <td class="text-xs">@foreach($s->users as $usr)<div>{{ $usr->name }} <span class="text-slate-400">({{ $usr->role }})</span></div>@endforeach</td>
          <td>{!! $s->is_active ? '<span class="badge bg-emerald-100 text-emerald-700">aktif</span>' : '<span class="badge bg-slate-200 text-slate-500">nonaktif</span>' !!}</td>
          <td class="whitespace-nowrap">
            <form method="post" action="{{ route('dinas.sekolah.toggle', $s) }}" class="inline">@csrf<button class="btn2 btn text-xs">{{ $s->is_active?'Nonaktifkan':'Aktifkan' }}</button></form>
            <form method="post" action="{{ route('dinas.sekolah.hapusdata', $s) }}" class="inline" data-konfirmasi="HAPUS SELURUH DATA {{ $s->nama }}? Transaksi, RKAS, tutup buku, LPJ ikut terhapus permanen.">@csrf @method('DELETE')<button class="btn text-xs !bg-rose-600 hover:!bg-rose-700">🗑️</button></form>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection
