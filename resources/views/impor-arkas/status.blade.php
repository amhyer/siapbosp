@extends('layouts.app', ['title' => 'Status pekerjaan ARKAS'])
@section('subtitle', 'Status pemeriksaan atau impor Excel ARKAS')
@if(in_array($impor->status, ['queued', 'processing'], true))
  @push('head')<meta http-equiv="refresh" content="5">@endpush
@endif
@section('content')
@php
  $labelJenis = match ($impor->jenis) {
    'integritas' => 'Cek integritas',
    'rekonsiliasi' => 'Rekonsiliasi',
    'rkas-csv' => 'Impor CSV RKAS',
    default => 'Impor ARKAS',
  };
@endphp
<div class="card max-w-3xl space-y-3">
  <h2 class="card-title">{{ $labelJenis }} · tahun {{ $impor->tahun ?? '—' }}</h2>
  @if($impor->sumber)
    <p class="text-sm text-slate-600">Sumber: {{ $impor->sumber }}</p>
  @endif
  <p>Status:
    @if($impor->status === 'queued') <span class="badge bg-amber-100 text-amber-800">Menunggu antrean</span>
    @elseif($impor->status === 'processing') <span class="badge bg-indigo-100 text-indigo-800">Sedang diproses</span>
    @elseif($impor->status === 'completed') <span class="badge bg-emerald-100 text-emerald-800">Selesai</span>
    @else <span class="badge bg-rose-100 text-rose-800">Gagal</span>@endif
  </p>
  @if($impor->error)
    <p class="text-rose-700">{{ $impor->error }}</p>
  @endif
  @if($impor->result)
    <p>Ringkasan hasil pekerjaan:</p>
    <pre class="max-h-[32rem] overflow-auto whitespace-pre-wrap rounded-lg bg-slate-50 p-3 text-xs">{{ json_encode($impor->result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) }}</pre>
    @if($impor->jenis === 'rkas-csv' && $impor->status === 'completed' && !empty($impor->result['rkas_header_id']))
      <a href="{{ route('rkas.show', $impor->result['rkas_header_id']) }}" class="btn2">Buka RKAS revisi {{ $impor->result['revisi_ke'] ?? '' }}</a>
    @endif
  @endif
  @if(in_array($impor->status, ['queued', 'processing'], true))
    <p class="page-desc">Halaman diperbarui otomatis setiap 5 detik. Jangan unggah ulang berkas selama pekerjaan berjalan. Jika status tidak berubah selama 24 jam, hubungi administrator.</p>
  @endif
  <div class="flex gap-2">
    <a href="{{ route('impor-arkas.status', $impor) }}" class="btn2">↻ Perbarui status</a>
    <a href="{{ route('impor-arkas.form') }}" class="btn2">Kembali ke Impor ARKAS</a>
  </div>
</div>
@endsection
