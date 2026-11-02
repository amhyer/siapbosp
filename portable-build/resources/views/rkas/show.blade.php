@extends('layouts.app', ['title' => 'Rencana Kegiatan Anggaran Sekolah (RKAS)', 'fullScreen' => empty($review)])
@section('content')
@php
  $sekAcuan = $h->school ?? auth()->user()->school;
  // Mode tinjauan Dinas (variabel $review terisi) tidak boleh memakai estimasi
  // pagu yang diisi sekolah — hanya alokasi resmi dari Dinas.
  $reviewJuknis ??= \App\Services\JuknisBosp2026Service::reviewKertasKerja($h, $sekAcuan, null, empty($review));
  $kodeNama ??= \App\Services\JuknisBosp2026Service::KODE_SNP_DEFAULT;
  $namaBulan ??= \App\Services\JuknisBosp2026Service::NAMA_BULAN;
  $bisaSisip = empty($review) && in_array($h->status, ['draft', 'revisi'], true);
  $sumberJudul = $h->sumber_dana ?? 'BOSP Reguler';
@endphp

@if(!empty($review))
<div class="mb-4 flex gap-2 items-center flex-wrap bg-amber-50 border border-amber-300 rounded-lg p-3">
  <span class="text-sm font-semibold text-amber-800">
    MODE TINJAUAN DINAS — RKAS {{ $review->school->nama ?? '' }} · TA {{ $review->tahun }} revisi ke-{{ $review->revisi_ke }} (status: {{ strtoupper($review->status) }})
  </span>
  @if($review->status === 'dikirim_dinas')
    <form method="post" action="{{ route('dinas.rkas.sahkan', $review->id) }}" data-konfirmasi="Sahkan RKAS ini? Sekolah akan dinotifikasi.">@csrf
      <button class="border rounded px-3 py-1 text-sm bg-emerald-600 text-white font-semibold">Sahkan</button>
    </form>
    <details class="relative">
      <summary class="border rounded px-3 py-1 text-sm bg-rose-600 text-white font-semibold cursor-pointer list-none">Minta Revisi</summary>
      <form method="post" action="{{ route('dinas.rkas.revisi', $review->id) }}" class="absolute left-0 mt-2 w-72 bg-white border border-slate-200 rounded-xl shadow-xl p-3 z-50">
        @csrf
        <label class="text-xs font-semibold text-slate-600">Catatan revisi untuk sekolah (wajib)</label>
        <textarea name="catatan_dinas" rows="3" class="border rounded w-full px-2 py-1 mt-1 text-xs" required minlength="5"></textarea>
        <button class="border rounded px-3 py-1 text-xs bg-rose-600 text-white font-semibold mt-2 w-full">Kirim Permintaan Revisi</button>
      </form>
    </details>
  @endif
  <a href="{{ route('dinas.rkas.pdf', $review->id) }}" class="border rounded px-3 py-1 text-sm bg-rose-50 border-rose-300">PDF</a>
  <a href="{{ route('dinas.rkas.xlsx', $review->id) }}" class="border rounded px-3 py-1 text-sm bg-green-50 border-green-300">Excel</a>
  <a href="{{ route('dinas.rkas') }}" class="border rounded px-3 py-1 text-sm">← Kembali ke RKAS Masuk</a>
</div>
@endif

