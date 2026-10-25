<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="SiapBOSP — Sistem Akuntansi Dana BOSP multi sekolah: perencanaan, pembukuan, dan pelaporan.">
<link rel="icon" href="/favicon.ico">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#4f46e5">
<link rel="apple-touch-icon" href="/icons/icon.svg">
<title>{{ $title ?? 'SiapBOSP' }} — SiapBOSP</title>
@fonts
@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@else
    {{-- Fallback darurat bila aset Vite belum dibangun (jalankan: npm run build).
         Gaya komponen app.css dimuat bersama oleh layouts/_gaya-darurat supaya
         halaman login (layouts.guest) juga tetap tampil benar. --}}
    <script nonce="{{ request()->attributes->get('csp_nonce') }}" src="/tailwind.js"></script>
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
<body class="bg-slate-100 text-slate-800">
@php
  $u = auth()->user();
  $tahunAktif = (int) session('tahun', now()->year);
@endphp

{{-- Definisi grup menu sekolah (label, rute, status aktif) --}}
@php
  $menuSekolah = [
    ['label'=>'🏫 Data Sekolah','open'=>request()->is('utility/profil*')||request()->is('utility/penandatangan*')||request()->is('data-sekolah*'),'items'=>[
      ['Profil Sekolah', route('profil.edit'), request()->is('utility/profil*')],
      ['Penanggung Jawab', route('penandatangan'), request()->is('utility/penandatangan*')],
      ['Tahun Anggaran', route('datasekolah.tahun'), request()->is('data-sekolah/tahun*')],
      ['Sumber Dana', route('datasekolah.sumber-dana'), request()->is('data-sekolah/sumber-dana*')],
      ['Status Sinkronisasi ke Pusat', route('datasekolah.sinkronisasi'), request()->is('data-sekolah/sinkronisasi*')],
    ]],
    ['label'=>'📝 Penganggaran','open'=>request()->is('rkas*')||request()->is('perencanaan*')||request()->is('penganggaran*'),'items'=>[
      ['Kertas Kerja (RKAS)', route('rkas.index'), request()->is('rkas*')],
      ['Review & Proporsi Anggaran', route('penganggaran.review'), request()->is('penganggaran/review*')],
      ['Daftar Kegiatan', route('penganggaran.kegiatan'), request()->is('penganggaran/kegiatan*')],
      ['Anggaran Bulanan (ARKAS)', route('penganggaran.anggaran-bulanan'), request()->is('penganggaran/anggaran-bulanan')],
      ['🧭 PBD → RKM → RKT (Perencanaan)', route('perencanaan.index'), request()->routeIs('perencanaan.index')],
    ]],
    ['label'=>'📒 Penatausahaan','open'=>request()->is('penerimaan*')||request()->is('pengeluaran*')||request()->is('pembukuan*')||request()->is('penatausahaan*'),'items'=>[
      ['Buku Kas Umum', route('bku'), request()->routeIs('bku')],
      ['Kas dan Bank', route('kas'), request()->routeIs('kas')],
      ['Penerimaan', '#', false],
      ['Pendapatan', route('pendapatan.index'), request()->is('penerimaan/pendapatan*')],
      ['💵 Tarik Tunai (Bank→Tunai)', route('ambil.index'), request()->is('penerimaan/ambil*')],
      ['💵 Setor Tunai (Tunai→Bank)', route('mutasi.index'), request()->is('penerimaan/mutasi*')],
      ['Pengeluaran & Pajak', '#', false],
      ['Pembelanjaan', route('belanja.index'), request()->is('pengeluaran/belanja*')],
      ['Pajak (Setor & Utang)', route('setorpajak.index'), request()->is('pengeluaran/setor*')],
      ['Buku Pembantu', route('pembukuan.bukuPembantu'), request()->routeIs('pembukuan.bukuPembantu')],
      ['Pelaporan', '#', false],
      ['Penutupan BKU', route('penatausahaan.penutupan-bku'), request()->is('penatausahaan/penutupan-bku')],
      ['📄 SPTJM BOSP', route('pembukuan.sptjm'), request()->routeIs('pembukuan.sptjm')],
    ]],
    ['label'=>'🖨️ Laporan','open'=>request()->is('laporan*')||request()->is('laporan-arkas*'),'items'=>[
      ['LPJ & Tutup Buku per Tahap', route('laporan.tahap'), request()->routeIs('laporan.tahap')],
      ['📦 LPJ per Tahap', route('laporan.lpj'), request()->routeIs('laporan.lpj*')],
      ['🧾 Pajak Triwulan', route('laporan.pajak-tw'), request()->routeIs('laporan.pajak-tw')],
      ['Realisasi per Sumber Dana', route('laporan.realisasi-sumber-dana'), request()->is('laporan/realisasi-sumber-dana')],
      ['🔍 Rekonsiliasi ARKAS', route('laporan.rekonsiliasi'), request()->is('laporan/rekonsiliasi')],
      ['Format ARKAS', '#', false],
      ['Rincian RKAS per Tahap (ARKAS)', route('laporan-arkas.rkas-tahap'), request()->is('laporan-arkas/rkas-tahap')],
      ['Realisasi Anggaran (ARKAS)', route('laporan-arkas.realisasi'), request()->is('laporan-arkas/realisasi')],
      ['Realisasi Penerimaan & Belanja (ARKAS)', route('laporan-arkas.penerimaan-belanja'), request()->is('laporan-arkas/penerimaan-belanja')],
      ['Realisasi Habis Pakai (ARKAS)', route('laporan-arkas.habis-pakai'), request()->is('laporan-arkas/habis-pakai')],
      ['Realisasi Modal (ARKAS)', route('laporan-arkas.modal'), request()->is('laporan-arkas/modal')],
      ['Daftar Kas Umum (ARKAS)', route('laporan-arkas.kas-umum'), request()->is('laporan-arkas/daftar-kas-umum')],
      ['Daftar Nota (ARKAS)', route('laporan-arkas.nota'), request()->is('laporan-arkas/daftar-nota')],
    ]],
    ['label'=>'🗄️ Arsip','open'=>request()->is('arsip*'),'items'=>[
      ['RKAS Disahkan', route('arsip.rkas'), request()->is('arsip/rkas*')],
      ['BKU Ditutup', route('arsip.bku'), request()->is('arsip/bku*')],
      ['Dokumen Pajak', route('arsip.pajak'), request()->is('arsip/pajak*')],
      ['Riwayat Impor', route('arsip.impor'), request()->is('arsip/impor*')],
      ['Riwayat Ekspor', route('arsip.ekspor'), request()->is('arsip/ekspor*')],
    ]],
    ['label'=>'🧩 Data Referensi','open'=>request()->is('referensi*')||request()->is('perencanaan/ssh*'),'items'=>[
      ['Referensi Master', route('referensi'), request()->routeIs('referensi')],
      ['🏷️ Referensi Harga (SSH/SBU)', route('referensi.harga'), request()->is('referensi/harga')],
    ]],
    ['label'=>'⚙️ Pengaturan','open'=>request()->is('pengaturan*')||request()->is('utility*')||request()->is('impor-arkas*')||request()->is('hpp*'),'items'=>array_values(array_filter([
      ['🔌 Koneksi ARKAS', route('pengaturan.koneksi'), request()->is('pengaturan/koneksi*')],
      ['⬆️ Impor Database (ARKAS)', route('pengaturan.impor-database'), request()->is('pengaturan/impor-database')],
      ['🗺️ Pemetaan Data (HPP)', route('pengaturan.pemetaan'), request()->is('pengaturan/pemetaan')],
      ['💾 Backup Data', route('pengaturan.backup'), request()->is('pengaturan/backup*')],
      ['🔎 Deteksi Versi ARKAS', route('pengaturan.versi'), request()->is('pengaturan/deteksi-versi*')],
      ['📋 Log Sistem', route('pengaturan.log'), request()->is('pengaturan/log*')],
      ['🔐 Ganti Password', route('password.edit'), request()->routeIs('password.edit')],
      $u->role === 'operator' ? ['🗑️ Hapus Data', route('hapusdata.form'), request()->is('utility/hapus-data*')] : null,
    ]))],
  ];
