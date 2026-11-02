@extends('layouts.app', ['title' => 'Review & Proporsi Anggaran', 'fullScreen' => true])
@section('subtitle', 'Kepatuhan Juknis BOSP 2026 (Permendikdasmen No 8/2026 Pasal 42) per kertas kerja')
@section('content')
@php
  $rkasDipilihId = (int) request('rkas_id');
  $aktifItem = collect($baris)->first(fn ($b) => $rkasDipilihId > 0 && (int) $b['header']->id === $rkasDipilihId)
      ?? ($baris[0] ?? null);
  $hAktif = $aktifItem['header'] ?? null;
  $reviewAktif = $hAktif
      ? \App\Services\JuknisBosp2026Service::reviewKertasKerja($hAktif, $sekolah ?? auth()->user()->school)
      : null;
  $namaBulan = \App\Services\JuknisBosp2026Service::NAMA_BULAN;
  $kodeNama = $hAktif
      ? (\App\Models\ArkasKode::peta(($sekolah ?? auth()->user()->school)->id, (int) $hAktif->tahun) + \App\Services\JuknisBosp2026Service::KODE_SNP_DEFAULT)
      : \App\Services\JuknisBosp2026Service::KODE_SNP_DEFAULT;
@endphp

{{-- Header Full-Width Review Kertas Kerja ala ARKAS 4 (frame_02_47s.jpg - frame_04_92s.jpg) --}}
<div class="border-b border-[#d9dbdd] pb-4 mb-5">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
    <a href="{{ $hAktif ? route('rkas.show', $hAktif) : route('rkas.index') }}"
       class="inline-flex items-center gap-2 text-[13.5px] font-semibold text-[#0b5fef] no-underline hover:underline">
      <span>←</span>
      <span>Kembali ke Halaman RKAS</span>
    </a>
    <a href="{{ route('bantuan') }}#rincian"
       class="inline-flex items-center gap-1.5 rounded bg-[#f5f5f5] px-3 py-1.5 text-[12px] text-[#2f3031] no-underline">
      <span>? Butuh panduan mengisi?</span>
      <span class="font-semibold text-[#0b5fef] underline">Baca Panduan Selengkapnya</span>
    </a>
  </div>

  <div class="flex flex-wrap items-start justify-between gap-4">
    <div>
      <h2 class="text-[22px] font-bold text-[#2f3031]">Review Kertas Kerja</h2>
      <p class="text-[13px] font-semibold uppercase text-[#65696c]">
        {{ $hAktif ? ($hAktif->sumber_dana ?? 'BOSP REGULER').' '.$hAktif->tahun : 'Review & Proporsi Anggaran — TA '.$tahun }}
      </p>
      <span class="sr-only">Review &amp; Proporsi Anggaran</span>
    </div>

    @if($reviewAktif)
      <div class="flex flex-wrap items-center gap-3">
        <div class="inline-flex items-center overflow-hidden rounded border border-emerald-300 bg-[#cefde0]/50 text-[13px]">
          <span class="bg-[#cefde0] px-3.5 py-2 font-semibold text-[#117e47] border-r border-emerald-300">✓ Sudah Dianggarkan</span>
          <span class="px-4 py-2 font-bold text-[#2f3031]">{{ rp($reviewAktif['total_anggaran']) }}</span>
        </div>
        <div class="inline-flex items-center overflow-hidden rounded border border-amber-300 bg-[#ffeed0]/60 text-[13px]">
          <span class="bg-[#ffeed0] px-3.5 py-2 font-semibold text-[#976a1c] border-r border-amber-300">Belum Dianggarkan</span>
          <span class="px-4 py-2 font-bold text-[#2f3031]">{{ rp($reviewAktif['belum_dianggarkan']) }}</span>
        </div>
      </div>
    @endif
  </div>
</div>

@if(empty($baris))
  <div class="card">
    <p class="text-center text-slate-400 py-8">Belum ada kertas kerja untuk tahun {{ $tahun }}.</p>
  </div>
