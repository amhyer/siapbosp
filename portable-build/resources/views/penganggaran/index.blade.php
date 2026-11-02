@extends('layouts.app', ['title' => 'Penganggaran'])
@section('content')
{{-- Halaman perantara Penganggaran (pola ARKAS 4): sidebar cukup satu tautan,
     navigasi detail ada di sini — ringkasan pagu, status dokumen, dan jalan masuk fitur. --}}

<div class="card">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div>
      <h2 class="card-title !mb-1">Penganggaran {{ $tahun }}</h2>
      <p class="text-[13px] text-slate-500">
        Kertas Kerja RKAS, review proporsi, dan format cetak ARKAS.
        {{ $jumlahDokumen }} sumber dana · {{ $jumlahDisahkan }} disahkan · {{ $jumlahDraf }} draf.
      </p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="{{ route('rkas.index') }}" class="btn text-sm">Buka Kertas Kerja</a>
      <a href="{{ route('laporan.format') }}" class="btn2 btn text-sm">🖨️ Pusat Format Laporan</a>
    </div>
  </div>
</div>

<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
  <div class="card">
    <div class="text-xs text-slate-500">Total Pagu RKAS</div>
    <div class="text-xl font-bold text-slate-800">{{ rp($totalPagu) }}</div>
  </div>
  <div class="card">
    <div class="text-xs text-slate-500">Alokasi Diterima</div>
    <div class="text-xl font-bold text-emerald-700">{{ rp($alokasi) }}</div>
  </div>
  <div class="card">
    <div class="text-xs text-slate-500">Dokumen Sumber Dana</div>
    <div class="text-xl font-bold text-slate-800">{{ $jumlahDokumen }}</div>
  </div>
  <div class="card">
    <div class="text-xs text-slate-500">Tahap Penyaluran</div>
    <div class="text-xl font-bold text-slate-800">{{ $stages->count() }}</div>
  </div>
</div>

<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
  @foreach($menu as $m)
    <a href="{{ $m['href'] }}" class="card block no-underline transition hover:border-[#0b5fef] hover:shadow-sm">
      <div class="flex items-start gap-3">
        <span class="text-xl" aria-hidden="true">{{ $m['ikon'] }}</span>
        <span class="min-w-0">
          <span class="block text-[13.5px] font-bold text-[#2f3031]">{{ $m['label'] }}</span>
          <span class="mt-0.5 block text-[12px] text-[#65696c]">{{ $m['ket'] }}</span>
        </span>
      </div>
    </a>
  @endforeach
</div>

<div class="card overflow-x-auto">
  <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
    <h3 class="card-title !mb-0">📄 Dokumen RKAS per Sumber Dana</h3>
    <p class="text-[12px] text-slate-500">Revisi terbaru pada tahun {{ $tahun }}.</p>
  </div>

  <div class="tbl-wrap">
    <table class="tbl text-[12.5px]">
      <thead>
        <tr>
          <th>Sumber Dana</th>
          <th class="num">Revisi</th>
          <th>Status</th>
          <th class="num">Total Pagu</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($perSumber as $baris)
          @php $h = $baris['header']; @endphp
          <tr>
            <td class="font-semibold text-slate-700">{{ $h->sumber_dana ?: 'BOSP Reguler' }}</td>
            <td class="num">{{ $baris['revisi'] > 1 ? 'ke-'.($baris['revisi'] - 1) : 'awal' }}</td>
            <td>
              <span class="badge">{{ $h->labelStatusArkas() }}</span>
            </td>
            <td class="num font-semibold">{{ rp($baris['total']) }}</td>
            <td class="whitespace-nowrap">
              <a href="{{ route('rkas.show', $h) }}" class="text-[#0b5fef] hover:underline">Buka</a>
              <span class="text-slate-300">·</span>
              <a href="{{ route('rkas.pdf', $h) }}" class="text-[#0b5fef] hover:underline">PDF</a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="py-6 text-center text-slate-500">
              Belum ada dokumen RKAS untuk tahun {{ $tahun }}.
              <a href="{{ route('rkas.create') }}" class="font-semibold text-[#0b5fef] hover:underline">Buat Kertas Kerja</a>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@if($stages->isNotEmpty())
  <div class="card">
    <h3 class="card-title">Tahap Penyaluran {{ $tahun }}</h3>
    <div class="flex flex-wrap gap-2">
      @foreach($stages as $s)
        <span class="badge bg-sky-50 text-sky-700">{{ $s->nama }}</span>
      @endforeach
    </div>
  </div>
@endif
@endsection