{{-- ===== HEADER FULL-WIDTH DETAIL RKAS ALA ARKAS 4 (Screenshot 2026-10-28 145552.png) ===== --}}
<div class="border-b border-[#d9dbdd] pb-5">
  @if(empty($review))
    <a href="{{ route('rkas.index') }}" class="inline-flex items-center gap-2 text-[13.5px] font-semibold text-[#0b5fef] no-underline hover:underline mb-3">
      <span>←</span>
      <span>Kembali ke Penganggaran</span>
    </a>
  @endif

  <div class="flex flex-wrap items-start justify-between gap-4">
    {{-- Kiri: Judul RKAS + Subjudul Sumber Dana + Kotak Pagu Dana --}}
    <div class="space-y-2">
      <div>
        <h1 class="text-[22px] font-bold text-[#2f3031] leading-tight">Rencana Kegiatan Anggaran Sekolah (RKAS)</h1>
        <p class="text-[13px] font-semibold uppercase tracking-wide text-[#65696c] mt-0.5">
          {{ $sumberJudul }} {{ $h->tahun }}
          <span class="sr-only">· {{ $h->labelJenis() }} · Revisi ke-{{ $h->revisi_ke }} · {{ $h->no_rkas ?? '(belum ada nomor dokumen)' }}</span>
        </p>
      </div>

      <div class="inline-flex items-center overflow-hidden rounded border border-[#c9cbcf] bg-white text-[13px]">
        <span class="bg-[#f5f5f5] px-3.5 py-2 font-medium text-[#65696c] border-r border-[#c9cbcf]">
          Pagu Dana {{ $sumberJudul }}
        </span>
        <span class="px-4 py-2 font-bold text-[#2f3031]">
          {{ rp($reviewJuknis['pagu']) }}
        </span>
      </div>

      <div class="pt-1">
        @include('rkas.partials._status-arkas', ['h' => $h])
        @if($h->status === 'dikirim_dinas')
          <div class="text-[11px] text-indigo-600 mt-1">Dikirim {{ $h->dikirim_dinas_at?->format('d/m/Y H:i') }} — menunggu tinjauan Dinas</div>
        @endif
        @if($h->status === 'revisi')
          <div class="mt-1 text-[11px] bg-rose-50 border border-rose-200 text-rose-700 rounded px-2.5 py-1">
            Catatan Dinas ({{ $h->ditinjau_oleh }}): <b>{{ $h->catatan_dinas }}</b> — susun RKAS revisi baru, lalu kirim ulang ke Dinas.
          </div>
        @endif
      </div>
    </div>

    {{-- Kanan: Banner Status Disahkan + 5 Tombol Aksi ARKAS 4 (Cari, Perubahan, Pergeseran, Cetak, Review) --}}
    @if(empty($review))
    <div class="flex flex-col items-end gap-2.5">
      @if($h->status === 'disahkan' || $h->status === 'dikunci')
        <div class="inline-flex items-center gap-2 rounded bg-[#cefde0] px-3.5 py-1.5 text-[12.5px] font-semibold text-[#117e47]">
          <x-icon name="check-circle" class="h-4 w-4 shrink-0"/>
          <span>RKAS Sudah Disahkan!</span>
          <a href="{{ route('bantuan') }}#rincian" class="underline font-bold ml-1">Baca Info Selengkapnya</a>
        </div>
      @endif

      <div class="flex flex-wrap items-center justify-end gap-2">
        {{-- 1. Cari --}}
        <button type="button" data-toggle-cari-rkas
                class="inline-flex items-center gap-1.5 rounded border border-[#c9cbcf] bg-white px-3.5 py-2 text-[13px] font-semibold text-[#2f3031] hover:bg-[#f5f5f5] transition">
          <x-icon name="search" class="h-4 w-4"/>
          <span>Cari</span>
        </button>

        {{-- 2. Perubahan --}}
        <a href="{{ route('rkas.create', ['jenis' => 'perubahan', 'acuan' => $h->id]) }}"
           class="inline-flex items-center gap-1.5 rounded border border-[#c9cbcf] bg-white px-3.5 py-2 text-[13px] font-semibold text-[#2f3031] no-underline hover:bg-[#f5f5f5] transition">
          <x-icon name="refresh" class="h-4 w-4"/>
          <span>Perubahan</span>
        </a>

        {{-- 3. Pergeseran --}}
        <a href="{{ route('rkas.create', ['jenis' => 'pergeseran', 'acuan' => $h->id]) }}"
           class="inline-flex items-center gap-1.5 rounded border border-[#c9cbcf] bg-white px-3.5 py-2 text-[13px] font-semibold text-[#2f3031] no-underline hover:bg-[#f5f5f5] transition">
          <x-icon name="arrow-right" class="h-4 w-4"/>
          <span>Pergeseran</span>
        </a>

        {{-- 4. Cetak (Menuju Pratinjau Cetak Mencetak RKAS) --}}
        <a href="{{ route('laporan.format', ['mode' => 'rkas', 'rkas_id' => $h->id, 'kembali' => 'rkas']) }}"
           class="inline-flex items-center gap-1.5 rounded border border-[#c9cbcf] bg-white px-3.5 py-2 text-[13px] font-semibold text-[#2f3031] no-underline hover:bg-[#f5f5f5] transition">
          <x-icon name="print" class="h-4 w-4"/>
          <span>Cetak</span>
        </a>

        {{-- 5. Review (Tombol Hitam Khas ARKAS 4) --}}
        <a href="{{ route('penganggaran.review', ['rkas_id' => $h->id]) }}"
           class="inline-flex items-center gap-1.5 rounded bg-[#2f3031] px-5 py-2 text-[13px] font-semibold text-white no-underline hover:bg-slate-800 transition">
          <span>Review</span>
        </a>
      </div>

      <div class="text-[11px] font-medium text-[#0b5fef]">
        "Perubahan" dan "Pergeseran" membutuhkan koneksi internet
      </div>

      {{-- Tombol alur pengajuan/persetujuan internal & ekspor langsung --}}
      <div class="flex flex-wrap items-center justify-end gap-1.5 pt-1">
        <form method="post" action="{{ route('rkas.aksi', $h) }}" class="flex flex-wrap gap-1.5">
          @csrf
          @if($h->status === 'draft')
            <button name="aksi" value="ajukan" class="btn btn-sm"
                    @disabled(! $praAjukan['boleh_ajukan'])
                    title="{{ $praAjukan['boleh_ajukan'] ? 'Ajukan Kertas Kerja ke Kepala Sekolah' : 'Belum bisa diajukan: selesaikan '.$praAjukan['jumlah_blokir'].' hal yang memblokir pada panel Kelayakan Pengajuan' }}">
              Ajukan ke Kepsek
            </button>
          @endif
          @if($h->status === 'diajukan' && auth()->user()->role === 'kepsek')
            <button name="aksi" value="setujui" class="btn btn-sm">Setujui (Kepsek)</button>
          @endif
          @if(in_array($h->status, ['disetujui', 'revisi']))
            <button name="aksi" value="kirim_dinas" class="btn btn-sm bg-indigo-600" data-konfirmasi="Kirim RKAS ini ke Dinas untuk disahkan?">
              {{ $h->status === 'revisi' ? 'Kirim Ulang ke Dinas' : 'Kirim ke Dinas' }}
            </button>
          @endif
          @if($h->status === 'disahkan')
            <button name="aksi" value="kunci" class="btn2 btn-sm">Kunci RKAS</button>
          @endif
        </form>
        <a href="{{ route('rkas.salinArkas', $h) }}" class="btn2 btn-sm">Salin Cepat ke ARKAS 4</a>
        <a href="{{ route('rkas.pdf', $h) }}" class="btn2 btn-sm">PDF</a>
        <a href="{{ route('rkas.xlsx', $h) }}" class="btn2 btn-sm">Excel</a>
      </div>
    </div>
    @endif
  </div>

  {{-- Kotak Pencarian Uraian/Rekening (Muncul saat tombol Cari diklik) --}}
  <div id="kotak-cari-rkas" class="mt-3 hidden">
    <input type="search" id="input-cari-rkas" class="inp max-w-md text-sm" placeholder="Ketik nama kegiatan, kode rekening, atau uraian...">
  </div>
