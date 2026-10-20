@extends('layouts.app', ['title' => 'Profil Sekolah'])
@section('content')
<div class="card max-w-2xl">
  <form method="post" action="{{ route('profil.update') }}" class="grid sm:grid-cols-2 gap-4">
    @csrf @method('PUT')
    <div class="sm:col-span-2"><label class="text-xs font-semibold text-slate-600">NPSN</label>
      <input class="inp mt-1 bg-slate-50" value="{{ $sekolah->npsn }}" disabled></div>
    <div class="sm:col-span-2"><label class="text-xs font-semibold text-slate-600">Nama Sekolah</label>
      <input name="nama" class="inp mt-1" value="{{ old('nama', $sekolah->nama) }}" required></div>
    <div><label class="text-xs font-semibold text-slate-600">Jenjang</label>
      <select name="jenjang" class="inp mt-1">@foreach(['SD','SMP','SMA','SMK','SLB'] as $j)<option @selected($sekolah->jenjang===$j)>{{ $j }}</option>@endforeach</select></div>
    <div><label class="text-xs font-semibold text-slate-600">Desa / Kelurahan</label>
      <input name="desa" class="inp mt-1" value="{{ old('desa', $sekolah->desa) }}"></div>
    <div><label class="text-xs font-semibold text-slate-600">Kecamatan</label>
      <input name="kecamatan" class="inp mt-1" value="{{ old('kecamatan', $sekolah->kecamatan) }}"></div>
    <div><label class="text-xs font-semibold text-slate-600">Kabupaten/Kota</label>
      <input name="kab_kota" class="inp mt-1" value="{{ old('kab_kota', $sekolah->kab_kota) }}"></div>
    <div><label class="text-xs font-semibold text-slate-600">Provinsi</label>
      <input name="provinsi" class="inp mt-1" value="{{ old('provinsi', $sekolah->provinsi) }}"></div>
    <div class="sm:col-span-2"><label class="text-xs font-semibold text-slate-600">Alamat</label>
      <input name="alamat" class="inp mt-1" value="{{ old('alamat', $sekolah->alamat) }}"></div>
    <div class="sm:col-span-2"><button class="btn">💾 Simpan Profil</button></div>
  </form>
</div>

<div class="card max-w-2xl mt-5">
  <h3 class="font-semibold text-slate-700 mb-1">🔑 Token API (Integrasi)</h3>
  <p class="text-xs text-slate-500 mb-3">Dipakai aplikasi lain untuk membaca data sekolah ini via <code class="bg-slate-100 px-1 rounded">/api/v1</code> (baca-saja). Simpan rahasia — siapa pun yang memegang token bisa membaca data Anda.</p>
  @if(session('token_baru'))
    <div class="bg-slate-900 text-emerald-300 font-mono text-xs rounded p-3 break-all select-all">{{ session('token_baru') }}</div>
    <div class="mt-2 text-[11px] font-semibold text-amber-600">⚠️ Salin sekarang — token hanya ditampilkan sekali ini. Nilainya disimpan ter-hash sehingga tidak bisa dilihat lagi nanti.</div>
    <div class="mt-3 text-[11px] text-slate-500">Contoh: <code class="bg-slate-100 px-1 rounded break-all">curl -H "Authorization: Bearer &lt;token&gt;" {{ url('/api/v1/saldo') }}</code></div>
  @elseif(auth()->user()->api_token)
    <div class="text-sm text-slate-600 mb-2">✅ Token aktif — nilai aslinya tersimpan ter-hash (terenkripsi satu arah) dan tidak dapat ditampilkan lagi. Pakai salinan yang Anda simpan saat membuat token.</div>
    <div class="mt-3 text-[11px] text-slate-500">Contoh: <code class="bg-slate-100 px-1 rounded break-all">curl -H "Authorization: Bearer &lt;token&gt;" {{ url('/api/v1/saldo') }}</code></div>
  @else
    <div class="text-sm text-slate-400 italic mb-3">Belum ada token. Buat satu untuk mulai integrasi.</div>
  @endif
  <form method="post" action="{{ route('api-token.store') }}" class="mt-3" onsubmit="return confirm('Buat token baru? Token lama akan langsung tidak berlaku.')">
    @csrf
    <button class="btn">♻️ {{ auth()->user()->api_token ? 'Buat Ulang Token' : 'Buat Token' }}</button>
  </form>
</div>
@endsection