@endphp

<div class="nonprint fixed inset-0 z-30 hidden bg-slate-900/50 backdrop-blur-[1px] lg:hidden" id="sidebar-overlay" data-close-sidebar aria-hidden="true"></div>

<div class="flex min-h-screen lg:pl-72">
  {{-- ===== SIDEBAR ===== --}}
  <aside id="sidebar" class="nonprint fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col bg-slate-900 text-slate-300 shadow-xl transition-transform duration-200 lg:translate-x-0" aria-label="Navigasi utama">
    <div class="flex items-center justify-between border-b border-white/10 px-4 py-4">
      <a href="{{ $u->isDinas() ? route('dinas.dashboard') : route('dashboard') }}" class="flex items-center gap-2.5">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-xl shadow-sm">🎓</span>
        <span>
          <span class="block text-[17px] font-bold leading-tight text-white">SiapBOSP</span>
          <span class="block text-[11px] leading-tight text-slate-400">Akuntansi Dana BOSP · ARKAS 4</span>
        </span>
      </a>
      <button type="button" class="rounded-lg p-1.5 text-xl text-slate-400 hover:bg-white/10 hover:text-white lg:hidden" data-sidebar-tutup aria-label="Tutup menu">✕</button>
    </div>

    <nav class="navw flex-1 gap-[2px] overflow-y-auto px-3 py-3" aria-label="Menu">
      @if(!$u->isDinas())
        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'on' : '' }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>📊 Dashboard</a>

        @foreach($menuSekolah as $grp)
        <div class="grp {{ $grp['open'] ? 'open' : '' }}">
          <button type="button" class="menu-btn" data-grup aria-expanded="{{ $grp['open'] ? 'true' : 'false' }}">
            <span>{{ $grp['label'] }}</span><span class="chev">▾</span>
          </button>
          <div class="sub {{ $grp['open'] ? '' : 'hidden' }}">
            @foreach($grp['items'] as [$label, $href, $aktif])
              @if($href === '#')
                <p class="pointer-events-none select-none px-3 pb-1 pt-2 text-[10px] font-bold uppercase tracking-widest text-slate-500">{{ $label }}</p>
              @else
                <a href="{{ $href }}" class="{{ $aktif ? 'on' : '' }}" @if($aktif) aria-current="page" @endif>{{ $label }}</a>
              @endif
            @endforeach
          </div>
        </div>
        @endforeach
      @else
        <p class="px-3 pb-1 pt-2 text-[10px] font-bold uppercase tracking-widest text-slate-500">Wilayah</p>
        <a href="{{ route('dinas.dashboard') }}" class="{{ request()->routeIs('dinas.dashboard') ? 'on' : '' }}">📊 Dashboard Wilayah</a>
        <a href="{{ route('dinas.sekolah') }}" class="{{ request()->routeIs('dinas.sekolah*') ? 'on' : '' }}">🏫 Sekolah</a>
        <a href="{{ route('dinas.alokasi') }}" class="{{ request()->routeIs('dinas.alokasi') ? 'on' : '' }}">💰 Pagu / Alokasi</a>
        <a href="{{ route('dinas.konsolidasi') }}" class="{{ request()->routeIs('dinas.konsolidasi') ? 'on' : '' }}">🗂️ Konsolidasi Wilayah</a>
        <p class="px-3 pb-1 pt-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Verifikasi</p>
        <a href="{{ route('dinas.rkas') }}" class="{{ request()->routeIs('dinas.rkas*') ? 'on' : '' }}">📋 RKAS Masuk</a>
        <a href="{{ route('dinas.tahap') }}" class="{{ request()->routeIs('dinas.tahap') ? 'on' : '' }}">🗓️ Kontrol Tahap</a>
        <p class="px-3 pb-1 pt-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Kelola</p>
        <a href="{{ route('dinas.pengguna') }}" class="{{ request()->routeIs('dinas.pengguna') ? 'on' : '' }}">👥 Pengguna</a>
        <a href="{{ route('dinas.master') }}" class="{{ request()->routeIs('dinas.master*') ? 'on' : '' }}">🧱 Master Data</a>
        <a href="{{ route('referensi') }}" class="{{ request()->routeIs('referensi') ? 'on' : '' }}">🧩 Referensi</a>
        <a href="{{ route('dinas.keamanan') }}" class="{{ request()->routeIs('dinas.keamanan') ? 'on' : '' }}">🛡️ Keamanan</a>
        <a href="{{ route('dinas.aktivitas') }}" class="{{ request()->routeIs('dinas.aktivitas') ? 'on' : '' }}">📋 Log Aktivitas</a>
        <a href="{{ route('dinas.pengumuman') }}" class="{{ request()->routeIs('dinas.pengumuman') ? 'on' : '' }}">📣 Pengumuman</a>
        <a href="{{ route('dinas.backup') }}" data-konfirmasi="Unduh backup database sekarang?">💾 Backup DB</a>
        <a href="{{ route('password.edit') }}" class="{{ request()->routeIs('password.edit') ? 'on' : '' }}">🔐 Ganti Password</a>
      @endif
    </nav>

    <div class="border-t border-white/10 p-3">
      <div class="flex items-center gap-2.5 rounded-xl bg-white/5 px-3 py-2.5">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-600/30 text-sm font-bold text-indigo-200">{{ mb_substr($u->name ?? 'U', 0, 1) }}</span>
        <div class="min-w-0 text-xs">
          <div class="truncate font-semibold text-slate-100">{{ $u->name }}</div>
          <div class="truncate text-slate-400">{{ $u->school->nama ?? 'Dinas Pendidikan' }} · {{ ucfirst($u->role) }}</div>
        </div>
      </div>
    </div>
  </aside>

  {{-- ===== KONTEN ===== --}}
  <div class="flex min-w-0 flex-1 flex-col">
    <header class="nonprint sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
      <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
        <div class="flex min-w-0 items-center gap-3">
          <button type="button" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-lg text-slate-600 hover:bg-slate-50 lg:hidden" data-sidebar-buka aria-label="Buka menu">☰</button>
          <div class="min-w-0">
            <h1 class="truncate text-[15px] font-bold text-slate-800 sm:text-base">{{ $title ?? '' }}</h1>
            @hasSection('subtitle')
              <p class="truncate text-xs text-slate-500">@yield('subtitle')</p>
            @endif
          </div>
        </div>
        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
          @php
            $notifBelum = $u->unreadNotifications()->count();
            $notifList = $u->notifications()->latest()->take(6)->get();
            $pajakIngat = $u->school_id ? (int) \App\Models\KasTax::where('status', 'belum')
                ->whereHas('transaksi', fn ($q) => $q->where('school_id', $u->school_id)->where('type', 'belanja'))->sum('nilai') : 0;
          @endphp
          <details class="relative" data-dropdown>
            <summary class="relative inline-block cursor-pointer select-none list-none rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-lg leading-none hover:bg-slate-50 [&::-webkit-details-marker]:hidden" title="Notifikasi" aria-label="Notifikasi">🔔
              @if($notifBelum)<span class="absolute -right-2 -top-2 min-w-5 rounded-full bg-rose-500 px-1 text-center text-[10px] font-bold leading-5 text-white">{{ $notifBelum }}</span>@endif
            </summary>
            <div class="absolute right-0 z-50 mt-2 max-h-96 w-[19rem] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 text-sm shadow-xl">
              <div class="mb-1 flex items-center justify-between border-b border-slate-100 px-2 py-1.5">
                <b class="text-slate-700">Notifikasi</b>
                @if($notifBelum)
                <form method="post" action="{{ route('notifikasi.bacaSemua') }}">@csrf
                  <button class="text-[11px] font-semibold text-indigo-600 hover:underline">Tandai semua dibaca</button>
                </form>
                @endif
              </div>
              @if($pajakIngat > 0)
                <a href="{{ route('setorpajak.index') }}" class="mb-1 block rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800">
                  ⚠️ Pajak belum disetor <b>{{ rp($pajakIngat) }}</b> — segera setor.
                </a>
              @endif
              @forelse($notifList as $n)
                <a href="{{ route('notifikasi.buka', $n->id) }}" class="block rounded-xl px-2.5 py-2 hover:bg-slate-100 {{ $n->read_at ? '' : 'bg-indigo-50/70' }}">
                  <div class="text-xs font-semibold text-slate-700">{{ $n->data['ikon'] ?? '🔔' }} {{ $n->data['judul'] ?? '' }}</div>
                  <div class="text-[11px] text-slate-500">{{ $n->data['isi'] ?? '' }}</div>
                  <div class="mt-0.5 text-[10px] text-slate-400">{{ $n->created_at->diffForHumans() }}</div>
                </a>
              @empty
                <div class="px-2 py-6 text-center text-xs text-slate-400">Belum ada notifikasi.</div>
              @endforelse
            </div>
          </details>
          <form method="post" action="{{ route('sesi.tahun') }}" class="flex items-center gap-2 text-sm">
            @csrf
            <label for="tahun-anggaran" class="hidden text-slate-500 md:inline">Tahun Anggaran</label>
            <select id="tahun-anggaran" name="tahun" class="inp inp-sm !w-auto" data-kirim-otomatis>
              @for($y = 2023; $y <= 2028; $y++) <option value="{{ $y }}" @selected($tahunAktif === $y)>{{ $y }}</option> @endfor
            </select>
          </form>
          <form method="post" action="{{ route('logout') }}">@csrf
            <button class="btn2 btn-sm !text-rose-600" title="Keluar dari aplikasi">Keluar ⏻</button>
          </form>
        </div>
      </div>
    </header>

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-5 sm:px-6">
      <div class="space-y-5">
        @if(session('ok')) <div class="alert alert-ok nonprint" role="status">✅<span>{{ session('ok') }}</span></div> @endif
        @if(session('warning')) <div class="alert alert-warn nonprint" role="status">⚠️<span>{{ session('warning') }}</span></div> @endif
        @if(session('error')) <div class="alert alert-err nonprint" role="alert">⛔<span>{{ session('error') }}</span></div> @endif
        @if($errors->any())
          <div class="alert alert-err nonprint" role="alert">
            <span>⛔</span>
            <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
          </div>
        @endif
        @yield('content')
      </div>
      <footer class="nonprint mt-8 border-t border-slate-200 pt-4 pb-2 text-center text-[11px] text-slate-400">
        SiapBOSP · Sistem Akuntansi Dana BOSP · Selaras ARKAS 4 v4.3.0 &amp; Permendikdasmen No. 8 Tahun 2026 · TA {{ $tahunAktif }}
      </footer>
    </main>
  </div>
</div>

@stack('scripts')
@include('layouts._interaksi')
@include('layouts._cetak-preview')
</body>
</html>
