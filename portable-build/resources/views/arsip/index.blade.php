@extends('layouts.app', ['title' => 'Arsip Dokumen Sah', 'hidePageHead' => true])

@section('content')
<div class="rounded border border-[#e3e5e8] bg-white p-6 shadow-sm">
  {{-- Baris Filter 3 Kolom di kanan atas kartu putih seperti ARKAS 4 (Screenshot 235301) --}}
  <form method="get" action="{{ route('arsip.index') }}" class="mb-6 flex flex-wrap items-center justify-end gap-5">
    <label class="flex items-center gap-2.5 text-[12.5px] text-[#65696c]">
      <span>Format</span>
      <select name="format" class="inp w-44 py-1.5 text-[12.5px]" data-kirim-otomatis>
        <option value="semua" @selected($format === 'semua')>Semua Format</option>
        <option value="bku" @selected($format === 'bku')>BKU</option>
        <option value="rkas" @selected($format === 'rkas')>RKAS</option>
      </select>
    </label>

    <label class="flex items-center gap-2.5 text-[12.5px] text-[#65696c]">
      <span>Sumber dana</span>
      <select name="sumber" class="inp w-48 py-1.5 text-[12.5px]" data-kirim-otomatis>
        <option value="">Semua sumber dana</option>
        @foreach($daftarSumber as $sd)
          <option value="{{ $sd }}" @selected($sumber === $sd)>{{ $sd }}</option>
        @endforeach
        @if($format !== 'rkas')
          <option value="Semua sumber dana" @selected($sumber === 'Semua sumber dana')>Semua sumber dana (BKU)</option>
        @endif
      </select>
    </label>

    <label class="flex items-center gap-2.5 text-[12.5px] text-[#65696c]">
      <span>Tahun</span>
      <select name="tahun" class="inp w-32 py-1.5 text-[12.5px]" data-kirim-otomatis>
        <option value="semua" @selected($tahun === 'semua')>Semua</option>
        @foreach($daftarTahun as $th)
          <option value="{{ $th }}" @selected((string) $tahun === (string) $th)>{{ $th }}</option>
        @endforeach
      </select>
    </label>

    <button class="sr-only" type="submit">Terapkan</button>
  </form>

  {{-- Daftar Kartu Dokumen Abu Muda (#f5f5f5) seperti ARKAS 4 --}}
  <div class="space-y-3">
    @forelse($dokumen as $d)
      <div class="flex flex-wrap items-center justify-between gap-3 rounded border border-[#e5e7eb] bg-[#f5f5f5] px-5 py-4">
        <div class="flex min-w-0 items-center gap-3.5">
          <div class="flex h-8 w-8 shrink-0 items-center justify-center text-[#65696c]">
            <x-icon name="file" class="h-5 w-5"/>
          </div>
          <div class="min-w-0">
            <div class="text-[14px] font-bold text-[#2f3031]">{{ $d['nama'] }}</div>
            <div class="sr-only">
              {{ $d['status'] }} · {{ $d['sumber'] }} · {{ $d['sub'] }}
              @if($d['nilai'] !== null)
                · {{ rp($d['nilai']) }}
              @endif
            </div>
          </div>
        </div>
        <div class="flex shrink-0 items-center gap-2">
          <a href="{{ $d['buka'] }}" class="sr-only">Buka</a>
          <a href="{{ $d['cetak'] }}" data-cetak="Cetak {{ $d['nama'] }}" class="inline-flex items-center gap-2 rounded border border-[#65696c] bg-white px-4 py-1.5 text-[12.5px] font-semibold text-[#2f3031] hover:bg-[#f9fafb]">
            <x-icon name="print" class="h-4 w-4 text-[#2f3031]"/> Cetak
          </a>
        </div>
      </div>
    @empty
      <p class="empty">
        Belum ada dokumen sah untuk filter ini.
        RKAS masuk arsip setelah <b>disahkan Dinas</b>; BKU masuk setelah <b>ditutup</b>.
      </p>
    @endforelse
  </div>

  {{-- Tautan cepat sub-arsip di bagian bawah --}}
  <details class="mt-4 rounded border border-[#e5e7eb] bg-[#fafafa] px-4 py-2 text-[12px] text-[#65696c]">
    <summary class="cursor-pointer font-medium">Filter Cepat Sub-Arsip ({{ $dokumen->count() }} dokumen)</summary>
    <div class="mt-2 flex flex-wrap gap-2 py-1">
      <a href="{{ route('arsip.rkas') }}" class="btn2 btn-sm">RKAS Disahkan</a>
      <a href="{{ route('arsip.bku') }}" class="btn2 btn-sm">BKU Ditutup</a>
      <a href="{{ route('arsip.pajak') }}" class="btn2 btn-sm">Dokumen Pajak</a>
    </div>
  </details>
</div>
@endsection
