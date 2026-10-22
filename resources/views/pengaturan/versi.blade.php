@extends('layouts.app', ['title' => 'Pengaturan — Deteksi Versi ARKAS'])
@section('subtitle', 'Kesesuaian ARKAS 4 & Juknis BOSP 2026 di laptop sekolah')
@section('content')
<div class="card">
  <h3 class="card-title mb-3">🔎 Deteksi Versi ARKAS</h3>

  @if(! $hasil['portabel'])
    <p class="text-[13px] text-slate-600">ℹ️ {{ $hasil['pesan'] }}</p>
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
          <span class="badge bg-emerald-100 text-emerald-700">✅ Sudah memenuhi</span>
        @else
          <span class="badge bg-amber-100 text-amber-700">⚠️ Perlu pembaruan</span>
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
          <div class="text-slate-600">Tenggat penambiguation anggaran: <b>{{ \Carbon\Carbon::parse($info['tenggat_penganggaran'])->translatedFormat('d F Y') }}</b></div>
        @endif
        @if(! empty($info['tenggat_penatausahaan']))
          <div class="text-slate-600">Tenggat penatausahaan: <b>{{ \Carbon\Carbon::parse($info['tenggat_penatausahaan'])->translatedFormat('d F Y') }}</b></div>
        @endif
        <div class="text-slate-600">Batas pergeseran: <b>{{ $info['allow_pergeseran'] ?? '—' }}</b> · batas perubahan: <b>{{ $info['allow_perubahan'] ?? '—' }}</b></div>
        <div class="text-slate-600 text-[11px] mt-1">Tenggat &amp; batas diambil langsung dari ARKAS laptop ini (bukan ketetapan aplikasi).</div>
      </div>
    </div>
  @endif

  <div class="mt-4 flex flex-wrap gap-2">
    <a href="https://arkas.kemdikbud.go.id/download/arkas4" target="_blank" rel="noopener" class="btn2 btn-sm">⬇️ Unduh ARKAS 4 terbaru</a>
    <a href="{{ route('impor-arkas.form') }}" class="btn2 btn-sm">⬆️ Tarik Data ARKAS</a>
  </div>
</div>
@endsection