@else
  {{-- Pemilih Tampilan Hasil Review: Lihat per tahapan vs Lihat grafik proporsi --}}
  @if($reviewAktif && $hAktif)
    <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
      <div class="w-full max-w-xs">
        <label for="pilih-mode-review" class="block text-[12px] font-semibold text-[#65696c] mb-1">Pilih tampilan hasil review</label>
        <select id="pilih-mode-review" class="inp text-sm font-medium text-[#2f3031]">
          <option value="tahapan">Lihat per tahapan</option>
          <option value="proporsi">Lihat grafik proporsi</option>
        </select>
      </div>

      @if(count($baris) > 1)
        <form method="get" action="{{ route('penganggaran.review') }}" class="flex items-center gap-2 text-xs">
          <label for="pilih-rkas-review" class="font-semibold text-[#65696c]">Dokumen:</label>
          <select id="pilih-rkas-review" name="rkas_id" class="inp inp-sm !w-auto" data-kirim-otomatis>
            @foreach($baris as $bItem)
              <option value="{{ $bItem['header']->id }}" @selected($bItem['header']->id === $hAktif->id)>
                {{ $bItem['header']->sumber_dana ?? 'BOS Reguler' }} · Rev {{ $bItem['header']->revisi_ke }} ({{ $bItem['header']->no_rkas ?? 'Tanpa No' }})
              </option>
            @endforeach
          </select>
        </form>
      @endif
    </div>

    {{-- MODE 1: LIHAT PER TAHAPAN (frame_02_47s.jpg) --}}
    <div id="panel-review-tahapan" class="space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#d9dbdd]">
        <div class="flex gap-2" role="tablist">
          <button type="button" data-tab-tahap="1" class="tab-tahap-btn border-b-2 border-[#0b5fef] px-4 py-2.5 text-[13.5px] font-semibold text-[#0b5fef]">
            Periode Salur Tahap 1
          </button>
          <button type="button" data-tab-tahap="2" class="tab-tahap-btn border-b-2 border-transparent px-4 py-2.5 text-[13.5px] font-medium text-[#65696c] hover:text-[#2f3031]">
            Periode Salur Tahap 2
          </button>
        </div>

        <div class="inline-flex items-center overflow-hidden rounded border border-[#c9cbcf] bg-white text-[13px] mb-2">
          <span class="bg-[#f5f5f5] px-3.5 py-1.5 font-medium text-[#65696c] border-r border-[#c9cbcf]" id="label-judul-tahap">Anggaran Tahap 1</span>
          <span class="px-4 py-1.5 font-bold text-[#2f3031]" id="label-nilai-tahap"
                data-tahap1="{{ rp($reviewAktif['tahap1']) }}"
                data-tahap2="{{ rp($reviewAktif['tahap2']) }}">{{ rp($reviewAktif['tahap1']) }}</span>
        </div>
      </div>

      <div class="overflow-x-auto rounded border border-[#d9dbdd] bg-white">
        <table class="tbl !min-w-[1000px]">
          <thead>
            <tr class="bg-[#f5f5f5]">
              <th class="w-12">No</th>
              <th>Uraian</th>
              <th class="num">Total</th>
              @for($m = 1; $m <= 6; $m++)
                <th class="num kol-tahap-1">{{ $namaBulan[$m] }}</th>
              @endfor
              @for($m = 7; $m <= 12; $m++)
                <th class="num kol-tahap-2 hidden">{{ $namaBulan[$m] }}</th>
              @endfor
            </tr>
          </thead>
          <tbody>
            @php $noRev = 1; @endphp
            @foreach($hAktif->details as $d)
              @php
                $mapBln = $d->bulanMap();
                $hrg = (int) $d->harga_satuan;
              @endphp
              <tr>
                <td class="text-xs text-[#65696c]">{{ $noRev++ }}</td>
                <td>
                  <div class="text-[11px] font-mono text-[#65696c]">{{ $d->kode_kegiatan ?? '06.05' }} · {{ $d->rekening->kode ?? '' }}</div>
                  <div class="font-medium text-[#2f3031]">{{ $d->uraian }}</div>
                </td>
                <td class="num font-bold">{{ rp($d->jumlah) }}</td>
                @for($m = 1; $m <= 6; $m++)
                  <td class="num kol-tahap-1 text-xs">{{ rp(((int) ($mapBln[$m] ?? 0)) * $hrg) }}</td>
                @endfor
                @for($m = 7; $m <= 12; $m++)
                  <td class="num kol-tahap-2 hidden text-xs">{{ rp(((int) ($mapBln[$m] ?? 0)) * $hrg) }}</td>
                @endfor
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    {{-- MODE 2: LIHAT GRAFIK PROPORSI (4 DONUT CHART 2x2 — frame_03_69s.jpg & frame_04_92s.jpg) --}}
    @php
      $propBuku = $reviewAktif['proporsi']['buku'] ?? ['label' => 'Penyediaan Buku', 'nilai' => 0, 'pct' => 0, 'ok' => true, 'batas_label' => 'Min. 10%'];
      $propSarpras = $reviewAktif['proporsi']['sarpras'] ?? ['label' => 'Pemeliharaan Sarpras', 'nilai' => 0, 'pct' => 0, 'ok' => true, 'batas_label' => 'Maks. 20%'];
      $propHonor = $reviewAktif['proporsi']['honor'] ?? ['label' => 'Pembayaran Honor', 'nilai' => 0, 'pct' => 0, 'ok' => true, 'batas_label' => 'Maks. 20%'];
    @endphp
    <div id="panel-review-proporsi" class="grid md:grid-cols-2 gap-4 hidden">
      {{-- Donut 1: Proporsi Belanja berdasarkan Kegiatan --}}
      <div class="rounded-lg border border-[#d9dbdd] bg-white p-5 space-y-3">
        <div class="font-bold text-[15px] text-[#2f3031]">Proporsi Belanja berdasarkan Kegiatan</div>
        <div class="flex items-center gap-6">
          <svg viewBox="0 0 36 36" class="h-28 w-28 shrink-0 -rotate-90">
            <circle cx="18" cy="18" r="15.915" fill="none" stroke="#e2e8f0" stroke-width="4.5"></circle>
            <circle cx="18" cy="18" r="15.915" fill="none" stroke="#0b5fef" stroke-width="4.5"
                    stroke-dasharray="{{ min(100, (float) $propSarpras['pct']) }}, 100"></circle>
          </svg>
          <div class="space-y-1.5 text-xs flex-1">
            <div class="flex justify-between"><span class="font-semibold text-[#2f3031]">● {{ $propSarpras['label'] }}</span><b>{{ $propSarpras['pct'] }}% ({{ rp($propSarpras['nilai']) }})</b></div>
            <div class="flex justify-between text-[#65696c]"><span>● Komponen Anggaran Lainnya</span><span>{{ max(0, round(100 - (float) $propSarpras['pct'], 2)) }}%</span></div>
            <div class="text-[11px] text-[#65696c]">Batas Juknis: <b>{{ $propSarpras['batas_label'] }}</b></div>
          </div>
        </div>
        <div class="rounded px-3 py-2 text-xs font-semibold {{ $propSarpras['ok'] ? 'bg-[#cefde0] text-[#117e47]' : 'bg-rose-50 text-rose-700' }}">
          {{ $propSarpras['ok'] ? '✓ Alokasi anggaran Anda sudah sesuai juknis' : '⚠ Alokasi melampaui batas juknis' }}
        </div>
      </div>

      {{-- Donut 2: Proporsi Antar Jenis Belanja Lainnya dari Total Pagu --}}
      <div class="rounded-lg border border-[#d9dbdd] bg-white p-5 space-y-3">
        <div class="font-bold text-[15px] text-[#2f3031]">Proporsi Antar Jenis Belanja Lainnya dari Total Pagu</div>
        <div class="space-y-2 text-xs">
          @foreach($reviewAktif['antar_jenis'] as $aj)
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

      {{-- Donut 3: Proporsi Anggaran Buku --}}
      <div class="rounded-lg border border-[#d9dbdd] bg-white p-5 space-y-3">
        <div class="font-bold text-[15px] text-[#2f3031]">Proporsi Anggaran Buku</div>
        <div class="flex items-center gap-6">
          <svg viewBox="0 0 36 36" class="h-28 w-28 shrink-0 -rotate-90">
            <circle cx="18" cy="18" r="15.915" fill="none" stroke="#e2e8f0" stroke-width="4.5"></circle>
            <circle cx="18" cy="18" r="15.915" fill="none" stroke="#10b981" stroke-width="4.5"
                    stroke-dasharray="{{ min(100, (float) $propBuku['pct']) }}, 100"></circle>
          </svg>
          <div class="space-y-1.5 text-xs flex-1">
            <div class="flex justify-between"><span class="font-semibold text-[#2f3031]">● {{ $propBuku['label'] }}</span><b>{{ $propBuku['pct'] }}% ({{ rp($propBuku['nilai']) }})</b></div>
            <div class="flex justify-between text-[#65696c]"><span>● Komponen Anggaran Lainnya</span><span>{{ max(0, round(100 - (float) $propBuku['pct'], 2)) }}%</span></div>
            <div class="text-[11px] text-[#65696c]">Batas Juknis: <b>{{ $propBuku['batas_label'] }}</b></div>
          </div>
        </div>
        <div class="rounded px-3 py-2 text-xs font-semibold {{ $propBuku['ok'] ? 'bg-[#cefde0] text-[#117e47]' : 'bg-rose-50 text-rose-700' }}">
          {{ $propBuku['ok'] ? '✓ Alokasi anggaran Anda sudah sesuai juknis' : '⚠ Alokasi buku belum memenuhi batas minimal' }}
        </div>
      </div>

      {{-- Donut 4: Proporsi Anggaran Honor dari Total Pagu --}}
      <div class="rounded-lg border border-[#d9dbdd] bg-white p-5 space-y-3">
        <div class="font-bold text-[15px] text-[#2f3031]">Proporsi Anggaran Honor dari Total Pagu</div>
        <div class="flex items-center gap-6">
          <svg viewBox="0 0 36 36" class="h-28 w-28 shrink-0 -rotate-90">
            <circle cx="18" cy="18" r="15.915" fill="none" stroke="#e2e8f0" stroke-width="4.5"></circle>
            <circle cx="18" cy="18" r="15.915" fill="none" stroke="#f59e0b" stroke-width="4.5"
                    stroke-dasharray="{{ min(100, (float) $propHonor['pct']) }}, 100"></circle>
          </svg>
          <div class="space-y-1.5 text-xs flex-1">
            <div class="flex justify-between"><span class="font-semibold text-[#2f3031]">● {{ $propHonor['label'] }}</span><b>{{ $propHonor['pct'] }}% ({{ rp($propHonor['nilai']) }})</b></div>
            <div class="flex justify-between text-[#65696c]"><span>● Komponen Anggaran Lainnya</span><span>{{ max(0, round(100 - (float) $propHonor['pct'], 2)) }}%</span></div>
            <div class="text-[11px] text-[#65696c]">Batas Juknis: <b>{{ $propHonor['batas_label'] }}</b></div>
          </div>
        </div>
        <div class="rounded px-3 py-2 text-xs font-semibold {{ $propHonor['ok'] ? 'bg-[#cefde0] text-[#117e47]' : 'bg-rose-50 text-rose-700' }}">
          {{ $propHonor['ok'] ? '✓ Alokasi anggaran Anda sudah sesuai juknis' : '⚠ Alokasi honor melampaui batas maksimal' }}
        </div>
      </div>
    </div>
  @endif

  {{-- Ringkasan Seluruh Dokumen Kertas Kerja Tahun Berjalan --}}
  <div class="card mt-6">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
      <h3 class="card-title mb-0">Daftar Kertas Kerja — TA {{ $tahun }}</h3>
      <a href="{{ route('rkas.index') }}" class="btn2 btn-sm">Kertas Kerja (RKAS)</a>
    </div>
    <div class="space-y-3">
      @foreach($baris as $b)
        @php $h = $b['header']; @endphp
        <div class="border border-slate-200 rounded-lg p-3">
          <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
            <div>
              <div class="font-semibold text-slate-800">Revisi ke-{{ $h->revisi_ke }} · {{ $h->no_rkas ?? '(tanpa nomor)' }}</div>
              <div class="text-[11px] text-slate-500">
                {{ $h->labelJenis() }} · {{ $h->sumber_dana ?? 'BOS Reguler' }} · {{ $h->details->count() }} rincian · total {{ rp($b['total']) }}
                @if($b['pagu'] > 0) · pagu {{ rp($b['pagu']) }} @endif
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span class="badge {{ $h->status === 'disahkan' || $h->status === 'dikunci' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ \App\Models\RkasHeader::LABEL_STATUS[$h->status] ?? $h->status }}</span>
              <a href="{{ route('rkas.show', $h) }}" class="btn2 btn-sm">Buka Review →</a>
            </div>
          </div>
          @if(! empty($b['proporsi']))
            <div class="grid sm:grid-cols-3 gap-2">
              @foreach($b['proporsi'] as $key => $p)
                @php
                  $label = $p['label'] ?? $key;
                  $nilai = $p['nilai'] ?? $p['nominal'] ?? null;
                  $persen = $p['persen'] ?? $p['pct'] ?? null;
                  $batasLabel = $p['batas_label'] ?? null;
                  $patuh = $p['ok'] ?? $p['patuh'] ?? null;
                @endphp
                <div class="border border-slate-200 rounded-lg px-3 py-2">
                  <div class="text-[11px] text-slate-500">{{ $label }}</div>
                  <div class="font-semibold text-slate-800">{{ $nilai !== null ? rp((int) $nilai) : '—' }}@if($persen !== null)<span class="text-[11px] text-slate-500"> ({{ $persen }}%)</span>@endif</div>
                  @if($batasLabel !== null)
                    <div class="text-[11px] {{ $patuh === false ? 'text-rose-700 font-semibold' : 'text-emerald-700' }}">
                      {{ $batasLabel }}{{ $patuh === false ? ' · terlampaui' : ' · sesuai' }}
                    </div>
                  @endif
                </div>
              @endforeach
            </div>
          @endif
        </div>
      @endforeach
    </div>
  </div>
