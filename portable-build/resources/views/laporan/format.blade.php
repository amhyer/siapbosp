@extends('layouts.app', ['title' => 'Pusat Format Laporan', 'fullScreen' => true])

@section('content')
@php
  $modeDokumen = request('dokumen', 'bku'); // 'rkas' atau 'bku'
  $formatAktif = request('format_arkas', $modeDokumen === 'rkas' ? 'rkas_tahunan' : 'bku');
  $periodeAktif = request('periode_arkas', sprintf('%d-%02d', $tahun, $bulan));
  $urlKembali = $modeDokumen === 'rkas' ? route('rkas.index') : route('penatausahaan.index');
  $labelKembali = $modeDokumen === 'rkas' ? 'Kembali ke Penganggaran' : 'Kembali ke Penatausahaan';

  $daftarFormatRkas = [
    'rkas_tahunan' => [
      'label' => 'Rencana Kegiatan dan Anggaran Sekolah (RKAS) Tahunan',
      'butuh_periode' => false,
      'url' => route('pembukuan.rekapRealisasi', ['periode' => 'tahunan']),
    ],
    'rkk_tahapan' => [
      'label' => 'Rincian Kertas Kerja (Tahapan)',
      'butuh_periode' => 'tahap',
      'url' => route('pembukuan.rekapRealisasi', ['periode' => 'tahap', 'tahap' => $tahap?->id]),
    ],
    'rkk_triwulan' => [
      'label' => 'Rincian Kertas Kerja (Triwulan)',
      'butuh_periode' => false,
      'url' => route('pembukuan.rekapRealisasi', ['periode' => 'semester', 'semester' => $semester]),
    ],
    'rkk_bulanan' => [
      'label' => 'Rincian Kertas Kerja (Bulanan)',
      'butuh_periode' => 'bulan',
      'url' => route('pembukuan.rekapRealisasi', ['periode' => 'bulanan', 'bulan' => sprintf('%d-%02d', $tahun, $bulan)]),
    ],
    'rka_221' => [
      'label' => 'Lembar Kertas Kerja Unit 2.2.1',
      'butuh_periode' => false,
      'url' => route('pembukuan.rekapRealisasi', ['periode' => 'tahunan']),
    ],
  ];

  $daftarFormatBku = [
    'bku' => [
      'label' => 'Buku Kas Umum',
      'butuh_periode' => 'bulan',
      'url' => route('bku.cetak', ['format' => 'bulanan', 'bulan' => sprintf('%d-%02d', $tahun, $bulan)]),
    ],
    'bpk_tunai' => [
      'label' => 'Buku Pembantu Kas',
      'butuh_periode' => 'bulan',
      'url' => route('pembukuan.bukuPembantuTunai', ['periode' => 'bulan', 'bulan' => sprintf('%d-%02d', $tahun, $bulan)]),
    ],
    'bpk_bank' => [
      'label' => 'Buku Pembantu Bank',
      'butuh_periode' => 'bulan',
      'url' => route('pembukuan.bukuPembantuBank', ['periode' => 'bulan', 'bulan' => sprintf('%d-%02d', $tahun, $bulan)]),
    ],
    'bpk_pajak' => [
      'label' => 'Buku Pembantu Pajak',
      'butuh_periode' => 'bulan',
      'url' => route('pembukuan.bukuPembantuPajak', ['periode' => 'bulan', 'bulan' => sprintf('%d-%02d', $tahun, $bulan)]),
    ],
    'bapk' => [
      'label' => 'Berita Acara Pemeriksaan Kas',
      'butuh_periode' => 'bulan',
      'url' => route('bku.cetak', ['format' => 'bapk', 'bulan' => sprintf('%d-%02d', $tahun, $bulan)]),
    ],
    'register_kas' => [
      'label' => 'Register Penutupan Kas',
      'butuh_periode' => 'bulan',
      'url' => route('bku.cetak', ['format' => 'register-kas', 'bulan' => sprintf('%d-%02d', $tahun, $bulan)]),
    ],
    'sptjm' => [
      'label' => 'SPTJM',
      'butuh_periode' => 'tahap',
      'url' => route('pembukuan.sptjm', ['periode' => 'semester', 'semester' => $semester]),
    ],
    'realisasi_dana' => [
      'label' => 'Laporan Realisasi Penggunaan Dana',
      'butuh_periode' => 'tahap',
      'url' => route('pembukuan.rekapRealisasi', ['periode' => 'semester', 'semester' => $semester]),
    ],
    'rekap_realisasi' => [
      'label' => 'Rekapitulasi Realisasi Penggunaan Dana',
      'butuh_periode' => 'tahap',
      'url' => route('pembukuan.rekapRealisasi', ['periode' => 'tahunan']),
    ],
    'rekap_komponen' => [
      'label' => 'Laporan Penggunaan Dana tiap Komponen',
      'butuh_periode' => 'tahap',
      'url' => route('pembukuan.rekapRealisasi', ['periode' => 'semester', 'semester' => $semester]),
    ],
    'rincian_kegiatan' => [
      'label' => 'Rincian Penggunaan Dana per Kegiatan',
      'butuh_periode' => 'bulan',
      'url' => route('pembukuan.rekapRealisasi', ['periode' => 'bulanan', 'bulan' => sprintf('%d-%02d', $tahun, $bulan)]),
    ],
  ];

  $katalogAktif = $modeDokumen === 'rkas' ? $daftarFormatRkas : $daftarFormatBku;
  $itemAktif = $katalogAktif[$formatAktif] ?? reset($katalogAktif);
  $previewUrl = $itemAktif['url'];