</div>

@if(in_array($h->status, ['draft', 'revisi'], true))
  @include('rkas.partials._pra-ajukan', ['pra' => $praAjukan, 'h' => $h])
@endif

{{-- ===== BARIS PERIODE ANGGARAN & PITA 12 TAB BULAN ALA ARKAS 4 ===== --}}
<div class="space-y-3">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <h2 class="text-[16px] font-bold text-[#2f3031]">Periode Anggaran {{ $h->tahun }}</h2>

    <div class="flex flex-wrap items-center gap-3">
      <div class="inline-flex items-center overflow-hidden rounded border border-[#c9cbcf] bg-white text-[13px]">
        <span class="bg-[#f5f5f5] px-3.5 py-1.5 font-medium text-[#65696c] border-r border-[#c9cbcf]">Dianggarkan</span>
        <span class="px-4 py-1.5 font-bold text-[#2f3031]" id="label-dianggarkan-bulan">{{ rp($reviewJuknis['total_anggaran']) }}</span>
      </div>
      @if($reviewJuknis['belum_dianggarkan'] !== 0)
        <div class="inline-flex items-center overflow-hidden rounded border border-amber-300 bg-amber-50 text-[12px] px-3 py-1.5 text-amber-900">
          <span>Belum Dianggarkan: <b class="ml-1">{{ rp($reviewJuknis['belum_dianggarkan']) }}</b></span>
        </div>
      @endif
    </div>
  </div>

  {{-- Pita 12 Tab Bulan (Januari s.d. Desember + Semua) --}}
  <div class="flex overflow-x-auto border-b border-[#d9dbdd] bg-white" role="tablist" aria-label="Pilih bulan anggaran">
    <button type="button" role="tab" aria-selected="true" data-tab-bulan-rkas="0"
            class="tab-bulan-rkas shrink-0 border-b-2 border-[#0b5fef] px-4 py-2.5 text-[13.5px] font-semibold text-[#0b5fef] transition">
      Semua Bulan
    </button>
    @foreach($namaBulan as $idxBulan => $labelBulan)
      <button type="button" role="tab" aria-selected="false" data-tab-bulan-rkas="{{ $idxBulan }}"
              class="tab-bulan-rkas shrink-0 border-b-2 border-transparent px-4 py-2.5 text-[13.5px] font-medium text-[#65696c] hover:text-[#2f3031] transition">
        {{ $labelBulan }}
      </button>
    @endforeach
  </div>

  {{-- ===== TABEL HIERARKI BERWARNA BERTINGKAT ALA ARKAS 4 ===== --}}
  <div class="overflow-x-auto rounded border border-[#d9dbdd] bg-white">
    <table class="tbl !min-w-[1080px]" id="tabel-rincian-rkas">
      <thead>
        <tr class="bg-[#f5f5f5] text-[#2f3031]">
          <th class="w-12">No</th>
          <th>Program Kegiatan</th>
          <th>Kegiatan</th>
          <th>Rekening Belanja</th>
          <th>Uraian</th>
          <th class="num">Dianggarkan</th>
          <th class="num">Dibelanjakan</th>
          <th>Satuan</th>
          <th class="num">Harga Satuan</th>
          <th class="num">Total</th>
          <th>Panduan</th>
          <th class="w-16 text-center">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @php
          $noUrut = 1;
          $grupKegiatan = $h->details->groupBy(fn ($item) => $item->kode_kegiatan ?: '06.05');
        @endphp
        @foreach($grupKegiatan as $kodeKeg => $itemsKeg)
          @php
            $kodeProgram = substr((string) $kodeKeg, 0, 2);
            $namaProgram = match ($kodeProgram) {
                '01' => 'Pengembangan Kompetensi Lulusan',
                '02' => 'Pengembangan Standar Isi',
                '03' => 'Pengembangan Standar Proses',
                '04' => 'Pengembangan Pendidik dan Tenaga Kependidikan',
                '05' => 'Pengembangan Sarana dan Prasarana Sekolah',
                '06' => 'Pengembangan Standar Pengelolaan',
                '07' => 'Pengembangan Standar Pembiayaan',
                '08' => 'Pengembangan dan Implementasi Sistem Penilaian',
                default => 'Program Operasional Satuan Pendidikan',
            };
            $namaKegiatan = $kodeNama[$kodeKeg] ?? 'Kegiatan Operasional Sekolah';
            $grupRekening = $itemsKeg->groupBy(fn ($item) => $item->rekening->kode ?? '5.1.02');
          @endphp

          {{-- Level 1: Baris Program Kegiatan (Biru Muda #dbeafe) --}}
          <tr class="baris-grup-rkas bg-[#dbeafe]/70 font-semibold text-[#1e3a8a]">
            <td>{{ $kodeProgram }}</td>
            <td colspan="8">{{ $namaProgram }}</td>
            <td class="num font-bold">{{ rp((int) $itemsKeg->sum('jumlah')) }}</td>
            <td colspan="2"></td>
          </tr>

          {{-- Level 2: Baris Kegiatan (Cyan Muda #e0f2fe) --}}
          <tr class="baris-grup-rkas bg-[#e0f2fe]/80 font-semibold text-[#0c4a6e]">
            <td class="font-mono text-xs">{{ $kodeKeg }}</td>
            <td></td>
            <td colspan="7">{{ $namaKegiatan }}</td>
            <td class="num font-bold">{{ rp((int) $itemsKeg->sum('jumlah')) }}</td>
            <td colspan="2"></td>
          </tr>

          @foreach($grupRekening as $kodeRek => $itemsRek)
            @php $namaRek = $itemsRek->first()?->rekening?->nama ?? 'Belanja Barang dan Jasa'; @endphp
            {{-- Level 3: Baris Rekening Belanja (Hijau Muda #dcfce7) --}}
            <tr class="baris-grup-rkas bg-[#dcfce7]/70 font-semibold text-[#14532d]">
              <td></td>
              <td></td>
              <td></td>
              <td colspan="6"><span class="font-mono text-xs mr-1.5">{{ $kodeRek }}</span> {{ $namaRek }}</td>
              <td class="num font-bold">{{ rp((int) $itemsRek->sum('jumlah')) }}</td>
              <td colspan="2"></td>
            </tr>

            {{-- Level 4: Baris Uraian --}}
            @foreach($itemsRek as $d)
              @php
                $petaBulan = $d->bulanMap();
                $bulanAktifList = array_keys(array_filter($petaBulan, fn ($v) => (int) $v > 0));
                if ($bulanAktifList === []) {
                    $bulanAktifList = [1];
                }
              @endphp
              <tr id="baris-{{ $d->id }}"
                  data-baris-uraian
                  data-bulan-aktif="{{ implode(',', $bulanAktifList) }}"
                  data-cari-teks="{{ mb_strtolower($kodeKeg.' '.$namaKegiatan.' '.$kodeRek.' '.$namaRek.' '.$d->uraian) }}"
                  class="scroll-mt-24 bg-[#f7fee7]/40 hover:bg-[#ecfccb]/60">
                <td class="text-xs text-[#65696c]">{{ $noUrut++ }}</td>
                <td class="text-xs text-[#65696c]">{{ $kodeProgram }}</td>
                <td class="font-mono text-xs text-[#65696c]">{{ $kodeKeg }}</td>
                <td class="font-mono text-xs text-[#65696c]">{{ $kodeRek }}</td>
                <td class="font-medium text-[#2f3031]">{{ $d->uraian }}</td>
                <td class="num">{{ $d->banyak }}</td>
                <td class="num text-[#65696c]">0</td>
                <td>{{ $d->satuan }}</td>
                <td class="num">{{ rp($d->harga_satuan) }}</td>
                <td class="num font-semibold text-[#2f3031]">{{ rp($d->jumlah) }}</td>
                <td>@include('partials._panduan-rincian', ['kode' => $d->rekening->kode ?? ''])</td>
                <td class="text-center">
                  @if($bisaSisip)
                    <details class="relative inline-block text-left">
                      <summary class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded border border-[#c9cbcf] bg-white text-[#2f3031] list-none hover:bg-[#f5f5f5]" title="Aksi baris">
                        ⋯
                      </summary>
                      <form method="post" action="{{ route('rkas.sisip', [$h, $d]) }}" class="absolute right-0 mt-1 w-64 bg-white border border-slate-200 rounded-xl shadow-xl p-3 z-40 space-y-2 text-xs">
                        @csrf
                        <div class="font-bold text-slate-700">Sisip Uraian ({{ $d->rekening->kode }})</div>
                        <input name="uraian" class="inp text-xs" maxlength="500" required placeholder="Uraian barang/jasa sisipan (maks. 500 karakter)">
                        <div class="grid grid-cols-2 gap-1.5">
                          <input type="number" name="banyak" min="1" value="1" class="inp text-xs" placeholder="Vol">
                          <input type="number" name="harga_satuan" min="0" value="{{ (int) $d->harga_satuan }}" class="inp text-xs" placeholder="Harga">
                        </div>
                        <select name="bulan" class="inp text-xs">
                          @foreach($namaBulan as $idx => $nb)<option value="{{ $idx }}">{{ $nb }}</option>@endforeach
                        </select>
                        <button class="btn w-full justify-center text-xs">Simpan Sisipan</button>
                      </form>
                    </details>
                  @else
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded text-[#808489]">⋯</span>
                  @endif
                </td>
              </tr>
            @endforeach
          @endforeach
        @endforeach
      </tbody>
      <tfoot>
        <tr class="font-bold bg-[#f5f5f5]">
          <td colspan="9" class="text-right">TOTAL</td>
          <td class="num">{{ rp($h->details->sum('jumlah')) }}</td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

{{-- ===== PANEL REVIEW KERTAS KERJA & GRAFIK PROPORSI DONUT (4 KARTU 2x2) ===== --}}
<div class="card space-y-4 mt-6">
  <div class="flex flex-wrap justify-between items-center gap-2 border-b border-slate-100 pb-3">
    <div>
      <h3 class="font-bold text-slate-800 text-sm">Review Kertas Kerja &amp; Kepatuhan Juknis BOSP 2026</h3>
      <p class="text-xs text-slate-500">{{ $reviewJuknis['aturan']['label_aturan'] }}</p>
    </div>
    <div class="flex gap-1.5 text-xs">
      <button type="button" data-tab-review="proporsi" id="btnTab-proporsi" class="btn btn-sm">Lihat grafik proporsi</button>
      <button type="button" data-tab-review="tahapan" id="btnTab-tahapan" class="btn2 btn-sm">Lihat per tahapan</button>
      <button type="button" data-tab-review="tahunan" id="btnTab-tahunan" class="btn2 btn-sm">Per Tahun</button>
    </div>
  </div>

  {{-- Ringkasan 2 Kartu Utama (Sudah Dianggarkan & Belum Dianggarkan) + Proporsi Tahap --}}
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
    <div class="rounded-lg bg-slate-50 border border-slate-200 p-3">
      <div class="text-slate-500">Total Pagu Sumber Dana</div>
      <div class="font-bold text-base text-slate-800">{{ rp($reviewJuknis['pagu']) }}</div>
    </div>
    <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-3">
      <div class="text-emerald-800">Sudah Dianggarkan</div>
      <div class="font-bold text-base text-emerald-900">{{ rp($reviewJuknis['total_anggaran']) }}</div>
    </div>
    <div class="rounded-lg {{ $reviewJuknis['belum_dianggarkan'] === 0 ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200' }} border p-3">
      <div class="text-slate-600">Belum Dianggarkan (Target Rp 0)</div>
      <div class="font-bold text-base">{{ rp($reviewJuknis['belum_dianggarkan']) }}</div>
    </div>
    <div class="rounded-lg {{ $reviewJuknis['tahap1_pct'] <= 50 ? 'bg-sky-50 border-sky-200' : 'bg-rose-50 border-rose-200' }} border p-3">
      <div class="text-slate-600">Proporsi Tahap 1 vs Tahap 2</div>
      <div class="font-bold text-base">{{ $reviewJuknis['tahap1_pct'] }}% : {{ $reviewJuknis['tahap2_pct'] }}%</div>
    </div>
  </div>

  @if(count($reviewJuknis['temuan']))
    <div class="space-y-1.5 text-xs">
      @foreach($reviewJuknis['temuan'] as $tm)
        <div class="rounded-lg px-3 py-2 border {{ $tm['level']==='blokir' ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-amber-50 border-amber-200 text-amber-800' }}">
          {{ $tm['pesan'] }}
        </div>
      @endforeach
    </div>
  @else
    <div class="rounded-lg bg-[#cefde0] border border-emerald-300 px-3.5 py-2 text-xs text-[#117e47] font-semibold">
      ✓ Alokasi anggaran Anda sudah sesuai juknis — seluruh parameter Kertas Kerja telah memenuhi ketentuan ARKAS 4 v{{ config('app.arkas_acuan', '4.3.0') }} &amp; Permendikdasmen No. 8 Tahun 2026.
    </div>
  @endif

  {{-- TAB 1: 4 KARTU GRAFIK DONUT PROPORSI (2x2) ALA ARKAS 4 --}}
  @php
    $propBuku = $reviewJuknis['proporsi']['buku'] ?? ['label' => 'Penyediaan Buku', 'nilai' => 0, 'pct' => 0, 'ok' => true, 'batas_label' => 'Min. 10%'];
    $propSarpras = $reviewJuknis['proporsi']['sarpras'] ?? ['label' => 'Pemeliharaan Sarpras', 'nilai' => 0, 'pct' => 0, 'ok' => true, 'batas_label' => 'Maks. 20%'];
    $propHonor = $reviewJuknis['proporsi']['honor'] ?? ['label' => 'Pembayaran Honor', 'nilai' => 0, 'pct' => 0, 'ok' => true, 'batas_label' => 'Maks. 20%'];
    $paguDasar = max(1, (int) $reviewJuknis['pagu']);
  @endphp
  <div id="tabReview-proporsi" class="grid md:grid-cols-2 gap-4 pt-1">
    {{-- Kartu Donut 1: Proporsi Belanja berdasarkan Kegiatan (Sarpras) --}}
    <div class="rounded-lg border border-[#d9dbdd] bg-white p-4 space-y-3">
      <div class="font-bold text-[14px] text-[#2f3031]">Proporsi Belanja berdasarkan Kegiatan</div>
      <div class="flex items-center gap-5">
        <svg viewBox="0 0 36 36" class="h-24 w-24 shrink-0 -rotate-90">
          <circle cx="18" cy="18" r="15.915" fill="none" stroke="#e2e8f0" stroke-width="4.5"></circle>
          <circle cx="18" cy="18" r="15.915" fill="none" stroke="#0b5fef" stroke-width="4.5"
                  stroke-dasharray="{{ min(100, (float) $propSarpras['pct']) }}, 100"></circle>
        </svg>
        <div class="space-y-1.5 text-xs flex-1">
          <div class="flex items-center justify-between gap-2">
            <span class="flex items-center gap-1.5 font-medium text-[#2f3031]"><span class="h-2.5 w-2.5 rounded-full bg-[#0b5fef]"></span> {{ $propSarpras['label'] }}</span>
            <span class="font-bold">{{ $propSarpras['pct'] }}% ({{ rp($propSarpras['nilai']) }})</span>
          </div>
          <div class="flex items-center justify-between gap-2 text-[#65696c]">
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span> Komponen Anggaran Lainnya</span>
            <span>{{ max(0, round(100 - (float) $propSarpras['pct'], 2)) }}%</span>
          </div>
          <div class="text-[11px] text-[#65696c]">Batas Juknis: <b>{{ $propSarpras['batas_label'] }}</b></div>
        </div>
      </div>
      <div class="rounded px-3 py-1.5 text-xs font-semibold {{ $propSarpras['ok'] ? 'bg-[#cefde0] text-[#117e47]' : 'bg-rose-50 text-rose-700' }}">
        {{ $propSarpras['ok'] ? '✓ Alokasi anggaran Anda sudah sesuai juknis' : '⚠ Alokasi melampaui batas juknis' }}
      </div>
    </div>

    {{-- Kartu Donut 2: Proporsi Antar Jenis Belanja Lainnya dari Total Pagu --}}
    <div class="rounded-lg border border-[#d9dbdd] bg-white p-4 space-y-3">
      <div class="font-bold text-[14px] text-[#2f3031]">Proporsi Antar Jenis Belanja Lainnya dari Total Pagu</div>
      <div class="space-y-2 text-xs">
        @foreach($reviewJuknis['antar_jenis'] as $aj)
          <div>
            <div class="flex justify-between mb-0.5">
              <span class="text-[#2f3031]">{{ $aj['label'] }}</span>
              <span class="font-bold">{{ $aj['pct'] }}% · {{ rp($aj['nilai']) }}</span>
            </div>
            <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
              <div class="h-full bg-[#0b5fef]" style="width:{{ min(100, $aj['pct']) }}%"></div>
            </div>
          </div>
        @endforeach
      </div>
    </div>

    {{-- Kartu Donut 3: Proporsi Anggaran Buku --}}
    <div class="rounded-lg border border-[#d9dbdd] bg-white p-4 space-y-3">
      <div class="font-bold text-[14px] text-[#2f3031]">Proporsi Anggaran Buku</div>
      <div class="flex items-center gap-5">
        <svg viewBox="0 0 36 36" class="h-24 w-24 shrink-0 -rotate-90">
          <circle cx="18" cy="18" r="15.915" fill="none" stroke="#e2e8f0" stroke-width="4.5"></circle>
          <circle cx="18" cy="18" r="15.915" fill="none" stroke="#10b981" stroke-width="4.5"
                  stroke-dasharray="{{ min(100, (float) $propBuku['pct']) }}, 100"></circle>
        </svg>
        <div class="space-y-1.5 text-xs flex-1">
          <div class="flex items-center justify-between gap-2">
            <span class="flex items-center gap-1.5 font-medium text-[#2f3031]"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> {{ $propBuku['label'] }}</span>
            <span class="font-bold">{{ $propBuku['pct'] }}% ({{ rp($propBuku['nilai']) }})</span>
          </div>
          <div class="flex items-center justify-between gap-2 text-[#65696c]">
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span> Komponen Anggaran Lainnya</span>
            <span>{{ max(0, round(100 - (float) $propBuku['pct'], 2)) }}%</span>
          </div>
          <div class="text-[11px] text-[#65696c]">Batas Juknis: <b>{{ $propBuku['batas_label'] }}</b></div>
        </div>
      </div>
      <div class="rounded px-3 py-1.5 text-xs font-semibold {{ $propBuku['ok'] ? 'bg-[#cefde0] text-[#117e47]' : 'bg-rose-50 text-rose-700' }}">
        {{ $propBuku['ok'] ? '✓ Alokasi anggaran Anda sudah sesuai juknis' : '⚠ Alokasi buku belum memenuhi batas minimal' }}
      </div>
    </div>

    {{-- Kartu Donut 4: Proporsi Anggaran Honor dari Total Pagu --}}
    <div class="rounded-lg border border-[#d9dbdd] bg-white p-4 space-y-3">
      <div class="font-bold text-[14px] text-[#2f3031]">Proporsi Anggaran Honor dari Total Pagu</div>
      <div class="flex items-center gap-5">
        <svg viewBox="0 0 36 36" class="h-24 w-24 shrink-0 -rotate-90">
          <circle cx="18" cy="18" r="15.915" fill="none" stroke="#e2e8f0" stroke-width="4.5"></circle>
          <circle cx="18" cy="18" r="15.915" fill="none" stroke="#f59e0b" stroke-width="4.5"
                  stroke-dasharray="{{ min(100, (float) $propHonor['pct']) }}, 100"></circle>
        </svg>
        <div class="space-y-1.5 text-xs flex-1">
          <div class="flex items-center justify-between gap-2">
            <span class="flex items-center gap-1.5 font-medium text-[#2f3031]"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> {{ $propHonor['label'] }}</span>
            <span class="font-bold">{{ $propHonor['pct'] }}% ({{ rp($propHonor['nilai']) }})</span>
          </div>
          <div class="flex items-center justify-between gap-2 text-[#65696c]">
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span> Komponen Anggaran Lainnya</span>
            <span>{{ max(0, round(100 - (float) $propHonor['pct'], 2)) }}%</span>
          </div>
          <div class="text-[11px] text-[#65696c]">Batas Juknis: <b>{{ $propHonor['batas_label'] }}</b></div>
        </div>
      </div>
      <div class="rounded px-3 py-1.5 text-xs font-semibold {{ $propHonor['ok'] ? 'bg-[#cefde0] text-[#117e47]' : 'bg-rose-50 text-rose-700' }}">
        {{ $propHonor['ok'] ? '✓ Alokasi anggaran Anda sudah sesuai juknis' : '⚠ Alokasi honor melampaui batas maksimal' }}
      </div>
    </div>
  </div>

  {{-- TAB 2: LIHAT PER TAHAPAN (Periode Salur Tahap 1 vs Tahap 2 + 12 Bulan) --}}
  <div id="tabReview-tahapan" class="hidden space-y-3 pt-1">
    <div class="grid sm:grid-cols-2 gap-3 text-xs">
      <div class="rounded-lg border border-slate-200 p-3">
        <div class="font-bold text-slate-700">Periode Salur Tahap 1 (Januari – Juni)</div>
        <div class="text-lg font-bold text-sky-700 mt-0.5">{{ rp($reviewJuknis['tahap1']) }} ({{ $reviewJuknis['tahap1_pct'] }}%)</div>
      </div>
      <div class="rounded-lg border border-slate-200 p-3">
        <div class="font-bold text-slate-700">Periode Salur Tahap 2 (Juli – Desember)</div>
        <div class="text-lg font-bold text-indigo-700 mt-0.5">{{ rp($reviewJuknis['tahap2']) }} ({{ $reviewJuknis['tahap2_pct'] }}%)</div>
      </div>
    </div>
    <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 text-xs">
      @foreach($reviewJuknis['per_bulan'] as $m => $nom)
        <div class="rounded border border-slate-200 p-2 bg-slate-50">
          <div class="text-slate-500">{{ $namaBulan[$m] }}</div>
          <div class="font-bold mt-0.5">{{ rp($nom) }}</div>
        </div>
      @endforeach
    </div>
  </div>

  {{-- TAB 3: REKAP PER TAHUN PER KEGIATAN SNP --}}
  <div id="tabReview-tahunan" class="hidden pt-1">
    <table class="tbl">
      <thead><tr><th>Kode Kegiatan SNP</th><th>Nama Kegiatan</th><th class="num">Total Setahun</th></tr></thead>
      <tbody>
        @foreach($reviewJuknis['per_kegiatan'] as $kodeKeg => $totKeg)
          <tr>
            <td class="font-mono text-xs">{{ $kodeKeg }}</td>
            <td>{{ $kodeNama[$kodeKeg] ?? 'Kegiatan Operasional Sekolah' }}</td>
            <td class="num font-bold">{{ rp($totKeg) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

<script nonce="{{ request()->attributes->get('csp_nonce') }}">
function setTabReview(tab){
  ['proporsi','tahapan','tahunan'].forEach(t => {
    const el = document.getElementById('tabReview-' + t);
    if (el) el.classList.toggle('hidden', t !== tab);
    const btn = document.getElementById('btnTab-' + t);
    if (btn) btn.className = (t === tab ? 'btn btn-sm' : 'btn2 btn-sm');
  });
}

document.addEventListener('click', function (ev) {
  const el = ev.target.closest('[data-tab-review]');
  if (el) setTabReview(el.dataset.tabReview);

  // Toggle kotak pencarian RKAS
  const btnCari = ev.target.closest('[data-toggle-cari-rkas]');
  if (btnCari) {
    const box = document.getElementById('kotak-cari-rkas');
    if (box) {
      box.classList.toggle('hidden');
      const inp = document.getElementById('input-cari-rkas');
      if (inp && !box.classList.contains('hidden')) inp.focus();
    }
  }

  // Filter tab 12 bulan pada Detail RKAS
  const tabBulan = ev.target.closest('[data-tab-bulan-rkas]');
  if (tabBulan) {
    const bulanDipilih = tabBulan.getAttribute('data-tab-bulan-rkas');
    document.querySelectorAll('[data-tab-bulan-rkas]').forEach(function (b) {
      const aktif = b === tabBulan;
      b.setAttribute('aria-selected', aktif ? 'true' : 'false');
      b.classList.toggle('border-[#0b5fef]', aktif);
      b.classList.toggle('text-[#0b5fef]', aktif);
      b.classList.toggle('font-semibold', aktif);
      b.classList.toggle('border-transparent', !aktif);
      b.classList.toggle('text-[#65696c]', !aktif);
    });
    document.querySelectorAll('[data-baris-uraian]').forEach(function (tr) {
      if (bulanDipilih === '0') {
        tr.classList.remove('hidden');
      } else {
        const daftar = (tr.getAttribute('data-bulan-aktif') || '').split(',');
        tr.classList.toggle('hidden', daftar.indexOf(bulanDipilih) === -1);
      }
    });
  }
});

// Pencarian langsung pada tabel rincian RKAS
document.addEventListener('input', function (ev) {
  if (ev.target && ev.target.id === 'input-cari-rkas') {
    const q = ev.target.value.trim().toLowerCase();
    document.querySelectorAll('[data-baris-uraian]').forEach(function (tr) {
      const teks = tr.getAttribute('data-cari-teks') || '';
      tr.classList.toggle('hidden', q !== '' && teks.indexOf(q) === -1);
    });
  }
});

// Periksa Ulang kelayakan pengajuan
document.addEventListener('click', async function (ev) {
  const btn = ev.target.closest('#btnPeriksaUlang');
  if (!btn) return;
  ev.preventDefault();
  const status = document.getElementById('praAjukanStatus');
  btn.disabled = true;
  if (status) status.textContent = 'Memeriksa…';
  try {
    const res = await fetch(btn.dataset.url, { headers: { 'Accept': 'application/json' } });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();
    if (status) {
      status.textContent = 'Pemeriksaan terbaru: ' + (data.boleh_ajukan
        ? 'siap diajukan'
        : data.jumlah_blokir + ' hal memblokir');
    }
    window.location.reload();
  } catch (e) {
    if (status) status.textContent = 'Gagal memeriksa, coba lagi.';
    btn.disabled = false;
  }
});
</script>
@endsection
