@extends('layouts.app', ['title' => 'Pengaturan — Koneksi ARKAS'])
@section('subtitle', 'Hubungan aplikasi ini dengan ARKAS & website pusat')
@section('content')
<div class="grid lg:grid-cols-2 gap-4">
  <div class="card">
    <h3 class="card-title mb-3">🔌 Koneksi ARKAS (laptop sekolah)</h3>
    <div class="space-y-2 text-[13px]">
      <div class="flex items-center justify-between border-b border-slate-100 pb-2">
        <span class="text-slate-500">Aplikasi portabel</span>
        <span class="badge {{ $portabel ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $portabel ? '✅ Ya (laptop sekolah)' : 'ℹ️ Tidak (server pusat)' }}</span>
      </div>
      <div class="flex items-center justify-between border-b border-slate-100 pb-2">
        <span class="text-slate-500">Database ARKAS terdeteksi</span>
        <span class="badge {{ ($tarik['db_ada'] ?? false) ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ ($tarik['db_ada'] ?? false) ? '✅ Ditemukan' : '— belum ada —' }}</span>
      </div>
      <div class="flex items-center justify-between border-b border-slate-100 pb-2">
        <span class="text-slate-500">Versi ARKAS</span>
        <span class="text-slate-700">Lihat di <a class="underline" href="{{ route('pengaturan.versi') }}">Deteksi Versi</a></span>
      </div>
    </div>
    <div class="mt-3 flex flex-wrap gap-2">
      <a href="{{ route('impor-arkas.form') }}" class="btn2 btn-sm">⬆️ Tarik/Impor Data ARKAS</a>
      <a href="{{ route('impor-arkas.unduh') }}" class="btn2 btn-sm">⬇️ Unduh Agen</a>
      <a href="{{ route('impor-arkas.unduhGui') }}" class="btn2 btn-sm">⬇️ Aplikasi GUI</a>
      <a href="{{ route('profil.edit') }}" class="btn2 btn-sm">⚡ Tarik 1-klik (Profil)</a>
    </div>
  </div>

  <div class="card">
    <h3 class="card-title mb-3">🌐 Website Pusat</h3>
    <div class="space-y-2 text-[13px]">
      <div class="flex items-center justify-between border-b border-slate-100 pb-2">
        <span class="text-slate-500">Alamat pusat</span>
        <span class="font-mono text-[11px] text-slate-700">{{ $conf['PUSAT_URL'] ?? '— belum diatur —' }}</span>
      </div>
      <div class="flex items-center justify-between border-b border-slate-100 pb-2">
        <span class="text-slate-500">NPSN akun</span>
        <span class="font-mono text-[11px] text-slate-700">{{ $conf['NPSN'] ?? '—' }}</span>
      </div>
      <div class="flex items-center justify-between border-b border-slate-100 pb-2">
        <span class="text-slate-500">Token API</span>
        <span class="badge {{ $status['token_ada'] ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ $status['token_ada'] ? '✅ Tersedia' : '⚠️ Belum ada' }}</span>
      </div>
      <div class="flex items-center justify-between">
        <span class="text-slate-500">Terakhir didorong</span>
        <span class="text-slate-700">{{ $status['terakhir_dorong'] ?? '— belum pernah —' }}</span>
      </div>
    </div>
    <div class="mt-3 flex flex-wrap gap-2">
      <form method="post" action="{{ route('pengaturan.koneksi.uji') }}">@csrf
        <button class="btn2 btn-sm">🔎 Periksa Koneksi</button>
      </form>
      <a href="{{ route('pengaturan.sinkronisasi') }}" class="btn2 btn-sm">🔄 Sinkronisasi</a>
      <a href="{{ route('unduh-identitas') }}" class="btn2 btn-sm">🔑 Unduh Identitas Sekolah</a>
    </div>
    <p class="text-[11px] text-slate-400 mt-2">Identitas pusat dibaca dari berkas <code class="bg-slate-100 px-1 rounded">launcher-windows/akun-sekolah.conf</code> (diisi otomatis saat pemasangan dari berkas unduhan).</p>
  </div>
</div>
@endsection