@endphp

<div class="rounded border border-[#e3e5e8] bg-white p-6 shadow-sm">
  {{-- Navigasi Kembali Full-Screen ala ARKAS 4 --}}
  <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
    <a href="{{ $urlKembali }}" class="inline-flex items-center gap-2 text-[13px] font-semibold text-[#0b5fef] hover:underline">
      <span>←</span> {{ $labelKembali }}
    </a>
    <div class="flex items-center gap-1.5 text-[12px]">
      <a href="{{ route('laporan.format', ['dokumen' => 'rkas', 'bulan' => $bulan, 'semester' => $semester]) }}"
         class="rounded px-2.5 py-1 font-medium {{ $modeDokumen === 'rkas' ? 'bg-[#0b5fef] text-white' : 'bg-[#f4f5f7] text-[#65696c] hover:text-[#2f3031]' }}">
        Mode Mencetak RKAS
      </a>
      <a href="{{ route('laporan.format', ['dokumen' => 'bku', 'bulan' => $bulan, 'semester' => $semester]) }}"
         class="rounded px-2.5 py-1 font-medium {{ $modeDokumen !== 'rkas' ? 'bg-[#0b5fef] text-white' : 'bg-[#f4f5f7] text-[#65696c] hover:text-[#2f3031]' }}">
        Mode Mencetak BKU
      </a>
    </div>
  </div>

  {{-- Judul Halaman Mencetak RKAS / BKU --}}
  <div class="mb-4">
    <h2 class="text-[20px] font-bold text-[#2f3031]">
      {{ $modeDokumen === 'rkas' ? 'Mencetak RKAS '.$tahun : 'Mencetak BKU '.$tahun }}
    </h2>
    <div class="mt-0.5 text-[12px] font-semibold uppercase tracking-wide text-[#65696c]">
      BOSP REGULER {{ $tahun }}
    </div>
  </div>

  {{-- Baris Dropdown Format & Periode (Screenshot 235346 - 235624) --}}
  <form method="get" action="{{ route('laporan.format') }}" class="mb-5 grid gap-4 sm:grid-cols-2 lg:max-w-3xl">
    <input type="hidden" name="dokumen" value="{{ $modeDokumen }}">
    <label class="block">
      <span class="mb-1 block text-[12px] font-semibold text-[#2f3031]">
        {{ $modeDokumen === 'rkas' ? 'Format RKAS' : 'Format Laporan' }}
      </span>
      <select name="format_arkas" class="inp text-[13px]" data-kirim-otomatis>
        @foreach($katalogAktif as $kodeFormat => $metaFormat)
          <option value="{{ $kodeFormat }}" @selected($formatAktif === $kodeFormat)>{{ $metaFormat['label'] }}</option>
        @endforeach
      </select>
    </label>

    @if($itemAktif['butuh_periode'] === 'bulan')
      <label class="block">
        <span class="mb-1 block text-[12px] font-semibold text-[#2f3031]">Periode</span>
        <select name="bulan" class="inp text-[13px]" data-kirim-otomatis>
          @for ($n = 1; $n <= 12; $n++)
            <option value="{{ $n }}" @selected($n === $bulan)>{{ \Carbon\Carbon::create($tahun, $n, 1)->translatedFormat('F') }}</option>
          @endfor
        </select>
      </label>
    @elseif($itemAktif['butuh_periode'] === 'tahap')
      <label class="block">
        <span class="mb-1 block text-[12px] font-semibold text-[#2f3031]">Periode</span>
        <select name="semester" class="inp text-[13px]" data-kirim-otomatis>
          <option value="1" @selected($semester === 1)>Tahap 1</option>
          <option value="2" @selected($semester === 2)>Tahap 2</option>
          <option value="1" @selected($semester === 0)>Tahunan</option>
        </select>
      </label>
    @endif
  </form>

  {{-- Embedded PDF Viewer Gelap ala Chromium/ARKAS 4 (#323639 & #525659) --}}
  <div class="overflow-hidden rounded border border-[#2b2e31] bg-[#525659] shadow-inner">
    {{-- Toolbar Atas PDF Viewer (#323639) --}}
    <div class="flex flex-wrap items-center justify-between gap-2 bg-[#323639] px-4 py-2.5 text-white">
      <div class="flex items-center gap-3 min-w-0">
        <span class="inline-flex h-7 w-7 items-center justify-center rounded hover:bg-white/10">☰</span>
        <span class="truncate text-[13px] font-medium text-gray-100">{{ $itemAktif['label'] }} — BOSP Reguler {{ $tahun }}</span>
      </div>

      {{-- Kontrol Halaman & Zoom di Tengah --}}
      <div class="flex items-center gap-2 text-[12px]">
        <span class="rounded bg-[#202224] px-2 py-0.5 font-mono text-gray-200">1</span>
        <span class="text-gray-400">/ 1</span>
        <span class="mx-1 text-gray-500">|</span>
        <span class="inline-flex h-6 w-6 items-center justify-center rounded hover:bg-white/10">−</span>
        <span class="rounded bg-[#202224] px-2.5 py-0.5 font-mono text-gray-200">83%</span>
        <span class="inline-flex h-6 w-6 items-center justify-center rounded hover:bg-white/10">+</span>
        <span class="mx-1 text-gray-500">|</span>
        <span class="inline-flex h-6 w-6 items-center justify-center rounded hover:bg-white/10" title="Sesuaikan Lebar">⊡</span>
        <span class="inline-flex h-6 w-6 items-center justify-center rounded hover:bg-white/10" title="Putar Halaman">↻</span>
      </div>

      {{-- Tombol Unduh & Cetak di Kanan Atas --}}
      <div class="flex items-center gap-2">
        <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded bg-white/10 px-3 py-1 text-[12px] font-medium text-white hover:bg-white/20" title="Buka / Unduh Dokumen">
          ⬇ Unduh
        </a>
        <a href="{{ $previewUrl }}" data-cetak="{{ $itemAktif['label'] }}" class="inline-flex items-center gap-1.5 rounded bg-[#0b5fef] px-3 py-1 text-[12px] font-semibold text-white hover:bg-[#094ec7]" title="Cetak Dokumen">
          <x-icon name="print" class="h-3.5 w-3.5"/> Cetak
        </a>
      </div>
    </div>

    {{-- Badan PDF Viewer: Sidebar Thumbnail Kiri + Kanvas Kertas Putih Kanan --}}
    <div class="grid min-h-[640px] grid-cols-1 lg:grid-cols-[200px_1fr]">
      {{-- Sidebar Thumbnail Kiri --}}
      <div class="hidden border-r border-[#3e4245] bg-[#3b3f42] p-4 lg:flex lg:flex-col lg:items-center">
        <div class="w-28 rounded border-2 border-[#8ab4f8] bg-white p-2 shadow-md">
          <div class="mb-1.5 h-2 w-16 bg-slate-300"></div>
          <div class="mb-1 h-1.5 w-full bg-slate-200"></div>
          <div class="mb-2 h-1.5 w-20 bg-slate-200"></div>
          <div class="space-y-1 border-t border-slate-200 pt-1.5">
            <div class="h-1.5 w-full bg-slate-100"></div>
            <div class="h-1.5 w-full bg-slate-100"></div>
            <div class="h-1.5 w-full bg-slate-100"></div>
            <div class="h-1.5 w-3/4 bg-slate-100"></div>
          </div>
          <div class="mt-6 flex justify-between pt-2">
            <div class="h-2 w-8 bg-slate-200"></div>
            <div class="h-2 w-8 bg-slate-200"></div>
          </div>
        </div>
        <span class="mt-2 text-[11px] font-medium text-gray-300">1</span>
      </div>

      {{-- Kanvas Kertas Pratinjau Cetak --}}
      <div class="bg-[#525659] p-4 sm:p-6">
        <div class="mx-auto max-w-[980px] overflow-hidden rounded bg-white shadow-2xl">
          <iframe src="{{ $previewUrl }}" title="Pratinjau Cetak {{ $itemAktif['label'] }}" class="h-[600px] w-full border-0 bg-white"></iframe>
        </div>
      </div>
    </div>
  </div>

  {{-- Katalog Lengkap Pusat Format Laporan untuk akses cepat & kompatibilitas pengujian --}}
  <details class="mt-6 rounded border border-[#d9dbdd] bg-[#fafafa] p-4">
    <summary class="cursor-pointer text-[13px] font-bold text-[#2f3031]">
      <span class="inline-flex items-center gap-2">
        <x-icon name="folder" class="h-4 w-4 text-[#0b5fef]"/> Pusat Format Laporan — Katalog Lengkap Semua Periode
      </span>
    </summary>

    <div class="mt-4 space-y-4">
      <form method="get" action="{{ route('laporan.format') }}" class="card">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <label class="block">
            <span class="lbl">Bulan</span>
            <select name="bulan" class="inp mt-1" data-kirim-otomatis>
              @for ($n = 1; $n <= 12; $n++)
                <option value="{{ $n }}" @selected($n === $bulan)>{{ \Carbon\Carbon::create($tahun, $n, 1)->translatedFormat('F') }} {{ $tahun }}</option>
              @endfor
            </select>
          </label>
          <label class="block">
            <span class="lbl">Semester</span>
            <select name="semester" class="inp mt-1" data-kirim-otomatis>
              <option value="1" @selected($semester === 1)>Semester 1 (Januari–Juni)</option>
              <option value="2" @selected($semester === 2)>Semester 2 (Juli–Desember)</option>
            </select>
          </label>
          <label class="block">
            <span class="lbl">Tahap</span>
            <select name="tahap" class="inp mt-1" data-kirim-otomatis @disabled($stages->isEmpty())>
              @forelse ($stages as $s)
                <option value="{{ $s->id }}" @selected($tahap && $s->id === $tahap->id)>{{ $s->nama }}</option>
              @empty
                <option value="">Belum ada tahap</option>
              @endforelse
            </select>
          </label>
          <div class="flex items-end gap-2">
            <button type="submit" class="btn2">Terapkan Periode</button>
            <a href="{{ route('laporan.format') }}" class="btn2">Reset</a>
          </div>
        </div>
        <div class="mt-2 text-[11px] text-slate-500">
          Periode aktif:
          <b>{{ $namaBulan }} {{ $tahun }}</b> ·
          <b>{{ $namaSemester }}</b> ·
          <b>{{ $tahap->nama ?? 'Belum ada tahap' }}</b>
        </div>
      </form>

      <div class="card">
        <div class="mb-2 flex items-center gap-2 text-sm font-bold text-[#2f3031]">
          <x-icon name="sparkles" class="h-4 w-4 text-[#0b5fef]"/> Pintasan Cepat
        </div>
        <div class="flex flex-wrap gap-2 text-xs">
          <a class="btn2" href="{{ route('bku.cetak', ['format' => 'bulanan', 'bulan' => sprintf('%d-%02d', $tahun, $bulan)]) }}" data-cetak="BKU Bulanan"><x-icon name="print" class="h-3.5 w-3.5"/> BKU {{ $namaBulan }}</a>
          <a class="btn2" href="{{ route('pembukuan.bukuPembantuBank', ['periode' => 'bulan', 'bulan' => sprintf('%d-%02d', $tahun, $bulan)]) }}" data-cetak="Buku Pembantu Bank"><x-icon name="print" class="h-3.5 w-3.5"/> Bank {{ $namaBulan }}</a>
          <a class="btn2" href="{{ route('pembukuan.bukuPembantuTunai', ['periode' => 'bulan', 'bulan' => sprintf('%d-%02d', $tahun, $bulan)]) }}" data-cetak="Buku Pembantu Tunai"><x-icon name="print" class="h-3.5 w-3.5"/> Tunai {{ $namaBulan }}</a>
          <a class="btn2" href="{{ route('pembukuan.bukuPembantuPajak', ['periode' => 'bulan', 'bulan' => sprintf('%d-%02d', $tahun, $bulan)]) }}" data-cetak="Buku Pembantu Pajak"><x-icon name="print" class="h-3.5 w-3.5"/> Pajak {{ $namaBulan }}</a>
          <a class="btn2" href="{{ route('pembukuan.rekapRealisasi', ['periode' => 'bulanan', 'bulan' => sprintf('%d-%02d', $tahun, $bulan)]) }}" data-cetak="Rekap Realisasi"><x-icon name="print" class="h-3.5 w-3.5"/> Rekap {{ $namaBulan }}</a>
          <a class="btn2" href="{{ route('pembukuan.sptjm', ['periode' => 'semester', 'semester' => $semester]) }}" data-cetak="SPTJM"><x-icon name="print" class="h-3.5 w-3.5"/> SPTJM Semester {{ $semester }}</a>
        </div>
      </div>

      @foreach ($grup as $bagian)
      <div class="card">
        <div class="flex items-start justify-between gap-2">
          <div>
            <div class="flex items-center gap-2 text-sm font-bold text-[#2f3031]"><x-icon name="{{ $bagian['ikon'] }}" class="h-4 w-4 text-slate-500"/> {{ $bagian['label'] }}</div>
            <div class="text-[11px] text-slate-500 mt-0.5">{{ $bagian['desc'] }}</div>
          </div>
          <span class="badge bg-slate-100 text-slate-600">{{ count($bagian['items']) }} format</span>
        </div>

        <div class="mt-3 grid gap-2 lg:grid-cols-2">
          @foreach ($bagian['items'] as $item)
          <div class="rounded border border-[#d9dbdd] bg-[#fafafa] p-3">
            <div class="text-[12.5px] font-semibold text-slate-800">{{ $item['nama'] }}</div>
            <div class="text-[11px] text-slate-500 mt-0.5">{{ $item['desc'] }}</div>
            <div class="mt-2 flex flex-wrap gap-1.5">
              @foreach ($item['link'] as $tautan)
                <a href="{{ $tautan['url'] }}" class="btn2" data-cetak="{{ $item['nama'] }} — {{ $tautan['label'] }}"><x-icon name="print" class="h-3.5 w-3.5"/> {{ $tautan['label'] }}</a>
              @endforeach
            </div>
          </div>
          @endforeach
        </div>
      </div>
      @endforeach
    </div>
  </details>
</div>
@endsection
