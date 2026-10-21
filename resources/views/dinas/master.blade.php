@extends('layouts.app', ['title' => 'Master Rekening & Komponen'])
@section('content')
<div class="grid sm:grid-cols-3 gap-4">
  <div class="card"><div class="text-xs text-slate-500">Komponen Belanja</div><div class="text-2xl font-bold text-indigo-700">{{ $komponens->count() }}</div></div>
  <div class="card"><div class="text-xs text-slate-500">Rekening Belanja</div><div class="text-2xl font-bold text-rose-700">{{ $rekeningBelanja }}</div></div>
  <div class="card"><div class="text-xs text-slate-500">Rekening Pendapatan</div><div class="text-2xl font-bold text-emerald-700">{{ $rekeningPendapatan }}</div></div>
</div>

<div class="card">
  <h3 class="font-semibold text-slate-700 text-sm mb-1">📥 Impor Rekening & Komponen dari CSV</h3>
  <p class="text-[11px] text-slate-400 mb-2">Format per baris: <code>kode_rekening;nama_rekening;kode_komponen;nama_komponen;tipe</code>
    — komponen & tipe opsional (tipe default <code>belanja</code>, pilihan: <code>belanja</code>/<code>pendapatan</code>).
    Kode yang sudah ada akan <b>diperbarui</b>, bukan digandakan; komponen baru dibuat otomatis.
    <a href="{{ route('dinas.master.template') }}" class="text-indigo-600 underline">Unduh contoh CSV</a></p>
  @if(session('ok'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs rounded px-3 py-2 mb-2">{{ session('ok') }}</div>@endif
  @if(session('error'))<div class="bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded px-3 py-2 mb-2">{{ session('error') }}</div>@endif
  <form method="post" action="{{ route('dinas.master.impor') }}" enctype="multipart/form-data" class="flex gap-2 items-center">
    @csrf
    <input type="file" name="berkas" accept=".csv,.txt" class="inp" required>
    <button class="btn2 btn whitespace-nowrap">Unggah & Sinkronkan</button>
  </form>
</div>

<div class="card overflow-x-auto">
  <h3 class="font-semibold text-slate-700 text-sm mb-3">Daftar Komponen & Rekening Belanja</h3>
  <table class="tbl">
    <thead><tr><th style="width:12%;">Kode Komponen</th><th>Komponen</th><th class="num">Jml Rekening</th><th>Rekening (kode — nama)</th></tr></thead>
    <tbody>
      @forelse($komponens as $k)
      <tr>
        <td class="font-mono">{{ $k->kode }}</td>
        <td class="font-semibold text-sm">{{ $k->nama }}</td>
        <td class="num">{{ $k->rekenings_count }}</td>
        <td class="text-[11px] text-slate-500 leading-5">
          @foreach($k->rekenings->sortBy('kode') as $r)<span class="inline-block bg-slate-50 border border-slate-200 rounded px-1 mr-1 mb-1">{{ $r->kode }} — {{ $r->nama }}</span>@endforeach
        </td>
      </tr>
      @empty
      <tr><td colspan="4" class="text-center text-slate-400 py-6">Belum ada komponen.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="card overflow-x-auto">
  <h3 class="font-semibold text-slate-700 text-sm mb-3">Rekening Pendapatan & Tanpa Komponen</h3>
  <table class="tbl">
    <thead><tr><th style="width:18%;">Kode</th><th>Nama</th><th style="width:14%;">Tipe</th></tr></thead>
    <tbody>
      @forelse(\App\Models\Rekening::whereNull('komponen_id')->orderBy('kode')->get() as $r)
      <tr><td class="font-mono">{{ $r->kode }}</td><td class="text-sm">{{ $r->nama }}</td>
        <td><span class="badge {{ $r->tipe === 'pendapatan' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $r->tipe }}</span></td></tr>
      @empty
      <tr><td colspan="3" class="text-center text-slate-400 py-4">Semua rekening sudah terpasang ke komponen.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