@endif

<script nonce="{{ request()->attributes->get('csp_nonce') }}">
document.addEventListener('change', function (ev) {
  if (ev.target && ev.target.id === 'pilih-mode-review') {
    const mode = ev.target.value;
    const pTahap = document.getElementById('panel-review-tahapan');
    const pProp = document.getElementById('panel-review-proporsi');
    if (pTahap) pTahap.classList.toggle('hidden', mode !== 'tahapan');
    if (pProp) pProp.classList.toggle('hidden', mode !== 'proporsi');
  }
});

document.addEventListener('click', function (ev) {
  const btnTahap = ev.target.closest('[data-tab-tahap]');
  if (!btnTahap) return;
  const tahap = btnTahap.getAttribute('data-tab-tahap');
  document.querySelectorAll('[data-tab-tahap]').forEach(function (b) {
    const aktif = b === btnTahap;
    b.classList.toggle('border-[#0b5fef]', aktif);
    b.classList.toggle('text-[#0b5fef]', aktif);
    b.classList.toggle('font-semibold', aktif);
    b.classList.toggle('border-transparent', !aktif);
    b.classList.toggle('text-[#65696c]', !aktif);
  });
  document.querySelectorAll('.kol-tahap-1').forEach(function (el) { el.classList.toggle('hidden', tahap !== '1'); });
  document.querySelectorAll('.kol-tahap-2').forEach(function (el) { el.classList.toggle('hidden', tahap !== '2'); });
  const lblJ = document.getElementById('label-judul-tahap');
  const lblN = document.getElementById('label-nilai-tahap');
  if (lblJ) lblJ.textContent = 'Anggaran Tahap ' + tahap;
  if (lblN) lblN.textContent = lblN.getAttribute('data-tahap' + tahap) || '';
});
</script>
@endsection
