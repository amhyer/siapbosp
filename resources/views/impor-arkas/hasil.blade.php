@extends('layouts.app', ['title' => 'Hasil Impor ARKAS'])
@section('subtitle', 'NPSN ' . $lap['npsn'] . ' · Tahun ' . $lap['tahun'])
@section('content')
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
  <div class="card"><div class="text-xs text-slate-500">Transaksi dibuat</div><div class="text-2xl font-bold text-emerald-700">{{ $lap['dibuat']['transaksi'] }}</div><div class="text-[11px] text-slate-400">diperbarui: {{ $lap['diperbarui']['transaksi'] }}</div></div>
  <div class="card"><div class="text-xs text-slate-500">Rincian RKAS dibuat</div><div class="text-2xl font-bold text-indigo-700">{{ $lap['dibuat']['rkas'] }}</div><div class="text-[11px] text-slate-400">diperbarui: {{ $lap['diperbarui']['rkas'] }}</div></div>
  <div class="card"><div class="text-xs text-slate-500">Pajak diterima / disetor</div><div class="text-2xl font-bold text-amber-700">{{ $lap['dibuat']['pajak_terima'] }} / {{ $lap['dibuat']['pajak_setor'] }}</div><div class="text-[11px] text-slate-400">diperbarui: {{ $lap['diperbarui']['pajak_terima'] }} / {{ $lap['diperbarui']['pajak_setor'] }}</div></div>
  <div class="card"><div class="text-xs text-slate-500">Baris dilewati</div><div class="text-2xl font-bold text-slate-500">{{ array_sum($lap['dilewati']) }}</div><div class="text-[11px] text-slate-400">rkas {{ $lap['dilewati']['rkas'] }} · trx {{ $lap['dilewati']['transaksi'] }} · pajak {{ $lap['dilewati']['pajak'] }}@if(!empty($lap['digabung']['transaksi'])) · digabung {{ $lap['digabung']['transaksi'] }}@endif</div></div>
</div>

@if($lap['peringatan'])
<div class="alert alert-warn"><span>⚠️</span><ul class="list-disc pl-5">@foreach($lap['peringatan'] as $w)<li>{{ $w }}</li>@endforeach</ul></div>
@endif

@if($lap['rekening_tak_dikenal'])
<div class="card">
  <h3 class="card-title mb-2">🧩 Kode rekening tak dikenal ({{ count($lap['rekening_tak_dikenal']) }})</h3>
  <p class="page-desc mb-2">Minta Dinas menambahkan kode ini ke master, lalu impor ulang file yang sama.</p>
  <div class="flex flex-wrap gap-1.5">@foreach($lap['rekening_tak_dikenal'] as $k)<code class="rounded bg-slate-100 px-2 py-0.5 font-mono text-[11px]" title="{{ $lap['rekening_asing_nama'][$k] ?? '' }}">{{ $k }}</code>@endforeach</div>
</div>
@endif

@if(!empty($lap['selisih_pagu']))
<div class="card">
  <h3 class="card-title mb-2">💰 Selisih pagu SiapBOS vs ARKAS (tidak ditimpa)</h3>
  <ul class="list-disc pl-5 text-[13px] text-slate-600 space-y-0.5">@foreach($lap['selisih_pagu'] as $w)<li>{{ $w }}</li>@endforeach</ul>
</div>
@endif

@if(!empty($lap['info_salur']) || !empty($lap['info_ptk']))
<div class="card">
  <h3 class="card-title mb-2">🔗 Info silang ARKAS</h3>
  <ul class="list-disc pl-5 text-[13px] text-slate-600 space-y-0.5">
    @if(!empty($lap['info_salur']))<li>Dana salur ARKAS {{ rp($lap['info_salur']['salur']) }} vs penerimaan tercatat {{ rp($lap['info_salur']['cair']) }}.</li>@endif
    @if(!empty($lap['info_ptk']))<li>{{ $lap['info_ptk'] }}</li>@endif
  </ul>
</div>
@endif

@if($lap['periode_terkunci'])
<div class="card">
  <h3 class="card-title mb-2">🔒 Periode terkunci — dilewati ({{ count($lap['periode_terkunci']) }})</h3>
  <ul class="list-disc pl-5 text-[13px] text-slate-600 space-y-0.5">@foreach(array_slice($lap['periode_terkunci'], 0, 30) as $w)<li>{{ $w }}</li>@endforeach</ul>
  @if(count($lap['periode_terkunci']) > 30)<p class="text-xs text-slate-400 mt-1">…dan {{ count($lap['periode_terkunci']) - 30 }} lainnya.</p>@endif
</div>
@endif

@if($lap['selisih_hapus'])
<div class="card">
  <h3 class="card-title mb-2">📋 Ada di SiapBOS tapi tak ada di file (tidak dihapus)</h3>
  <ul class="list-disc pl-5 text-[13px] text-slate-600 space-y-0.5">@foreach(array_slice($lap['selisih_hapus'], 0, 30) as $w)<li>{{ $w }}</li>@endforeach</ul>
  @if(count($lap['selisih_hapus']) > 30)<p class="text-xs text-slate-400 mt-1">…dan {{ count($lap['selisih_hapus']) - 30 }} lainnya.</p>@endif
</div>
@endif

@if($lap['alasan_lewat'])
<div class="card">
  <h3 class="card-title mb-2">📝 Keterangan baris dilewati</h3>
  <ul class="list-disc pl-5 text-[13px] text-slate-600 space-y-0.5">@foreach(array_slice(array_unique($lap['alasan_lewat']), 0, 30) as $w)<li>{{ $w }}</li>@endforeach</ul>
</div>
@endif

<div class="flex flex-wrap gap-2">
  <a href="{{ route('impor-arkas.form') }}" class="btn2 btn-sm">← Impor file lain</a>
  <a href="{{ route('dashboard') }}" class="btn2 btn-sm">📊 Dashboard</a>
</div>
@endsection
