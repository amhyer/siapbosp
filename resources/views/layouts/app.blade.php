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
    {{-- Fallback darurat bila aset Vite belum dibangun (jalankan: npm run build) --}}
    <script src="/tailwind.js"></script>
    <style>
      body{font-family:ui-sans-serif,system-ui,sans-serif}
      .inp{width:100%;border:1px solid #cbd5e1;border-radius:.5rem;padding:.45rem .7rem;font-size:.875rem;background:#fff}
      .btn{display:inline-flex;align-items:center;gap:.4rem;background:#4f46e5;color:#fff;font-weight:600;font-size:.85rem;padding:.5rem 1rem;border-radius:.5rem}
      .btn2{background:#fff;color:#334155;border:1px solid #cbd5e1;border-radius:.5rem;padding:.5rem 1rem;font-size:.85rem;font-weight:600}
      .card{background:#fff;border-radius:.8rem;box-shadow:0 1px 3px rgb(15 23 42/.08);padding:1.1rem}
      .tbl{width:100%;border-collapse:collapse;font-size:.85rem}
      .tbl th{background:#f1f5f9;text-align:left;padding:.55rem .7rem}
      .tbl td{padding:.5rem .7rem;border-bottom:1px solid #eef2f7}
      .num{text-align:right;white-space:nowrap}
      .badge{display:inline-block;padding:.1rem .55rem;border-radius:999px;font-size:.72rem;font-weight:600}
    </style>
@endif
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
    ['label'=>'📝 Perencanaan','open'=>request()->is('perencanaan*')||request()->is('rkas*')||request()->is('impor-arkas*')||request()->is('hpp*'),'items'=>[
      ['🧭 PBD → RKM → RKT', route('perencanaan.index'), request()->routeIs('perencanaan.index')],
      ['🏷️ Standar Harga (SSH/SBU)', route('perencanaan.ssh'), request()->routeIs('perencanaan.ssh*')],
      ['RKAS-BOSP (Kertas Kerja)', route('rkas.index'), request()->is('rkas*')],
      ['⬆️ Impor ARKAS', route('impor-arkas.form'), request()->is('impor-arkas*')],
      ['🗺️ HPP Pemetaan', route('hpp.index'), request()->is('hpp*')],
    ]],
    ['label'=>'📥 Penerimaan','open'=>request()->is('penerimaan*'),'items'=>[
      ['Pendapatan', route('pendapatan.index'), request()->is('penerimaan/pendapatan*')],
      ['💵 Tarik Tunai (Bank→Tunai)', route('ambil.index'), request()->is('penerimaan/ambil*')],
      ['💵 Setor Tunai (Tunai→Bank)', route('mutasi.index'), request()->is('penerimaan/mutasi*')],
    ]],
    ['label'=>'📤 Pengeluaran','open'=>request()->is('pengeluaran*'),'items'=>[
      ['Belanja', route('belanja.index'), request()->is('pengeluaran/belanja*')],
      ['Setor Pajak / Utang', route('setorpajak.index'), request()->is('pengeluaran/setor*')],
    ]],
    ['label'=>'📒 Pembukuan','open'=>request()->is('pembukuan*')||request()->is('rekonsiliasi*'),'items'=>[
      ['BKU Bulanan', route('bku'), request()->routeIs('bku')],
      ['Kas Tunai & Bank', route('kas'), request()->routeIs('kas')],
      ['📄 SPTJM BOSP', route('pembukuan.sptjm'), request()->routeIs('pembukuan.sptjm')],
      ['🔍 Rekonsiliasi ARKAS', route('rekonsiliasi.form'), request()->is('rekonsiliasi*')],
    ]],
    ['label'=>'🖨️ Laporan','open'=>request()->is('laporan*'),'items'=>[
      ['LPJ & Tutup Buku per Tahap', route('laporan.tahap'), request()->routeIs('laporan.tahap')],
      ['📦 LPJ per Tahap', route('laporan.lpj'), request()->routeIs('laporan.lpj*')],
      ['🧾 Pajak Triwulan', route('laporan.pajak-tw'), request()->routeIs('laporan.pajak-tw')],
      ['Format ARKAS', '#', false],
      ['RKAS Bulanan (ARKAS)', route('laporan-arkas.rkas-bulanan'), request()->is('laporan-arkas/rkas-bulanan')],
      ['Rincian RKAS per Tahap (ARKAS)', route('laporan-arkas.rkas-tahap'), request()->is('laporan-arkas/rkas-tahap')],
      ['Realisasi Anggaran (ARKAS)', route('laporan-arkas.realisasi'), request()->is('laporan-arkas/realisasi')],
      ['Realisasi Penerimaan & Belanja (ARKAS)', route('laporan-arkas.penerimaan-belanja'), request()->is('laporan-arkas/penerimaan-belanja')],
      ['Realisasi Habis Pakai (ARKAS)', route('laporan-arkas.habis-pakai'), request()->is('laporan-arkas/habis-pakai')],
      ['Realisasi Modal (ARKAS)', route('laporan-arkas.modal'), request()->is('laporan-arkas/modal')],
      ['Daftar Kas Umum (ARKAS)', route('laporan-arkas.kas-umum'), request()->is('laporan-arkas/daftar-kas-umum')],
      ['Daftar Nota (ARKAS)', route('laporan-arkas.nota'), request()->is('laporan-arkas/daftar-nota')],
      ['Register Penutupan Kas (ARKAS)', route('laporan-arkas.penutupan-kas'), request()->is('laporan-arkas/penutupan-kas')],
    ]],
    ['label'=>'⚙️ Utility','open'=>request()->is('utility*'),'items'=>array_values(array_filter([
      ['Profil Sekolah', route('profil.edit'), request()->is('utility/profil*')],
      ['Penandatangan', route('penandatangan'), request()->is('utility/penandatangan*')],
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
      <button type="button" class="rounded-lg p-1.5 text-xl text-slate-400 hover:bg-white/10 hover:text-white lg:hidden" onclick="toggleSidebar(false)" aria-label="Tutup menu">✕</button>
    </div>

    <nav class="navw flex-1 gap-[2px] overflow-y-auto px-3 py-3" aria-label="Menu">
      @if(!$u->isDinas())
        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'on' : '' }}">📊 Dashboard</a>

        @foreach($menuSekolah as $grp)
        <div class="grp {{ $grp['open'] ? 'open' : '' }}">
          <button type="button" class="menu-btn" onclick="toggleGrp(this)" aria-expanded="{{ $grp['open'] ? 'true' : 'false' }}">
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

        <a href="{{ route('referensi') }}" class="{{ request()->routeIs('referensi') ? 'on' : '' }}">🧩 Referensi</a>
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
        <a href="{{ route('dinas.backup') }}" onclick="return confirm('Unduh backup database sekarang?')">💾 Backup DB</a>
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
          <button type="button" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-lg text-slate-600 hover:bg-slate-50 lg:hidden" onclick="toggleSidebar()" aria-label="Buka menu">☰</button>
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
            <select id="tahun-anggaran" name="tahun" class="inp inp-sm !w-auto" onchange="this.form.submit()">
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
      <div class="nonprint space-y-5">
        @if(session('ok')) <div class="alert alert-ok" role="status">✅<span>{{ session('ok') }}</span></div> @endif
        @if(session('warning')) <div class="alert alert-warn" role="status">⚠️<span>{{ session('warning') }}</span></div> @endif
        @if(session('error')) <div class="alert alert-err" role="alert">⛔<span>{{ session('error') }}</span></div> @endif
        @if($errors->any())
          <div class="alert alert-err" role="alert">
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
</body>
</html>
