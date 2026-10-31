@extends('layouts.app', ['title' => 'Penganggaran / Kertas Kerja RKAS-BOSP', 'hidePageHead' => true])
@section('subtitle', 'Kertas Kerja Murni, Pergeseran, & Perubahan sesuai ARKAS 4 v'.config('app.arkas_acuan', '4.3.0').' & Permendikdasmen No. 8 Tahun 2026')
@section('content')

{{-- Container Utama Ala ARKAS 4 --}}
<div class="rounded-lg border border-[#d9dbdd] bg-white overflow-hidden">
  {{-- Tab sumber dana: partial bersama, persis pita tab ARKAS 4 --}}
  @include('partials._tab-sumber-dana', ['rute' => route('rkas.index'), 'aktif' => $tabAktif])

  {{-- ===== Sumber Dana Tambahan: Dropdown Pilih Sumber Dana Tambahan + Unduh dan Perbarui Data ===== --}}
  @if($tabAktif === 'tambahan')
  <div class="border-b border-[#efefef] bg-white px-6 pt-5 pb-4">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <form method="get" action="{{ route('rkas.index') }}" class="w-full max-w-xs">
        <input type="hidden" name="tab" value="tambahan">
        <label for="pilih-sdt" class="block text-[12px] font-semibold text-[#65696c] mb-1">Pilih Sumber Dana Tambahan</label>
        <select id="pilih-sdt" name="sumber_nama" class="inp text-sm" data-kirim-otomatis>
          <option value="">Semua Sumber Dana Tambahan</option>
          @foreach($daftarTambahan as $s)
            <option value="{{ $s->nama_sumber_dana }}" @selected($sumberDipilih === $s->nama_sumber_dana)>{{ $s->nama_sumber_dana }}</option>
          @endforeach
        </select>
      </form>

      <div class="flex flex-col items-end gap-1">
        <div class="flex flex-wrap items-center gap-2">
          <button type="button" data-modal-buka="modal-berhasil-perbarui-sdt"
                  class="inline-flex items-center gap-2 rounded border border-[#c9cbcf] bg-white px-4 py-2 text-[13px] font-semibold text-[#2f3031] hover:bg-[#f5f5f5] transition">
            <x-icon name="refresh" class="h-4 w-4"/>
            <span>Unduh dan Perbarui Data</span>
          </button>
          @if($bisaTarik)
            <form method="post" action="{{ route('tarik-arkas.tarik') }}"
                  data-tombol-sibuk="Sinkronisasi… (30-60 dtk)"
                  data-konfirmasi="Pastikan ARKAS Desktop sudah ditutup. Tarik daftar sumber dana terbaru?">
              @csrf
              <input type="hidden" name="tahun" value="{{ session('tahun', now()->year) }}">
              <button class="btn2 btn-sm"><x-icon name="refresh" class="h-4 w-4"/> {{ $daftarTambahan->isEmpty() ? 'Tarik Data ARKAS' : 'Perbarui dari ARKAS' }}</button>
            </form>
          @else
            <a href="{{ route('impor-arkas.form') }}" class="btn2 btn-sm"><x-icon name="upload" class="h-4 w-4"/> Perbarui via Impor ARKAS</a>
          @endif
        </div>
        <span class="text-[11px] font-medium text-[#0b5fef]">"Unduh dan Perbarui Data" membutuhkan koneksi internet</span>
      </div>
    </div>

    @if($daftarTambahan->isEmpty())
      <div class="mt-3 rounded border border-amber-200 bg-amber-50 px-3 py-2.5 text-[12px] text-amber-800">
        Daftar sumber dana tambahan <b>belum ada</b> di aplikasi ini.
        @if($bisaTarik)
          Klik <b>Tarik Data ARKAS</b> untuk membacanya dari <code>arkas.db</code> di laptop ini
          (ARKAS harus tertutup lebih dulu).
        @else
          Daftar ini hanya bisa ditarik di aplikasi portabel di laptop sekolah lewat tombol
          <b>Tarik Data ARKAS</b>; di server pusat, perbarui lewat unggah Excel di
          <a href="{{ route('impor-arkas.form') }}" class="underline">halaman Impor ARKAS</a>.
        @endif
      </div>
    @else
      <div class="mt-3 flex flex-wrap items-center gap-1.5" role="group" aria-label="Saring per nama sumber dana">
        <a href="{{ route('rkas.index', ['tab' => 'tambahan']) }}"
           aria-pressed="{{ $sumberDipilih === null ? 'true' : 'false' }}"
           class="rounded border px-3 py-1 text-[12px] font-semibold no-underline transition
                  {{ $sumberDipilih === null
                      ? 'border-indigo-600 bg-indigo-50 text-indigo-700'
                      : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
          Semua
        </a>
        @foreach($daftarTambahan as $s)
          @php $aktifChip = $sumberDipilih === $s->nama_sumber_dana; @endphp
          <a href="{{ route('rkas.index', array_filter(['tab' => 'tambahan', 'sumber_nama' => $aktifChip ? null : $s->nama_sumber_dana])) }}"
             aria-pressed="{{ $aktifChip ? 'true' : 'false' }}"
             title="{{ $s->kode ? 'Kode '.$s->kode : $s->nama_sumber_dana }}"
             class="rounded border px-3 py-1 text-[12px] font-semibold no-underline transition
                    {{ $aktifChip
                        ? 'border-indigo-600 bg-indigo-50 text-indigo-700'
                        : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
            {{ $s->nama_sumber_dana }}
          </a>
        @endforeach
      </div>
    @endif
  </div>

  {{-- Modal Berhasil Perbarui Data Sumber Dana Tambahan (Screenshot 235213.png) --}}
  <div id="modal-berhasil-perbarui-sdt" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 hidden nonprint" role="dialog" aria-modal="true" aria-labelledby="judul-modal-sdt">
    <div class="w-full max-w-md rounded-xl bg-white p-7 text-center shadow-2xl">
      <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[#cefde0] text-[#117e47]">
        <x-icon name="check-circle" class="h-10 w-10"/>
      </div>
      <h3 id="judul-modal-sdt" class="text-[18px] font-bold text-[#2f3031]">Berhasil Perbarui Data</h3>
      <p class="mt-1.5 text-[13.5px] text-[#65696c]">Data sumber dana tambahan sudah yang paling baru.</p>
      <div class="mt-6">
        <button type="button" data-modal-tutup="modal-berhasil-perbarui-sdt"
                class="inline-flex w-full items-center justify-center rounded bg-[#2f3031] px-5 py-2.5 text-[14px] font-semibold text-white hover:bg-slate-800 transition">
          Saya Mengerti
        </button>
      </div>
    </div>
  </div>
  @endif

  {{-- ===== Daftar Dokumen Kertas Kerja (Satu baris per tahun) ===== --}}
  <div class="p-6">
    @if($tabAktif === 'tambahan' && $sumberDipilih)
      <h3 class="mb-3 text-[15px] font-bold text-[#2f3031]">{{ $sumberDipilih }}</h3>
    @elseif($tabAktif === 'tambahan' && $daftarTambahan->isNotEmpty())
      <h3 class="mb-3 text-[15px] font-bold text-[#2f3031]">{{ $daftarTambahan->first()->nama_sumber_dana }}</h3>
    @endif

    <div class="space-y-3.5">
      @foreach($dokumen as $dok)
        @php $u = $dok['utama']; @endphp
        <div class="rounded-lg bg-[#f5f5f5] px-6 py-5 transition">
          <div class="flex flex-wrap items-center justify-between gap-4">
            {{-- Sisi Kiri: Ikon dokumen + Nama + Badge Status + Subjudul --}}
            <div class="flex items-start gap-4 min-w-0">
              <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded bg-white border border-[#d9dbdd] text-[#0b5fef]">
                <x-icon name="file" class="h-5 w-5"/>
              </div>
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2.5">
                  <span class="text-[16px] font-bold text-[#2f3031] tracking-tight">
                    {{ $u ? 'RKAS ' . $u->sumber_dana . ' ' . $dok['tahun'] : 'Kertas Kerja ' . $tabLabel . ' ' . $dok['tahun'] }}
                  </span>

                  @if($u)
                    @if($u->status === 'disahkan' || $u->status === 'dikunci')
                      <span class="inline-flex items-center rounded-full bg-[#cefde0] px-3 py-0.5 text-[12px] font-semibold text-[#117e47]">
                        Sudah Disahkan
                      </span>
                    @elseif($u->status === 'dikirim_dinas')
                      <span class="inline-flex items-center rounded-full bg-[#ffeed0] px-3 py-0.5 text-[12px] font-semibold text-[#976a1c]">
                        Menunggu Pengesahan
                      </span>
                    @elseif($u->status === 'revisi')
                      <span class="inline-flex items-center rounded-full bg-[#fed7aa] px-3 py-0.5 text-[12px] font-semibold text-[#c2410c]">
                        Perlu Revisi
                      </span>
                    @else
                      <span class="inline-flex items-center rounded-full bg-[#e1effe] px-3 py-0.5 text-[12px] font-semibold text-[#054bcc]">
                        Draf
                      </span>
                    @endif
                    <span class="sr-only">@include('rkas.partials._status-arkas', ['h' => $u, 'ringkas' => true])</span>
                  @elseif($dok['tahun_aktif'])
                    <span class="inline-flex items-center rounded-full border border-[#d9dbdd] bg-white px-3 py-0.5 text-[12px] font-semibold text-[#65696c]">
                      Belum Aktif
                    </span>
                  @else
                    <span class="inline-flex items-center rounded-full border border-[#d9dbdd] bg-[#ebebeb] px-3 py-0.5 text-[12px] font-semibold text-[#65696c]">
                      Nonaktif - Lewat Batas
                    </span>
                  @endif
                </div>

                <div class="mt-1 text-[13px] text-[#65696c]">
                  @if($u)
                    Ver. {{ $u->labelJenis() }} - {{ ($u->updated_at ?? $u->created_at)?->format('d M Y') }}
                    <span class="sr-only">· {{ rp($dok['total']) }}</span>
                    @if($dok['jml_revisi'] > 1)
                      · <span class="font-medium text-slate-600">{{ $dok['jml_revisi'] }} revisi</span>
                    @endif
                  @elseif(!$dok['tahun_aktif'])
                    <span class="sr-only">Kertas Kerja tidak diaktifkan karena melewati tenggat 31 Des {{ $dok['tahun'] }}</span>
                  @else
                    <span class="sr-only">Kertas Kerja {{ $tabLabel }} tahun ini belum dibuat.</span>
                  @endif
                </div>
              </div>
            </div>

            {{-- Sisi Kanan: 3 Ikon Aksi Persegi ARKAS 4 atau Tombol Aktivasi --}}
            <div class="flex items-center gap-2.5 shrink-0">
              @if($u)
                {{-- Riwayat revisi jika lebih dari satu --}}
                @if($dok['jml_revisi'] > 1)
                  <details class="relative" data-dropdown>
                    <summary class="btn2 btn-sm cursor-pointer select-none list-none text-xs"
                             title="Lihat {{ $dok['jml_revisi'] }} revisi pada tahun {{ $dok['tahun'] }}">
                      <x-icon name="activity" class="h-3.5 w-3.5"/> Riwayat ({{ $dok['jml_revisi'] }})
                    </summary>
                    <div class="absolute right-0 z-40 mt-1.5 w-72 rounded-lg border border-slate-200 bg-white p-1.5 shadow-xl">
                      @foreach($dok['riwayat'] as $rev)
                        <a href="{{ route('rkas.show', $rev) }}"
                           class="flex items-center justify-between gap-2 rounded px-2.5 py-2 text-[12px] no-underline hover:bg-slate-50">
                          <span class="min-w-0">
                            <span class="block font-semibold text-slate-700">Revisi ke-{{ $rev->revisi_ke }} · {{ $rev->labelJenis() }}</span>
                            <span class="block text-[10.5px] text-slate-400">{{ $rev->updated_at?->format('d/m/Y H:i') }}</span>
                          </span>
                          <span class="badge shrink-0 bg-slate-100 text-slate-600">{{ $rev->labelStatusArkas() }}</span>
                        </a>
                      @endforeach
                    </div>
                  </details>
                @endif

                {{-- Ikon 1: Buka/Edit Kertas Kerja (Kotak Pensil) --}}
                <a href="{{ route('rkas.show', $u) }}" data-buka-rkas
                   class="inline-flex h-9 w-9 items-center justify-center rounded border border-[#c9cbcf] bg-white text-[#2f3031] hover:border-[#0b5fef] hover:text-[#0b5fef] transition"
                   title="Buka Kertas Kerja">
                  <x-icon name="edit" class="h-4 w-4"/>
                </a>

                {{-- Ikon 2: Hapus Dokumen (Kotak Tempat Sampah nonaktif bila sudah disahkan) --}}
                <span class="inline-flex h-9 w-9 items-center justify-center rounded border border-[#e2e4e7] bg-[#f5f5f5] text-[#b4b7bb] cursor-not-allowed"
                      title="Dokumen Kertas Kerja yang sudah disahkan tidak dapat dihapus">
                  <x-icon name="trash" class="h-4 w-4"/>
                </span>

                {{-- Ikon 3: Cetak Dokumen (Membuka Pratinjau Cetak Mencetak RKAS) --}}
                <a href="{{ route('laporan.format', ['mode' => 'rkas', 'rkas_id' => $u->id, 'kembali' => 'penganggaran']) }}"
                   class="inline-flex h-9 w-9 items-center justify-center rounded border border-[#c9cbcf] bg-white text-[#2f3031] hover:border-[#0b5fef] hover:text-[#0b5fef] transition"
                   title="Cetak Kertas Kerja">
                  <x-icon name="print" class="h-4 w-4"/>
                </a>
              @elseif($dok['tahun_aktif'])
                <div class="text-right">
                  <a href="{{ route('rkas.create', ['jenis' => 'murni', 'sumber' => $sumberTab]) }}"
                     class="inline-flex items-center justify-center rounded bg-[#2f3031] px-5 py-2 text-[13.5px] font-semibold text-white hover:bg-slate-800 transition">
                    Aktivasi Kertas Kerja
                  </a>
                  <div class="text-[11px] text-[#0b5fef] mt-1 font-medium">"Aktivasi Kertas Kerja" membutuhkan koneksi internet</div>
                </div>
              @else
                <span class="text-[12.5px] text-[#65696c]">
                  Kertas Kerja tidak dapat diaktifkan karena melewati tenggat 31 Desember {{ $dok['tahun'] }}
                </span>
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</div>

{{-- Panel Alat Bantu Tambahan SiapBOSP (Dilipat agar tampilan utama 100% bersih seperti ARKAS 4) --}}
<details class="mt-4 rounded border border-[#d9dbdd] bg-white px-5 py-3 text-xs text-[#65696c]">
  <summary class="cursor-pointer font-semibold text-[#2f3031] select-none">
    Alat Bantu Tambahan SiapBOSP (Susun Baru, Salin Tahun Lalu, &amp; Impor CSV)
  </summary>
  <div class="mt-3 grid gap-4 lg:grid-cols-2">
    <div class="card">
      <h3 class="card-title mb-2 flex items-center gap-2">
        <x-icon name="settings" class="h-4 w-4 text-slate-500"/> Tindakan Kertas Kerja
      </h3>
      <p class="text-xs text-slate-500 mb-3">
        Pagu {{ $tabLabel }}: <b class="text-slate-800">{{ rp($pagu) }}</b> · Status: <b>{{ strtoupper($sekolah->status_sekolah ?? 'negeri') }} ({{ $sekolah->jenjang }})</b>
      </p>
      <div class="flex flex-wrap gap-2">
        <a href="{{ route('rkas.create', ['jenis' => 'murni', 'sumber' => $sumberTab]) }}" class="btn text-xs">
          <x-icon name="plus" class="h-3.5 w-3.5"/> Susun Kertas Kerja Baru
        </a>
        @if($aktifTerakhir)
          <a href="{{ route('rkas.create', ['jenis' => 'pergeseran', 'acuan' => $aktifTerakhir->id]) }}" class="btn2 text-xs">
            <x-icon name="arrow-right" class="h-3.5 w-3.5"/> Buat Pergeseran
          </a>
          <a href="{{ route('rkas.create', ['jenis' => 'perubahan', 'acuan' => $aktifTerakhir->id]) }}" class="btn2 text-xs">
            <x-icon name="refresh" class="h-3.5 w-3.5"/> Buat Perubahan
          </a>
        @endif
        @if($adaTahunLalu)
          <form method="post" action="{{ route('rkas.salin') }}" class="inline">
            @csrf
            <button class="btn2 text-xs"><x-icon name="copy" class="h-3.5 w-3.5"/> Salin Tahun {{ $tahun - 1 }}</button>
          </form>
        @endif
        <a href="{{ route('perencanaan.index') }}" class="btn2 text-xs">
          <x-icon name="chart" class="h-3.5 w-3.5"/> Dari RKT (Pra-ARKAS)
        </a>
      </div>
    </div>

    <div class="card">
      <h3 class="card-title mb-1 flex items-center gap-2">
        <x-icon name="upload" class="h-4 w-4 text-slate-500"/> Impor Kertas Kerja RKAS dari CSV
      </h3>
      <p class="text-[11px] text-slate-400 mb-2">Format: <code>kode_rekening;uraian;satuan;banyak;harga;sumber_dana</code>. Maks. 2 MB.</p>
      <form method="post" action="{{ route('rkas.impor') }}" enctype="multipart/form-data" class="flex gap-2 items-center">
        @csrf
        <input type="file" name="berkas" accept=".csv,.txt" class="inp" required>
        <button class="btn2 whitespace-nowrap text-xs">Unggah &amp; Buat</button>
      </form>
    </div>
  </div>
</details>

@endsection
