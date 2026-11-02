@extends('layouts.app', ['title' => 'Pengaturan — Deteksi Versi & Sinkronisasi ARKAS'])
@section('subtitle', 'Penyelarasan ARKAS 4, penarikan data lokal, dan sinkronisasi ke server pusat')

@section('content')
<div class="space-y-4">
  {{-- KARTU 1: INTEGRASI & SINKRONISASI DATA --}}
  <div class="card">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3 mb-4">
      <div>
        <h3 class="card-title mb-0">Integrasi &amp; Sinkronisasi Data</h3>
        <p class="text-xs text-slate-500 mt-0.5">Tarik data dari ARKAS lokal dan kirim pembukuan ke server pusat dinas.</p>
      </div>
      <div class="flex items-center gap-2">
        <a href="{{ route('pengaturan.koneksi') }}" class="btn2 btn-sm">Pengaturan Koneksi</a>
      </div>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
      {{-- Kolom Tarik Data ARKAS --}}
      <div class="border border-slate-200 rounded-xl p-4 bg-slate-50/50 flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between gap-2 mb-2">
            <span class="font-semibold text-slate-800 text-sm flex items-center gap-1.5">
              <x-icon name="database" class="h-4 w-4 text-indigo-600"/>
              Tarik Data dari ARKAS
            </span>
            @if($tarikTersedia)
              <span class="badge bg-emerald-100 text-emerald-700">Siap Ditarik</span>
            @else
              <span class="badge bg-slate-100 text-slate-600">Mode Manual / Web</span>
            @endif
          </div>
          <p class="text-[12.5px] text-slate-600 mb-3 leading-relaxed">
            Menarik seluruh data transaksi BKU, nota, kuitansi, pajak, dan penganggaran RKAS langsung dari database aplikasi ARKAS Desktop di laptop ini.
          </p>
        </div>

        <div class="pt-2 border-t border-slate-200/60 flex flex-wrap items-center gap-2">
          @if($tarikTersedia)
            <form method="post" action="{{ route('tarik-arkas.tarik') }}" data-tombol-sibuk="⏳ Menarik data ARKAS…" data-konfirmasi="Tarik seluruh data dari aplikasi ARKAS Desktop ke aplikasi ini dan sinkronkan otomatis ke website sekarang? Pastikan aplikasi ARKAS sedang ditutup.">
              @csrf
              <input type="hidden" name="tahun" value="{{ $hasil['info']['tahun_aktif'] ?? session('tahun', now()->year) }}">
              <button type="submit" class="btn btn-sm bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">
                <x-icon name="refresh" class="h-4 w-4 inline mr-1"/>
                Tarik Data dari ARKAS
              </button>
            </form>
          @else
            <a href="{{ route('impor-arkas.form') }}" class="btn btn-sm bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">
              <x-icon name="upload" class="h-4 w-4 inline mr-1"/>
              Impor Database ARKAS
            </a>
            <a href="{{ route('aplikasi-desktop') }}" class="btn2 btn-sm text-slate-600">
              Unduh Aplikasi Desktop
            </a>
          @endif
        </div>
      </div>

      {{-- Kolom Sinkron ke Pusat --}}
      <div class="border border-slate-200 rounded-xl p-4 bg-slate-50/50 flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between gap-2 mb-2">
            <span class="font-semibold text-slate-800 text-sm flex items-center gap-1.5">
              <x-icon name="upload" class="h-4 w-4 text-emerald-600"/>
              Kirim / Sinkron ke Pusat
            </span>
            <span class="badge {{ ($statusSinkron['punya_akun'] ?? false) ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
              {{ ($statusSinkron['punya_akun'] ?? false) ? 'Terhubung' : 'Belum Konfigurasi' }}
            </span>
          </div>
          <p class="text-[12.5px] text-slate-600 mb-2 leading-relaxed">
            Kirim seluruh pembukuan lokal ke server pusat dinas pendidikan agar dapat diverifikasi dan disahkan dalam laporan BOS daerah.
          </p>
          <div class="text-[11.5px] text-slate-500 space-y-1 mb-3">
            <div>Terakhir dikirim: <b class="text-slate-700">{{ $statusSinkron['terakhir_dorong'] ?? '— belum pernah —' }}</b></div>
            <div>Transaksi lokal: <b class="text-slate-700">{{ number_format($statusSinkron['jumlah_transaksi_lokal'] ?? 0) }} baris</b></div>
          </div>
        </div>

        <div class="pt-2 border-t border-slate-200/60 flex flex-wrap items-center gap-2">
          <form method="post" action="{{ route('pengaturan.sinkronisasi.jalankan') }}" data-tombol-sibuk="☁️ Mengirim data ke pusat…" data-konfirmasi="Kirim seluruh transaksi BKU, nota, kuitansi, dan pajak ke website dinas sekarang?">
            @csrf
            <button type="submit" class="btn btn-sm bg-emerald-600 hover:bg-emerald-700 text-white font-semibold" {{ ($statusSinkron['punya_akun'] ?? false) ? '' : 'disabled' }}>
              <x-icon name="upload" class="h-4 w-4 inline mr-1"/>
              Kirim / Sinkron ke Pusat
            </button>
          </form>
          <form method="post" action="{{ route('pengaturan.koneksi.uji') }}">
            @csrf
            <button type="submit" class="btn2 btn-sm text-slate-600">Periksa Koneksi</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- KARTU 2: DETEKSI & KESESUAIAN VERSI ARKAS --}}
  <div class="card">
    <h3 class="card-title mb-3">Deteksi &amp; Kesesuaian Versi ARKAS</h3>
    @if(! $hasil['portabel'])
      <p class="text-[13px] text-slate-600">ℹ {{ $hasil['pesan'] }}</p>
    @else
      <div class="flex flex-wrap items-center gap-3 mb-3">
        <div class="border rounded-lg px-4 py-3 {{ $hasil['cukup'] ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200' }}">
          <div class="text-[11px] text-slate-500">Versi ARKAS di laptop ini</div>
          <div class="text-xl font-bold {{ $hasil['cukup'] ? 'text-emerald-700' : 'text-amber-700' }}">{{ $hasil['versi'] ?? 'tidak terbaca' }}</div>
        </div>
        <div class="border rounded-lg px-4 py-3 bg-slate-50 border-slate-200">
          <div class="text-[11px] text-slate-500">Minimum untuk Juknis 8/2026</div>
          <div class="text-xl font-bold text-slate-700">{{ $hasil['minimum'] }}</div>
        </div>
        <div>
          @if($hasil['cukup'])
            <span class="badge bg-emerald-100 text-emerald-700">Sudah memenuhi</span>
          @else
            <span class="badge bg-amber-100 text-amber-700">Perlu pembaruan</span>
          @endif
        </div>
      </div>
      @if($hasil['pesan'])
        <p class="text-[13px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2 mb-3">{{ $hasil['pesan'] }}</p>
      @endif

      @php($info = $hasil['info'])
      <div class="grid sm:grid-cols-2 gap-3 text-[13px]">
        <div class="border border-slate-200 rounded-lg p-3">
          <div class="font-semibold text-slate-700 mb-1">Data master di ARKAS</div>
          <div class="text-slate-600">Kode kegiatan: <b>{{ number_format((int) ($info['ref_kode'] ?? 0)) }}</b></div>
          <div class="text-slate-600">Rekening: <b>{{ number_format((int) ($info['ref_rekening'] ?? 0)) }}</b></div>
          <div class="text-slate-600">PTK: <b>{{ number_format((int) ($info['ptk'] ?? 0)) }}</b></div>
          <div class="text-slate-600">Konfigurasi aplikasi: <b>{{ number_format((int) ($info['app_config'] ?? 0)) }}</b> baris</div>
        </div>
        <div class="border border-slate-200 rounded-lg p-3">
          <div class="font-semibold text-slate-700 mb-1">Ketentuan AKTIF di ARKAS</div>
          <div class="text-slate-600">Tahun anggaran aktif: <b>{{ $info['tahun_aktif'] ?? '—' }}</b></div>
          @if(! empty($info['tenggat_penganggaran']))
            <div class="text-slate-600">Tenggat penganggaran: <b>{{ \Carbon\Carbon::parse($info['tenggat_penganggaran'])->translatedFormat('d F Y') }}</b></div>
          @endif
          @if(! empty($info['tenggat_penatausahaan']))
            <div class="text-slate-600">Tenggat penatausahaan: <b>{{ \Carbon\Carbon::parse($info['tenggat_penatausahaan'])->translatedFormat('d F Y') }}</b></div>
          @endif
          <div class="text-slate-600">Batas pergeseran: <b>{{ $info['allow_pergeseran'] ?? '—' }}</b> · batas perubahan: <b>{{ $info['allow_perubahan'] ?? '—' }}</b></div>
          <div class="text-slate-600 text-[11px] mt-1">Tenggat &amp; batas diambil langsung dari ARKAS laptop ini (bukan ketetapan aplikasi).</div>
        </div>
      </div>
    @endif

    <div class="mt-4 flex flex-wrap items-center gap-2">
      <a href="{{ route('aplikasi-desktop') }}" class="btn2 btn-sm">Unduh Aplikasi Desktop</a>
      <a href="https://arkas.kemdikbud.go.id/download/arkas4" target="_blank" rel="noopener" class="btn2 btn-sm">Unduh ARKAS 4 terbaru</a>
      <a href="{{ route('impor-arkas.form') }}" class="btn2 btn-sm">Impor Database (ARKAS)</a>
    </div>
  </div>
</div>
@endsection
