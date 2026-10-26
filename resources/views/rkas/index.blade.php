@extends('layouts.app', ['title' => 'Penganggaran / Kertas Kerja RKAS-BOSP'])
@section('subtitle', 'Kertas Kerja Murni, Pergeseran, & Perubahan sesuai ARKAS 4 v4.3.0 & Permendikdasmen No. 8 Tahun 2026')
@section('content')

{{-- ===== Toolbar atas ===== --}}
<div class="card border-l-4 border-indigo-600 flex flex-wrap justify-between items-center gap-3">
  <div>
    <div class="font-bold text-slate-800 text-sm">📋 Aktivasi &amp; Penyusunan Kertas Kerja Tahun {{ $tahun }}</div>
    <p class="text-xs text-slate-500 mt-0.5">
      Pagu {{ $tabLabel }}: <b class="text-slate-800">{{ rp($pagu) }}</b> · Status Sekolah: <b>{{ strtoupper($sekolah->status_sekolah ?? 'negeri') }} ({{ $sekolah->jenjang }})</b>
      @if(empty($sekolah->nama_komite))
        · <a href="{{ route('profil.edit') }}" class="text-amber-600 underline font-semibold">⚠️ Lengkapi Data Komite di Profil</a>
      @else
        · Komite: <b>{{ $sekolah->nama_komite }}</b>
      @endif
    </p>
  </div>
  <div class="flex flex-wrap gap-2">
    <a href="{{ route('perencanaan.index') }}" class="btn2 btn">🧭 Dari RKT (Pra-ARKAS)</a>
    @if($adaTahunLalu)
    <form method="post" action="{{ route('rkas.salin') }}">
      @csrf
      <button class="btn2 btn">📑 Salin Kertas Kerja Tahun {{ $tahun - 1 }}</button>
    </form>
    @endif
    @if($aktifTerakhir)
      <a href="{{ route('rkas.create', ['jenis' => 'pergeseran', 'acuan' => $aktifTerakhir->id]) }}" class="btn2 btn">🔀 Buat Pergeseran</a>
      <a href="{{ route('rkas.create', ['jenis' => 'perubahan', 'acuan' => $aktifTerakhir->id]) }}" class="btn2 btn">♻️ Buat Perubahan</a>
    @endif
    <a href="{{ route('rkas.create', ['jenis' => 'murni', 'sumber' => $sumberTab]) }}" class="btn">➕ Susun Kertas Kerja Baru</a>
  </div>
</div>

