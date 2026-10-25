@extends('layouts.app', ['title' => 'Arsip'])
@section('subtitle', 'Dokumen yang sudah sah — RKAS disahkan dan BKU yang sudah ditutup — siap dicetak sebagai kelengkapan laporan')

@section('content')
<div class="card border-l-4 border-indigo-600">
  <div class="font-bold text-slate-800 text-sm">🗄️ Arsip Dokumen Sah</div>
  <p class="text-xs text-slate-500 mt-1 max-w-3xl">
    Di halaman ini Anda dapat melihat dan mencetak RKAS yang telah disahkan atau BKU bulanan
    yang sudah ditutup sebagai berkas pelengkap laporan yang sah — sama seperti menu
    <b>Arsip</b> pada ARKAS 4.
  </p>
</div>

{{-- Tiga filter sejajar seperti ARKAS 4: Format × Sumber dana × Tahun --}}
<form method="get" action="{{ route('arsip.index') }}" class="card">
  <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <label class="block">
      <span class="lbl">Format</span>
      <select name="format" class="inp mt-1" data-kirim-otomatis>
        <option value="semua" @selected($format === 'semua')>Semua Format</option>
        <option value="bku" @selected($format === 'bku')>BKU</option>
        <option value="rkas" @selected($format === 'rkas')>RKAS</option>
      </select>
    </label>
    <label class="block">
      <span class="lbl">Sumber dana</span>
      <select name="sumber" class="inp mt-1" data-kirim-otomatis>
        <option value="">Semua sumber dana</option>
        @foreach($daftarSumber as $sd)
          <option value="{{ $sd }}" @selected($sumber === $sd)>{{ $sd }}</option>
        @endforeach
        @if($format !== 'rkas')
          <option value="Semua sumber dana" @selected($sumber === 'Semua sumber dana')>Semua sumber dana (BKU)</option>
        @endif
      </select>
    </label>
    <label class="block">
      <span class="lbl">Tahun</span>
      <select name="tahun" class="inp mt-1" data-kirim-otomatis>
        <option value="semua" @selected($tahun === 'semua')>Semua</option>
        @foreach($daftarTahun as $th)
          <option value="{{ $th }}" @selected((string) $tahun === (string) $th)>{{ $th }}</option>
        @endforeach
      </select>
    </label>
    <div class="flex items-end gap-2">
      <button class="btn2 btn" type="submit">Terapkan</button>
      <a href="{{ route('arsip.index') }}" class="btn2 btn">Reset</a>
    </div>
  </div>
</form>

<div class="card">
  <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
    <h3 class="font-semibold text-slate-700 text-sm">
      📚 Daftar dokumen
      <span class="font-normal text-slate-400">({{ $dokumen->count() }} dokumen)</span>
    </h3>
    <div class="flex flex-wrap gap-2 text-xs">
      <a href="{{ route('arsip.rkas') }}" class="btn2 btn btn-sm">RKAS Disahkan</a>
      <a href="{{ route('arsip.bku') }}" class="btn2 btn btn-sm">BKU Ditutup</a>
      <a href="{{ route('arsip.pajak') }}" class="btn2 btn btn-sm">Dokumen Pajak</a>
    </div>
  </div>

  @forelse($dokumen as $d)
    <div class="rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3 mb-2 flex flex-wrap items-center justify-between gap-3">
      <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
          <span class="text-slate-400" aria-hidden="true">📄</span>
          <span class="text-[14px] font-bold text-slate-800">{{ $d['nama'] }}</span>
          <span class="badge {{ $d['jenis'] === 'rkas' ? 'bg-emerald-100 text-emerald-700' : 'bg-sky-100 text-sky-700' }}">
            {{ $d['status'] }}
          </span>
          <span class="badge bg-slate-100 text-slate-600">{{ $d['sumber'] }}</span>
        </div>
        <div class="mt-1 text-[11.5px] text-slate-500">
          {{ $d['sub'] }}
          @if($d['nilai'] !== null)
            · {{ rp($d['nilai']) }}
          @endif
        </div>
      </div>
      <div class="flex shrink-0 items-center gap-1.5">
        <a href="{{ $d['buka'] }}" class="btn2 btn btn-sm">📂 Buka</a>
        <a href="{{ $d['cetak'] }}" data-cetak="Cetak {{ $d['nama'] }}" class="btn2 btn btn-sm">🖨️ Cetak</a>
      </div>
    </div>
  @empty
    <p class="empty">
      Belum ada dokumen sah untuk filter ini.
      RKAS masuk arsip setelah <b>disahkan Dinas</b>; BKU masuk setelah <b>ditutup</b>.
    </p>
  @endforelse
</div>
@endsection
