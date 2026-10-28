@extends('layouts.app', ['title' => 'Penatausahaan'])
@section('content')
{{-- Halaman perantara Penatausahaan (pola ARKAS 4): sidebar cukup satu tautan,
     navigasi detail ada di sini — status BKU 12 bulan + jalan masuk tiap fitur. --}}

<div class="card">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div>
      <h2 class="card-title !mb-1">Penatausahaan {{ $tahun }}</h2>
      <p class="text-[13px] text-slate-500">
        Pencatatan realisasi: BKU bulanan, pajak, dan buku pembantu.
        {{ $jumlahTutup }} dari 12 bulan sudah ditutup.
      </p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="{{ route('bku.cetak') }}" data-cetak="BKU {{ $tahun }}" class="btn2 btn text-sm">🖨️ Cetak BKU</a>
      <a href="{{ route('pendapatan.index') }}" class="btn text-sm">+ Tambah Penerimaan</a>
      <a href="{{ route('belanja.create') }}" class="btn2 btn text-sm">+ Tambah Belanja</a>
    </div>
  </div>
</div>

<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
  <div class="card">
    <div class="text-xs text-slate-500">Saldo Kas Tunai</div>
    <div class="text-xl font-bold text-emerald-700">{{ rp($saldo['tunai']) }}</div>
  </div>
  <div class="card">
    <div class="text-xs text-slate-500">Saldo Kas Bank</div>
    <div class="text-xl font-bold text-sky-700">{{ rp($saldo['bank']) }}</div>
  </div>
  <div class="card">
    <div class="text-xs text-slate-500">Penerimaan {{ $tahun }}</div>
    <div class="text-xl font-bold text-slate-800">{{ rp($pendapatan) }}</div>
  </div>
  <div class="card">
    <div class="text-xs text-slate-500">Belanja {{ $tahun }}</div>
    <div class="text-xl font-bold text-slate-800">{{ rp($belanja) }}</div>
  </div>
</div>

<div class="card">
  <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
    <h3 class="card-title !mb-0">📅 Status BKU {{ $tahun }}</h3>
    <p class="text-[12px] text-slate-500">Klik bulan untuk membuka BKU-nya.</p>
  </div>

  <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
    @foreach($bulan as $b)
      @php
        $gaya = match ($b['status']) {
            'tertutup', 'selesai' => 'border-emerald-200 bg-emerald-50 hover:bg-emerald-100',
            'belum-pajak' => 'border-amber-200 bg-amber-50 hover:bg-amber-100',
            'draf' => 'border-sky-200 bg-sky-50 hover:bg-sky-100',
            default => 'border-slate-200 bg-white hover:bg-slate-50',
        };
        $warna = match ($b['status']) {
            'tertutup', 'selesai' => 'bg-emerald-100 text-emerald-700',
            'belum-pajak' => 'bg-amber-100 text-amber-700',
            'draf' => 'bg-sky-100 text-sky-700',
            default => 'bg-slate-100 text-slate-500',
        };
      @endphp
      <a href="{{ route('bku', ['bulan' => $b['ym']]) }}"
         class="block rounded-lg border px-3 py-2 no-underline transition {{ $gaya }}
                {{ $b['now'] ? 'ring-2 ring-indigo-400' : '' }}">
        <div class="flex items-center justify-between gap-2">
          <span class="text-[13px] font-semibold text-slate-700">{{ $b['nama'] }}</span>
          <span class="text-[11px] {{ in_array($b['status'], ['tertutup', 'selesai'], true) ? 'text-emerald-700' : 'text-slate-400' }}">{{ $b['tanda'] }}</span>
        </div>
        <div class="mt-1 flex flex-wrap items-center gap-1.5">
          <span class="badge {{ $warna }}">{{ $b['label'] }}</span>
          @if($b['n'] > 0)
            <span class="text-[11px] text-slate-500">{{ $b['n'] }} transaksi</span>
          @endif
        </div>
      </a>
    @endforeach
  </div>

  @unless($adaTransaksi)
    <p class="empty mt-3">Belum ada transaksi pada tahun {{ $tahun }}. Mulai dari <b>Tambah Penerimaan</b> di atas.</p>
  @endunless
</div>

<div class="card overflow-x-auto">
  <h3 class="card-title mb-3">💰 Pagu & Realisasi per Sumber Dana</h3>
  @if($sumberDana->isEmpty())
    <p class="empty">Belum ada pagu maupun transaksi per sumber dana pada tahun {{ $tahun }}.</p>
  @else
    <table class="tbl">
      <thead>
        <tr>
          <th>Sumber dana</th>
          <th class="num">Pagu</th>
          <th class="num">Penerimaan</th>
          <th class="num">Belanja</th>
          <th class="num">Sisa pagu</th>
        </tr>
      </thead>
      <tbody>
        @foreach($sumberDana as $s)
          <tr>
            <td>{{ $s['nama'] }}</td>
            <td class="num">{{ rp($s['pagu']) }}</td>
            <td class="num">{{ rp($s['penerimaan']) }}</td>
            <td class="num">{{ rp($s['belanja']) }}</td>
            <td class="num font-semibold {{ $s['sisa'] < 0 ? 'text-rose-700' : 'text-slate-700' }}">{{ rp($s['sisa']) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>

<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
  @foreach($menu as $m)
    <a href="{{ $m['href'] }}" class="card no-underline transition hover:border-indigo-300 hover:bg-indigo-50/40">
      <div class="flex items-start justify-between gap-2">
        <div>
          <div class="text-[14px] font-semibold text-slate-800">{{ $m['label'] }}</div>
          <div class="text-[12px] text-slate-500">{{ $m['ket'] }}</div>
        </div>
        <span class="text-slate-300" aria-hidden="true">›</span>
      </div>
    </a>
  @endforeach
</div>
@endsection