{{-- ===== Tab sumber dana (ARKAS 4) ===== --}}
<div class="card !p-0 overflow-hidden">
  <div class="flex flex-wrap items-center gap-1 border-b border-slate-200 px-3" role="tablist" aria-label="Sumber dana">
    @foreach($tabs as $kunci => $tab)
      <a href="{{ route('rkas.index', ['tab' => $kunci]) }}"
         role="tab"
         aria-selected="{{ $tabAktif === $kunci ? 'true' : 'false' }}"
         class="border-b-2 px-3.5 py-3 text-[13px] font-semibold no-underline transition
                {{ $tabAktif === $kunci
                    ? 'border-indigo-600 text-indigo-700'
                    : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
        {{ $tab['label'] }}
      </a>
    @endforeach
  </div>

  {{-- ===== Satu baris per tahun anggaran ===== --}}
  <div class="space-y-3 p-4">
    @foreach($dokumen as $dok)
      @php $u = $dok['utama']; @endphp
      <div class="rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3.5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-slate-400" aria-hidden="true">📄</span>
              <span class="text-[15px] font-bold text-slate-800">
                {{ $u ? 'RKAS ' . $u->sumber_dana . ' ' . $dok['tahun'] : 'Kertas Kerja ' . $tabLabel . ' ' . $dok['tahun'] }}
              </span>

              @if($u)
                @include('rkas.partials._status-arkas', ['h' => $u, 'ringkas' => true])
              @elseif($dok['tahun_aktif'])
                <span class="badge bg-slate-100 text-slate-500">Belum Aktif</span>
              @else
                <span class="badge bg-slate-100 text-slate-500">Nonaktif - Lewat Batas</span>
              @endif
            </div>

            <div class="mt-1 text-[11.5px] text-slate-500">
              @if($u)
                Ver. {{ $u->labelJenis() }} — {{ ($u->updated_at ?? $u->created_at)?->format('d M Y') }}
                · {{ rp($dok['total']) }}
                @if($dok['jml_revisi'] > 1)
                  · {{ $dok['jml_revisi'] }} revisi
                @endif
              @elseif($dok['tahun_aktif'])
                Kertas Kerja {{ $tabLabel }} tahun ini belum dibuat.
              @else
                Kertas Kerja tidak diaktifkan pada tahun tersebut.
              @endif
            </div>
          </div>

          <div class="flex shrink-0 items-center gap-1.5">
            @if($u)
              {{-- Riwayat revisi: semua revisi tahun ini (dilipat agar tetap ala ARKAS). --}}
              @if($dok['jml_revisi'] > 1)
                <details class="relative" data-dropdown>
                  <summary class="btn2 btn btn-sm cursor-pointer select-none list-none [&::-webkit-details-marker]:hidden"
                           title="Lihat {{ $dok['jml_revisi'] }} revisi pada tahun {{ $dok['tahun'] }}">
                    🕘 Riwayat ({{ $dok['jml_revisi'] }})
                  </summary>
                  <div class="absolute right-0 z-40 mt-2 w-[21rem] rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl">
                    @foreach($dok['riwayat'] as $rev)
                      <a href="{{ route('rkas.show', $rev) }}"
                         class="flex items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-[12px] no-underline hover:bg-slate-50">
                        <span class="min-w-0">
                          <span class="block font-semibold text-slate-700">Revisi ke-{{ $rev->revisi_ke }} · {{ $rev->labelJenis() }}</span>
                          <span class="block text-[10.5px] text-slate-400">{{ $rev->updated_at?->format('d/m/Y H:i') }} · {{ rp((int) $rev->details->sum('jumlah')) }}</span>
                        </span>
                        <span class="badge shrink-0 bg-slate-100 text-slate-600">{{ $rev->labelStatusArkas() }}</span>
                      </a>
                    @endforeach
                  </div>
                </details>
              @endif

              <a href="{{ route('rkas.show', $u) }}" class="btn2 btn btn-sm" title="Buka / tinjau Kertas Kerja">📂 Buka</a>
              <a href="{{ route('rkas.pdf', $u) }}" class="btn2 btn btn-sm" title="Unduh PDF">📕</a>
              <a href="{{ route('rkas.xlsx', $u) }}" class="btn2 btn btn-sm" title="Unduh Excel">📗</a>
            @elseif($dok['tahun_aktif'])
              <a href="{{ route('rkas.create', ['jenis' => 'murni', 'sumber' => $sumberTab]) }}" class="btn btn-sm">Aktivasi Kertas Kerja</a>
            @else
              <span class="text-[10.5px] text-slate-400 text-right max-w-[13rem]">
                Kertas Kerja tahun {{ $dok['tahun'] }} tidak diaktifkan.
              </span>
            @endif
          </div>
        </div>
      </div>
    @endforeach

    <p class="text-[11px] text-slate-400">
      Satu baris = satu tahun anggaran (revisi terbaru ditampilkan; riwayat revisi dapat dibuka),
      sama seperti daftar <b>Penganggaran</b> di ARKAS 4. Riwayat 3 tahun: {{ implode(' · ', array_column($dokumen, 'tahun')) }}.
    </p>
  </div>
</div>

{{-- ===== Impor CSV ===== --}}
<div class="card">
  <h3 class="font-semibold text-slate-700 text-sm mb-1">📥 Impor Kertas Kerja RKAS dari CSV</h3>
  <p class="text-[11px] text-slate-400 mb-2">Format: <code>kode_rekening;uraian;satuan;banyak;harga;sumber_dana</code> (sumber dana opsional). Berkas CSV maks. 2 MB; pemrosesan dilakukan di antrean dan statusnya dapat dipantau setelah unggah.</p>
  <form method="post" action="{{ route('rkas.impor') }}" enctype="multipart/form-data" class="flex gap-2 items-center">
    @csrf
    <input type="file" name="berkas" accept=".csv,.txt" class="inp" required>
    <button class="btn2 btn whitespace-nowrap">Unggah &amp; Buat Revisi</button>
  </form>
</div>

@if($tabAktif === 'tambahan')
<div class="card border-l-4 border-violet-500">
  <p class="text-xs text-violet-800">
    🌟 <b>Sumber Dana Tambahan</b> (ARKAS 4.2.16+) — dokumen BOSP Afirmasi dan sumber dana tambahan lain
    ditampilkan di tab ini. Gunakan kode kegiatan khusus 06.33.x (Penguatan akses) &amp; 03.34.x (Penguatan mutu).
  </p>
</div>
@endif

@endsection
