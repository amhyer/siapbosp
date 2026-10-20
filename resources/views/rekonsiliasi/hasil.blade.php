@extends('layouts.app', ['title' => 'Hasil Rekonsiliasi ' . $hasil['tahun']])
@section('subtitle', $hasil['cocok'] . ' cocok dari ' . $hasil['total_arkas'] . ' baris ARKAS')
@section('content')
@php $bulanNama = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des']; @endphp
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
  <div class="card"><div class="text-xs text-slate-500">Cocok</div><div class="text-2xl font-bold text-emerald-700">{{ $hasil['cocok'] }}</div></div>
  <div class="card"><div class="text-xs text-slate-500">Selisih nominal</div><div class="text-2xl font-bold text-amber-700">{{ count($hasil['selisih_nominal']) }}</div></div>
  <div class="card"><div class="text-xs text-slate-500">Hilang di SiapBOS</div><div class="text-2xl font-bold text-rose-700">{{ count($hasil['hilang_di_sipbos']) }}</div></div>
  <div class="card"><div class="text-xs text-slate-500">Lebih di SiapBOS</div><div class="text-2xl font-bold text-slate-500">{{ count($hasil['lebih_di_sipbos']) }}</div></div>
</div>

<div class="card">
  <h3 class="card-title mb-2">📊 Saldo berjalan per bulan</h3>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Bulan</th><th class="num">ARKAS</th><th class="num">SiapBOS</th><th class="num">Selisih</th></tr></thead>
    <tbody>
      @foreach($hasil['saldo_bulanan'] as $m => $s)
      <tr class="{{ $s['arkas'] !== $s['sipbos'] ? 'bg-amber-50' : '' }}">
        <td>{{ $bulanNama[$m] }}</td>
        <td class="num">{{ rp($s['arkas']) }}</td>
        <td class="num">{{ rp($s['sipbos']) }}</td>
        <td class="num {{ $s['arkas'] !== $s['sipbos'] ? 'font-bold text-amber-700' : '' }}">{{ rp($s['sipbos'] - $s['arkas']) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table></div>
</div>

@if($hasil['selisih_nominal'])
<div class="card">
  <h3 class="card-title mb-2">⚠️ Selisih nominal</h3>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Tanggal</th><th>Uraian ARKAS</th><th class="num">ARKAS</th><th class="num">SiapBOS</th></tr></thead>
    <tbody>@foreach(array_slice($hasil['selisih_nominal'], 0, 30) as $w)<tr>
      <td class="whitespace-nowrap">{{ $w['arkas']['tgl'] }}</td><td>{{ $w['arkas']['uraian'] }}</td>
      <td class="num">{{ rp($w['arkas']['nominal']) }}</td><td class="num font-bold">{{ rp($w['sipbos']['total']) }}</td>
    </tr>@endforeach</tbody>
  </table></div>
</div>
@endif

@if($hasil['hilang_di_sipbos'])
<div class="card">
  <h3 class="card-title mb-2">❌ Ada di ARKAS, hilang di SiapBOS</h3>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Tanggal</th><th>Jenis</th><th>Uraian</th><th class="num">Nominal</th></tr></thead>
    <tbody>@foreach(array_slice($hasil['hilang_di_sipbos'], 0, 30) as $b)<tr>
      <td class="whitespace-nowrap">{{ $b['tgl'] }}</td><td>{{ $b['jenis'] }}</td><td>{{ $b['uraian'] }}</td><td class="num">{{ rp($b['nominal']) }}</td>
    </tr>@endforeach</tbody>
  </table></div>
</div>
@endif

<div class="card">
  <h3 class="card-title mb-2">🔗 Peta BKU SiapBOS ↔ ARKAS ({{ count($hasil['peta_bku']) }} cocok)</h3>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>BKU SiapBOS</th><th>Tanggal</th><th>BKU ARKAS</th><th>Jenis ARKAS</th><th>Uraian</th><th class="num">Nilai</th></tr></thead>
    <tbody>@foreach(array_slice($hasil['peta_bku'], 0, 50) as $p)<tr>
      <td>#{{ $p['no_bku'] }}</td><td class="whitespace-nowrap">{{ $p['tanggal'] }}</td>
      <td class="font-mono text-xs">{{ $p['bku_arkas'] ?? '—' }}</td><td class="text-xs">{{ $p['jenis_arkas'] ?? '—' }}</td>
      <td>{{ \Str::limit($p['uraian'], 45) }}</td><td class="num">{{ rp($p['total']) }}</td>
    </tr>@endforeach</tbody>
  </table></div>
  @if(count($hasil['peta_bku']) > 50)<p class="text-xs text-slate-400 mt-1">…dan {{ count($hasil['peta_bku']) - 50 }} lainnya.</p>@endif
</div>

<div class="flex flex-wrap gap-2">
  <a href="{{ route('rekonsiliasi.form') }}" class="btn2 btn-sm">← Bandingkan file lain</a>
  <a href="{{ route('impor-arkas.form') }}" class="btn2 btn-sm">⬆️ Impor ARKAS</a>
</div>
@endsection
