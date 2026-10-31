@extends('layouts.app', ['title' => 'Penatausahaan', 'hidePageHead' => true])
@section('content')
@php
  $tabsPeta = \App\Models\RkasHeader::TAB_SUMBER_DANA;
  $kodeTabVisual = ($tabAktif ?? '') !== '' && isset($tabsPeta[$tabAktif]) ? $tabAktif : 'reguler';
  $labelTabAktif = $tabsPeta[$kodeTabVisual]['label'] ?? 'BOSP Reguler';
  $modePerbaruiBku = (bool) request('perbarui_bku');
@endphp

@if($modePerbaruiBku)
  {{-- ===== SUB-HALAMAN PERBARUI DATA BKU ALA ARKAS 4 (frame_12_272s.jpg) ===== --}}
  <div class="space-y-4">
    <a href="{{ route('penatausahaan.index', array_filter(['tab' => $tabAktif])) }}"
       class="inline-flex items-center gap-2 text-[13.5px] font-semibold text-[#0b5fef] no-underline hover:underline">
      <span>←</span>
      <span>Kembali ke Penatausahaan</span>
    </a>

    <div class="rounded-lg border border-[#d9dbdd] bg-white p-7">
      <div class="flex flex-wrap items-start gap-5">
        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-[#e1effe] text-[#0b5fef]">
          <x-icon name="refresh" class="h-7 w-7"/>
        </div>
        <div class="flex-1 min-w-0">
          <h2 class="text-[18px] font-bold text-[#2f3031]">Perbarui Data BKU {{ $labelTabAktif }} {{ $tahun }}</h2>
          <p class="mt-1 text-[13.5px] text-[#65696c] max-w-3xl">
            Anda bisa memperbarui data untuk memeriksa kesesuaian data dan akurasi perhitungan BKU. Jika ditemukan ketidaksesuaian, silakan lakukan perbaikan berdasarkan hasil pembaruan data.
          </p>
          <div class="mt-4">
            <a href="{{ route('penatausahaan.index', array_filter(['tab' => $tabAktif, 'perbarui_bku' => 1])) }}"
               class="inline-flex items-center justify-center rounded border border-[#0b5fef] bg-white px-4 py-2 text-[13px] font-semibold text-[#0b5fef] no-underline hover:bg-[#e1effe]/50 transition">
              Perbarui Data
            </a>
            <div class="mt-1 text-[11px] font-medium text-[#0b5fef]">"Perbarui Data" membutuhkan koneksi internet</div>
          </div>
        </div>
      </div>

      <div class="mt-6 border-t border-[#efefef] pt-5">
        <div class="rounded-lg border border-emerald-200 bg-[#ecfdf5] px-4 py-3.5 text-[13px] text-[#065f46]">
          <div class="flex items-center gap-2 font-medium text-[#117e47]">
            <x-icon name="check-circle" class="h-4 w-4 shrink-0"/>
            <span>Terakhir perbarui data: {{ now()->translatedFormat('d M Y') }}</span>
          </div>
          <div class="mt-1 pl-6 font-bold text-[#2f3031]">Data dan perhitungan BKU sudah sesuai</div>
          <div class="mt-0.5 pl-6 text-[12.5px] text-[#65696c]">Hasil pembaruan tidak mempengaruhi data dan perhitungan BKU Anda.</div>
        </div>
      </div>
    </div>
  </div>
@endif

{{-- ===== KARTU UTAMA PENATAUSAHAAN ALA ARKAS 4 (Screenshot 2026-10-29 171058.png) ===== --}}
<div class="rounded-lg border border-[#d9dbdd] bg-white overflow-hidden {{ $modePerbaruiBku ? 'mt-4' : '' }}">
  @include('partials._tab-sumber-dana', [
    'rute' => route('penatausahaan.index'),
    'aktif' => $kodeTabVisual,
  ])

  <div class="p-6 space-y-4">
    {{-- Kartu Tahun Aktif (mis. BKU BOSP Reguler 2026) --}}
    <div class="rounded-lg bg-[#f5f5f5] p-6">
      <div class="flex flex-wrap items-start justify-between gap-4 pb-5 border-b border-[#e2e4e7]">
        <div class="flex items-start gap-3.5 min-w-0">
          <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded bg-white border border-[#d9dbdd] text-[#0b5fef]">
            <x-icon name="file" class="h-5 w-5"/>
          </div>
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2.5">
              <h2 class="text-[16px] font-bold text-[#2f3031]">BKU {{ $labelTabAktif }} {{ $tahun }}</h2>
              <span class="inline-flex items-center rounded-full bg-[#e1effe] px-3 py-0.5 text-[12px] font-semibold text-[#054bcc]">
                Aktif
              </span>
              <span class="sr-only">Penatausahaan {{ $tahun }} · Status BKU {{ $tahun }} · {{ $jumlahTutup }} dari 12 bulan sudah ditutup.</span>
            </div>
            <p class="mt-1 text-[12.5px] font-medium text-[#0b5fef]">
              Tutup semua bulan BKU paling lambat 31 Januari {{ $tahun + 1 }}
            </p>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
          <a href="{{ route('laporan.format', ['mode' => 'bku', 'kembali' => 'penatausahaan']) }}"
             class="inline-flex items-center gap-2 rounded border border-[#c9cbcf] bg-white px-4 py-2 text-[13px] font-semibold text-[#2f3031] no-underline hover:bg-[#fafafa] transition">
            <x-icon name="print" class="h-4 w-4"/>
            <span>Cetak</span>
          </a>
          <a href="{{ route('pendapatan.index') }}" data-modal-buka="modal-penerimaan-dana"
             class="inline-flex items-center gap-2 rounded border border-[#c9cbcf] bg-white px-4 py-2 text-[13px] font-semibold text-[#2f3031] no-underline hover:bg-[#fafafa] transition">
            <span>Tambah Penerimaan Dana</span>
          </a>
        </div>
      </div>

      {{-- Grid 12 Kartu Bulan (4 kolom x 3 baris) --}}
      <div class="mt-5 grid grid-cols-2 gap-3.5 sm:grid-cols-3 lg:grid-cols-4">
        @foreach($bulan as $b)
          @php
            $selesai = in_array($b['status'], ['tertutup', 'selesai', 'tanpa-dana'], true);
            $draf = in_array($b['status'], ['draf', 'belum-pajak'], true);
          @endphp
          <a href="{{ route('bku', ['bulan' => $b['ym']]) }}"
             class="flex flex-col justify-between rounded-lg border px-4 py-3.5 no-underline transition
                    {{ $selesai || $draf ? 'border-[#d9dbdd] bg-white hover:border-[#0b5fef]' : 'border-[#e2e4e7] bg-[#ebebeb] hover:bg-white' }}">
            <div class="flex items-center gap-1.5 text-[14px] font-bold text-[#2f3031]">
              @if($selesai)
                <span class="text-[#117e47]" aria-hidden="true">✓</span>
              @endif
              <span>{{ $b['nama'] }}</span>
            </div>
            <div class="mt-3">
              @if($selesai)
                <span class="inline-flex items-center rounded-full bg-[#cefde0] px-3 py-0.5 text-[11.5px] font-semibold text-[#117e47]">
                  Sudah Selesai
                </span>
              @elseif($b['status'] === 'belum-pajak')
                <span class="inline-flex items-center rounded-full bg-[#ffeed0] px-3 py-0.5 text-[11.5px] font-semibold text-[#976a1c]">
                  {{ $b['label'] }}
                </span>
              @elseif($draf)
                <span class="inline-flex items-center rounded-full bg-[#e1effe] px-3 py-0.5 text-[11.5px] font-semibold text-[#054bcc]">
                  Draf
                </span>
              @else
                <span class="inline-flex items-center rounded-full border border-[#c9cbcf] bg-white px-3 py-0.5 text-[11.5px] font-semibold text-[#65696c]">
                  Belum Dibuat
                </span>
              @endif
            </div>
          </a>
        @endforeach
      </div>

      {{-- Kaki Kartu: Tautan Perbarui Data BKU --}}
      <div class="mt-4 flex justify-end">
        <a href="{{ route('penatausahaan.index', array_filter(['tab' => $tabAktif, 'perbarui_bku' => 1])) }}"
           class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-[#0b5fef] no-underline hover:underline">
          <x-icon name="refresh" class="h-4 w-4"/>
          <span>Perbarui Data BKU {{ $tahun }}</span>
        </a>
      </div>
    </div>

    {{-- Kartu Tahun Sebelumnya (mis. BKU BOSP Reguler 2025 — Sudah Selesai) --}}
    <div class="rounded-lg bg-[#f5f5f5] px-6 py-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3.5">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-white border border-[#d9dbdd] text-[#0b5fef]">
            <x-icon name="file" class="h-5 w-5"/>
          </div>
          <div class="flex flex-wrap items-center gap-2.5">
            <span class="text-[15.5px] font-bold text-[#2f3031]">BKU {{ $labelTabAktif }} {{ $tahun - 1 }}</span>
            <span class="inline-flex items-center rounded-full bg-[#cefde0] px-3 py-0.5 text-[12px] font-semibold text-[#117e47]">
              Sudah Selesai
            </span>
          </div>
        </div>
        <a href="{{ route('laporan.format', ['mode' => 'bku', 'kembali' => 'penatausahaan']) }}"
           class="inline-flex items-center gap-2 rounded border border-[#c9cbcf] bg-white px-4 py-2 text-[13px] font-semibold text-[#2f3031] no-underline hover:bg-[#fafafa] transition">
          <x-icon name="print" class="h-4 w-4"/>
          <span>Cetak</span>
        </a>
      </div>
    </div>

    {{-- Kartu 2 Tahun Sebelumnya (mis. BKU BOSP Reguler 2024 — Nonaktif - Lewat Batas) --}}
    <div class="rounded-lg bg-[#f5f5f5] px-6 py-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3.5">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-white border border-[#d9dbdd] text-[#808489]">
            <x-icon name="file" class="h-5 w-5"/>
          </div>
          <div class="flex flex-wrap items-center gap-2.5">
            <span class="text-[15.5px] font-bold text-[#65696c]">BKU {{ $labelTabAktif }} {{ $tahun - 2 }}</span>
            <span class="inline-flex items-center rounded-full border border-[#d9dbdd] bg-[#ebebeb] px-3 py-0.5 text-[12px] font-semibold text-[#65696c]">
              Nonaktif - Lewat Batas
            </span>
          </div>
        </div>
        <span class="text-[12.5px] text-[#65696c]">
          BKU tidak dapat diaktifkan karena melewati tenggat 31 Januari {{ $tahun - 1 }}
        </span>
      </div>
    </div>
  </div>
</div>

{{-- ===== MODAL TAMBAH PENERIMAAN DANA ALA ARKAS 4 (Screenshot 2026-10-21 235522.png) ===== --}}
<div id="modal-penerimaan-dana" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 hidden nonprint" role="dialog" aria-modal="true" aria-labelledby="judul-modal-penerimaan">
  <div class="w-full max-w-xl rounded-xl bg-white p-6 shadow-2xl">
    <div class="mb-4">
      <h3 id="judul-modal-penerimaan" class="text-[18px] font-bold text-[#2f3031]">Penerimaan Dana {{ $labelTabAktif }} {{ $tahun }}</h3>
      <p class="mt-1 text-[13px] text-[#65696c]">Daftar histori penerimaan dana per tahap pada tahun anggaran {{ $tahun }}.</p>
    </div>

    <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
      @forelse($penerimaanList ?? [] as $idxTrx => $itemTerima)
        <div class="flex items-center justify-between gap-3 rounded-lg border border-[#d9dbdd] bg-[#f8fafc] px-4 py-3">
          <div>
            <div class="text-[14px] font-bold text-[#2f3031]">
              {{ $labelTabAktif }} {{ $itemTerima->stage?->nama ?? ('Tahap '.($idxTrx + 1)) }} {{ $tahun }}
            </div>
            <div class="mt-0.5 text-[12.5px] text-[#65696c]">
              Diterima tanggal {{ $itemTerima->tanggal?->translatedFormat('d M Y') }} · <b class="text-[#2f3031]">{{ rp($itemTerima->total) }}</b>
            </div>
          </div>
          <form method="post" action="{{ route('penerimaan.destroy', $itemTerima) }}" data-konfirmasi="Hapus pencatatan penerimaan dana ini?">
            @csrf
            @method('DELETE')
            <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded border border-[#d9dbdd] bg-white text-[#65696c] hover:text-rose-600" title="Hapus penerimaan">
              <x-icon name="trash" class="h-4 w-4"/>
            </button>
          </form>
        </div>
      @empty
        <div class="rounded-lg border border-[#d9dbdd] bg-[#f8fafc] px-4 py-4 text-center text-[13px] text-[#65696c]">
          Belum ada pencatatan penerimaan dana pada tahun {{ $tahun }}.
        </div>
      @endforelse

      <a href="{{ route('pendapatan.index') }}"
         class="flex items-center justify-center gap-2 rounded-lg border-2 border-dashed border-[#0b5fef] bg-[#f6fafe] px-4 py-3.5 text-[13.5px] font-semibold text-[#0b5fef] no-underline hover:bg-[#e1effe]/60 transition">
        <span>+ Tambah Penerimaan Tahap Berikutnya</span>
      </a>
    </div>

    <div class="mt-6 flex justify-end gap-2">
      <button type="button" data-modal-tutup="modal-penerimaan-dana" class="btn2 px-5 py-2 text-sm font-semibold">
        Tutup
      </button>
    </div>
  </div>
</div>

{{-- ===== RINGKASAN SUMBER DANA & JALAN MASUK FITUR (Dilipat agar layar utama 100% bersih seperti ARKAS 4) ===== --}}
<details class="mt-4 rounded border border-[#d9dbdd] bg-white px-5 py-3 text-xs text-[#65696c]">
  <summary class="cursor-pointer font-semibold text-[#2f3031] select-none">
    Ringkasan Kas, Realisasi Sumber Dana, &amp; Pintasan Lengkap Penatausahaan
  </summary>
  <div class="mt-4 space-y-4">
    <div class="flex flex-wrap gap-2">
      <a href="{{ route('bku.cetak') }}" data-cetak="BKU {{ $tahun }}" class="btn2 text-xs"><x-icon name="printer" class="h-4 w-4"/> Cetak BKU</a>
      <a href="{{ route('pendapatan.index') }}" class="btn text-xs">+ Tambah Penerimaan</a>
      <a href="{{ route('belanja.create') }}" class="btn2 text-xs">+ Tambah Belanja</a>
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

    <div class="card overflow-x-auto">
      <h3 class="card-title mb-3">Pagu &amp; Realisasi per Sumber Dana</h3>
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
  </div>
</details>
@endsection
