<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="SiapBOSP — Sistem Akuntansi Dana BOSP multi sekolah: perencanaan, pembukuan, dan pelaporan.">
<link rel="icon" href="/favicon.ico">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#0b5fef">
<link rel="apple-touch-icon" href="/icons/icon.svg">
@use('App\Support\Aset')
<title>{{ $title ?? 'SiapBOSP' }} — SiapBOSP</title>
@fonts
@if (Aset::viteAktif())
    {{-- Server dev (npm run dev) hidup: biarkan Vite menangani aset + HMR. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@elseif (Aset::cssSiap())
    {{-- Jalur produksi: rujuk berkas hasil `npm run build` secara langsung.
         Tanpa Vite, tanpa manifest, tanpa berkas `hot` — sehingga halaman tidak
         bisa lagi tampil polos karena manifest rusak atau hot basi. --}}
    @foreach (Aset::cssUrl() as $css)
        <link rel="stylesheet" href="{{ $css }}">
    @endforeach
    @foreach (Aset::jsUrl() as $js)
        <script src="{{ $js }}" defer></script>
    @endforeach
@else
    {{-- Aset build tidak ada: pakai Tailwind di browser + gaya komponen layout. --}}
    @if (Aset::tailwindJsAda())
        <script nonce="{{ request()->attributes->get('csp_nonce') }}" src="/tailwind.js"></script>
    @endif
    @include('layouts._gaya-darurat')
@endif
<style>
  /* Aturan cetak untuk halaman aplikasi: kerangka (sidebar, header, footer,
     pesan flash) disembunyikan, isi dokumen tetap tercetak rapi. */
  @media print{
    .nonprint{display:none !important}
    body{background:#fff !important;color:#0f172a !important}
    main{padding:0 !important;max-width:none !important}
    .card{box-shadow:none !important}
    @page{size:A4;margin:1.5cm}
  }
  /* Halaman dokumen (SPTJM, LPJ per Tahap) yang tampil dengan kerangka aplikasi
     tapi tetap berformat dokumen resmi saat dicetak. */
  .dokumen{background:#fff;max-width:62rem;margin:0 auto;padding:1.2rem;border-radius:.8rem;box-shadow:0 1px 3px rgb(15 23 42/.08)}
  .dokumen .kop{border-bottom:3px double #0f172a;padding-bottom:.6rem;margin-bottom:1rem;text-align:center}
  .dokumen .tbl th,.dokumen .tbl td{border:1px solid #94a3b8;padding:.32rem .5rem;vertical-align:top}
  .dokumen .tbl th{background:#f1f5f9}
  .dokumen .toolbar{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem}
  .dokumen .toolbar a,.dokumen .toolbar button{border:1px solid #cbd5e1;background:#fff;border-radius:.5rem;padding:.4rem .8rem;font-size:.82rem;text-decoration:none;color:#0f172a;cursor:pointer}
  .dokumen .ttd{display:flex;justify-content:flex-end;margin-top:2rem}
  .dokumen .ttd-grid{display:grid;grid-template-columns:1fr 1fr;gap:3.5rem;text-align:center;font-size:.82rem}
  .dokumen .pg{break-before:page}
  @media print{
    .dokumen{box-shadow:none !important;padding:0 !important;max-width:none !important;border-radius:0}
  }
</style>
@stack('head')
</head>
<body>
@php
  $u = auth()->user();
  $tahunAktif = (int) session('tahun', now()->year);
@endphp

{{-- =====================================================================
     Definisi menu sekolah.
     Struktur mengikuti ARKAS 4 agar operator yang sudah terbiasa dengan
     ARKAS langsung menemukan letak menunya:
       · 3 area kerja di sidebar: Penganggaran, Penatausahaan, Arsip
       · Data Sekolah pindah ke dropdown profil di topbar (ARKAS: Profil ▾)
       · Laporan/Referensi/Pengaturan tetap ada di bawah pemisah karena
         SiapBOSP punya fitur yang tidak ada di ARKAS 4.
     ===================================================================== --}}
@php
  $menuUtama = [
    ['label'=>'Penganggaran','href'=>route('rkas.index'),'aktif'=>request()->is('rkas*')||request()->is('perencanaan*')||request()->is('penganggaran*'),'open'=>false,'items'=>[
      ['Kertas Kerja (RKAS)', route('rkas.index'), request()->is('rkas*')],
      ['Review & Proporsi Anggaran', route('penganggaran.review'), request()->is('penganggaran/review*')],
      ['Daftar Kegiatan', route('penganggaran.kegiatan'), request()->is('penganggaran/kegiatan*')],
      ['Anggaran Bulanan (ARKAS)', route('penganggaran.anggaran-bulanan'), request()->is('penganggaran/anggaran-bulanan')],
      ['PBD → RKM → RKT (Perencanaan)', route('perencanaan.index'), request()->routeIs('perencanaan.index')],
    ]],
    // ARKAS 4 hanya punya satu menu "Penatausahaan" di sidebar; rinciannya
    // (BKU, kas, pendapatan, belanja, pajak, dll.) dibuka dari halaman itu.
    ['label'=>'Penatausahaan', 'href'=>route('penatausahaan.index'),
      'aktif'=>request()->is('penerimaan*')||request()->is('pengeluaran*')||request()->is('pembukuan*')||request()->is('penatausahaan*')],
    ['label'=>'Arsip', 'href'=>route('arsip.index'), 'aktif'=>request()->is('arsip*'), 'open'=>request()->is('arsip*'),'items'=>[
      ['Arsip Dokumen Sah', route('arsip.index'), request()->routeIs('arsip.index')],
      ['RKAS Disahkan', route('arsip.rkas'), request()->is('arsip/rkas*')],
      ['BKU Ditutup', route('arsip.bku'), request()->is('arsip/bku*')],
      ['Dokumen Pajak', route('arsip.pajak'), request()->is('arsip/pajak*')],
      ['Riwayat Impor', route('arsip.impor'), request()->is('arsip/impor*')],
      ['Riwayat Ekspor', route('arsip.ekspor'), request()->is('arsip/ekspor*')],
    ]],
  ];

  $menuTambahan = [
    ['label'=>'Laporan','open'=>request()->is('laporan*')||request()->is('laporan-arkas*')||request()->is('pengeluaran/belanja*'),'items'=>[
      ['Pusat Format Laporan', route('laporan.format'), request()->routeIs('laporan.format')],
      ['Kelengkapan Berkas & Kwitansi (SPJ)', route('belanja.index'), request()->is('pengeluaran/belanja*')],
      ['LPJ & Tutup Buku per Tahap', route('laporan.tahap'), request()->routeIs('laporan.tahap')],
      ['LPJ per Tahap', route('laporan.lpj'), request()->routeIs('laporan.lpj*')],
      ['Pajak Triwulan', route('laporan.pajak-tw'), request()->routeIs('laporan.pajak-tw')],
      ['Realisasi per Sumber Dana', route('laporan.realisasi-sumber-dana'), request()->is('laporan/realisasi-sumber-dana')],
      ['Rekonsiliasi ARKAS', route('laporan.rekonsiliasi'), request()->is('laporan/rekonsiliasi')],
      ['Format ARKAS', '#', false],
      ['Rincian RKAS per Tahap (ARKAS)', route('laporan-arkas.rkas-tahap'), request()->is('laporan-arkas/rkas-tahap')],
      ['Realisasi Anggaran (ARKAS)', route('laporan-arkas.realisasi'), request()->is('laporan-arkas/realisasi')],
      ['Realisasi Penerimaan & Belanja (ARKAS)', route('laporan-arkas.penerimaan-belanja'), request()->is('laporan-arkas/penerimaan-belanja')],
      ['Realisasi Habis Pakai (ARKAS)', route('laporan-arkas.habis-pakai'), request()->is('laporan-arkas/habis-pakai')],
      ['Realisasi Modal (ARKAS)', route('laporan-arkas.modal'), request()->is('laporan-arkas/modal')],
      ['Daftar Kas Umum (ARKAS)', route('laporan-arkas.kas-umum'), request()->is('laporan-arkas/daftar-kas-umum')],
      ['Daftar Nota (ARKAS)', route('laporan-arkas.nota'), request()->is('laporan-arkas/daftar-nota')],
    ]],
    ['label'=>'Data Referensi','open'=>request()->is('referensi*')||request()->is('perencanaan/ssh*'),'items'=>[
      ['Referensi Master', route('referensi'), request()->routeIs('referensi')],
      ['Referensi Harga (SSH/SBU)', route('referensi.harga'), request()->is('referensi/harga')],
    ]],
    ['label'=>'Pengaturan','open'=>request()->is('pengaturan*')||request()->is('utility*')||request()->is('impor-arkas*')||request()->is('hpp*'),'items'=>array_values(array_filter([
      ['Koneksi ARKAS', route('pengaturan.koneksi'), request()->is('pengaturan/koneksi*')],
      ['Impor Database (ARKAS)', route('pengaturan.impor-database'), request()->is('pengaturan/impor-database')],
      \App\Services\TarikArkasService::tersedia()
        ? ['Sinkron ARKAS (1-klik)', route('tarik-arkas.sinkron').'#sinkron-arkas', request()->is('utility/sinkron-arkas*')]
        : null,
      ['Pemetaan Data (HPP)', route('pengaturan.pemetaan'), request()->is('pengaturan/pemetaan')],
      ['Backup Data', route('pengaturan.backup'), request()->is('pengaturan/backup*')],
      ['Deteksi Versi ARKAS', route('pengaturan.versi'), request()->is('pengaturan/deteksi-versi*')],
      ['Log Sistem', route('pengaturan.log'), request()->is('pengaturan/log*')],
      ['Ganti Password', route('password.edit'), request()->routeIs('password.edit')],
      $u->role === 'operator' ? ['Hapus Data', route('hapusdata.form'), request()->is('utility/hapus-data*')] : null,
    ]))],
  ];

  // Dropdown profil ARKAS 4 hanya berisi dua item: Data Penanggung Jawab & Keluar.
  // Pengaturan data sekolah dipindah ke halaman Data Penanggung Jawab agar
  // dropdown tetap identik dengan ARKAS.

@endphp

@include('layouts._shell')

@stack('scripts')
@include('layouts._interaksi')
@include('layouts._cetak-preview')
</body>
</html>
