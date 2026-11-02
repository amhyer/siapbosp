@extends('layouts.app', ['title' => 'Aplikasi Desktop'])
@section('subtitle', 'Pasang SiapBOSP di laptop bendahara — penarikan data ARKAS dijalankan dari sini')

@section('content')
@php
    $portabel = $portabel ?? \App\Http\Controllers\UnduhAplikasiController::isPortable();
@endphp
<div class="grid lg:grid-cols-3 gap-5 items-start">
  <div class="card max-w-3xl lg:col-span-2">
    <div class="flex items-start gap-3">
      <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-xl">💻</span>
      <div>
        <h2 class="text-lg font-bold text-slate-800">Aplikasi Desktop SiapBOSP</h2>
        <p class="text-[13px] text-slate-500 mt-1">
          Website pusat menyimpan &amp; melaporkan data ke dinas. <b>Penarikan data ARKAS</b> dilakukan
          dari aplikasi di laptop bendahara — website pusat tidak pernah membaca database ARKAS langsung.
        </p>
      </div>
    </div>

    @if($portabel)
      <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-[13px] text-emerald-800">
        <b>Mode portabel aktif.</b> Anda sedang memakai aplikasi SiapBOSP di laptop ini.
        Tidak ada unduhan yang perlu dilakukan — gunakan tombol <b>Tarik Data dari ARKAS</b> di sidebar
        aplikasi untuk menarik data dari database ARKAS Desktop ke aplikasi ini.
      </div>
    @elseif(auth()->user()->isDinas())
      <div class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50 p-4 text-[13px] text-indigo-900">
        <div class="font-bold mb-1">Informasi Aplikasi Desktop untuk Tim Dinas</div>
        <p>Halaman ini merupakan pusat distribusi paket aplikasi desktop untuk bendahara sekolah. Paket pemasangan (<code class="font-mono">SiapBOSP-Setup-v12.exe</code>) dan berkas konfigurasi identitas sekolah (<code class="font-mono">akun-sekolah.conf</code>) diunduh langsung oleh bendahara saat pertama kali login menggunakan akun sekolah masing-masing.</p>
        <div class="mt-3 flex gap-2">
          <a href="{{ route('dinas.pengguna') }}" class="btn text-xs">Kelola Akun Sekolah</a>
          <a href="{{ route('dinas.sekolah') }}" class="btn2 text-xs">Daftar Sekolah</a>
        </div>
      </div>
    @else
      <div class="mt-4 flex flex-wrap gap-2">
        <a href="{{ route('unduh-pemasang') }}" class="btn">
          <x-icon name="download" class="h-4 w-4"/> Unduh Pemasang (EXE, ±77 MB)
        </a>
        <a href="{{ route('unduh-aplikasi') }}" class="btn2">
          <x-icon name="download" class="h-4 w-4"/> Unduh Portable (ZIP, ±38 MB)
        </a>
        <a href="{{ route('unduh-identitas') }}" class="btn2">
          <x-icon name="download" class="h-4 w-4"/> Unduh Identitas Sekolah (.conf)
        </a>
      </div>
      <p class="page-desc mt-3">Dua berkas (pemasang + identitas) disimpan ke folder yang sama — mis. <code class="font-mono">Downloads</code> — lalu klik 2× <code class="font-mono">SiapBOSP-Setup-v12.exe</code> dan isi nama serta kata sandi bendahara.</p>
    @endif

    <div class="border-t border-slate-100 mt-5 pt-4">
      <div class="text-sm font-bold text-slate-800"> Cara memakai di laptop bendahara</div>
      <ol class="list-decimal pl-5 text-[13px] text-slate-600 space-y-1 mt-2">
        <li>Pasang SiapBOSP di laptop — unduh &amp; jalankan aplikasi (atau paket Portable/ZIP).</li>
        <li>Buka aplikasi → tombol <b>Tarik Data dari ARKAS</b> ada di sidebar.</li>
        <li>Pastikan aplikasi ARKAS Desktop <b>tertutup</b>, lalu klik tombol tersebut.</li>
        <li>Data ARKAS dipindahkan ke aplikasi &amp; langsung dikirim ke website pusat (30–60 dtk).</li>
      </ol>
    </div>

    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50/60 p-3 text-[13px] text-amber-800">
      <b>Perhatian.</b> Berkas unduhan <b>Identitas Sekolah</b> (dan ZIP) membuat <b>token API baru</b> —
      token lama (termasuk yang dipakai oleh agen TarikArkas) otomatis <b>tidak aktif</b>. Pakai berkas
      hasil unduhan yang paling baru.
    </div>
  </div>

  <div class="space-y-4">
    <div class="card">
      <div class="text-sm font-bold text-slate-800 mb-2">Alur penarikan data</div>
      <ol class="list-decimal pl-5 text-[12px] text-slate-600 space-y-1.5">
        <li>Website pusat menerima data ARKAS dari sekolah.</li>
        <li>Aplikasi di laptop mengekspor database ARKAS → Excel.</li>
        <li>Impor ke aplikasi → sinkron ke website pusat.</li>
      </ol>
    </div>
    <div class="card">
      <div class="text-sm font-bold text-slate-800 mb-2">Tanpa aplikasi?</div>
      <p class="text-[12px] text-slate-600">
        Bila ARKAS tidak bisa dipasang di laptop, unggah berkas Excel ARKAS manual lewat halaman
        <a href="{{ route('impor-arkas.form') }}" class="link">Impor ARKAS</a>.
      </p>
    </div>
  </div>
</div>
@endsection
