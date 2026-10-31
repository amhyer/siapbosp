{{-- Kerangka aplikasi yang meniru ARKAS 4.3:
     topbar 54px (logo + nama sekolah), sidebar 260px biru #1e3a8a,
     tiga area kerja, empat utilitas bawah, dan mode full-screen tanpa sidebar
     pada halaman Detail RKAS, Review Kertas Kerja, BKU Bulanan, & Pratinjau Cetak. --}}
@php
  $fullScreen = $fullScreen ?? false;
  $hidePageHead = $hidePageHead ?? false;
  $halamanUtamaArkas = request()->routeIs('rkas.index', 'penatausahaan.index', 'arsip.index');
  $namaSekolahTop = $u->school->nama ?? ($u->name ?? 'Dinas Pendidikan');
  $kataSekolah = preg_split('/\s+/', trim($namaSekolahTop)) ?: [];
  $inisialAvatar = count($kataSekolah) >= 2
      ? mb_strtoupper(mb_substr($kataSekolah[0], 0, 1).mb_substr($kataSekolah[1], 0, 1))
      : mb_strtoupper(mb_substr($namaSekolahTop, 0, 2));
@endphp

<div class="nonprint fixed inset-0 z-30 hidden bg-slate-900/50 lg:hidden" id="sidebar-overlay" data-close-sidebar aria-hidden="true"></div>

<header class="arkas-topbar nonprint {{ $fullScreen ? 'sr-only' : '' }}">
  <div class="flex min-w-0 items-center gap-2.5">
    <button type="button" class="arkas-icon-btn lg:hidden" data-sidebar-buka aria-label="Buka menu">
      <x-icon name="menu" class="h-5 w-5"/>
    </button>
    <a href="{{ $u->isDinas() ? route('dinas.dashboard') : route('dashboard') }}" class="flex min-w-0 items-center gap-2.5 no-underline">
      <svg class="h-8 w-8 shrink-0" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M5 26L13.5 6H18.5L27 26H21.5L19.5 20.5H12.5L10.5 26H5ZM13.8 16.5H18.2L16 10.5L13.8 16.5Z" fill="#1e3a8a"/>
        <path d="M12.5 20.5L16 10.5L19.5 20.5H12.5Z" fill="#3b82f6"/>
        <circle cx="23.5" cy="8.5" r="3" fill="#f59e0b"/>
        <path d="M4 25.5C8 18 13 8 23.5 8.5" stroke="#0b5fef" stroke-width="2.5" stroke-linecap="round"/>
      </svg>
      <span class="truncate text-[18px] font-bold tracking-tight text-[#2f3031]">ARKAS</span>
      <span class="sr-only">SiapBOSP v4.3.0</span>
    </a>
  </div>

  <div class="flex shrink-0 items-center">
    <details class="relative" data-dropdown>
      <summary class="arkas-school-btn list-none [&amp;::-webkit-details-marker]:hidden" title="Data sekolah &amp; keluar">
        <span class="arkas-avatar">{{ $inisialAvatar }}</span>
        <span class="hidden max-w-[22rem] truncate text-[15px] font-semibold text-[#2f3031] sm:block">{{ $u->school->nama ?? 'Dinas Pendidikan' }}</span>
        <x-icon name="chevron-down" class="h-5 w-5 text-[#2f3031]"/>
      </summary>
      <div class="arkas-dropdown">
        @if(!$u->isDinas())
          <a href="{{ route('penandatangan') }}" data-modal-buka="modal-penanggung-jawab">
            <x-icon name="user"/>
            <span>Data Penanggung Jawab</span>
          </a>
        @else
          <a href="{{ route('dinas.dashboard') }}">
            <x-icon name="chart"/>
            <span>Dashboard Wilayah</span>
          </a>
        @endif
        <form method="post" action="{{ route('logout') }}">
          @csrf
          <button type="submit">
            <x-icon name="logout"/>
            <span>Keluar</span>
          </button>
        </form>
      </div>
    </details>
  </div>
</header>

<div class="flex {{ $fullScreen ? 'min-h-screen bg-white' : 'min-h-[calc(100vh-54px)]' }}">
  <aside id="sidebar"
         class="arkas-sidebar nonprint {{ $fullScreen ? 'sr-only' : 'fixed inset-y-0 left-0 z-40 flex w-[230px] -translate-x-full flex-col shadow-xl transition-transform duration-200 lg:sticky lg:top-[54px] lg:h-[calc(100vh-54px)] lg:translate-x-0 lg:shadow-none' }}"
         aria-label="Navigasi utama">
    <button type="button" class="absolute right-2 top-2 rounded p-1.5 text-white/70 hover:bg-white/10 hover:text-white lg:hidden" data-sidebar-tutup aria-label="Tutup menu">
      <x-icon name="x" class="h-5 w-5"/>
    </button>

    <nav class="navw flex-1 overflow-y-auto px-[18px] pb-4 pt-[36px]" aria-label="Menu">
      @if(!$u->isDinas())
        <div class="navw-utama">
          @foreach($menuUtama as $grp)
            <div class="grp">
              <div class="navw-area-row">
                <a href="{{ $grp['href'] ?? '#' }}" class="navw-area {{ !empty($grp['aktif']) ? 'on' : '' }}" @if(!empty($grp['aktif'])) aria-current="page" @endif>{{ $grp['label'] }}</a>
                @if(!empty($grp['items']))
                <button type="button" class="navw-chev sr-only" data-grup aria-expanded="false" aria-label="Buka submenu {{ $grp['label'] }}">
                  <x-icon name="chevron-down" class="h-4 w-4"/>
                </button>
                @endif
              </div>
              @if(!empty($grp['items']))
              <div class="navw-sub hidden">
                @foreach($grp['items'] as [$label, $href, $aktif])
                  @if($href === '#')
                    <p>{{ $label }}</p>
                  @else
                    <a href="{{ $href }}" class="{{ $aktif ? 'on' : '' }}" @if($aktif) aria-current="page" @endif>{{ $label }}</a>
                  @endif
                @endforeach
              </div>
              @endif
            </div>
          @endforeach
        </div>

        <div class="navw-sep" role="presentation"><span class="sr-only">Menu Lain</span></div>
        <a href="{{ route('dashboard') }}" class="navw-extra sr-only {{ request()->routeIs('dashboard') ? 'on' : '' }}">Dashboard</a>

        @foreach($menuTambahan as $grp)
          @php $tampilGrupSidebar = ($grp['label'] === 'Laporan'); @endphp
          <div class="grp navw-group {{ $tampilGrupSidebar ? '' : 'sr-only' }} {{ $grp['open'] ? 'open' : '' }}">
            <button type="button" class="menu-btn" data-grup aria-expanded="{{ $grp['open'] ? 'true' : 'false' }}">
              <span>{{ $grp['label'] }}</span>
              <x-icon name="chevron-down" class="chev"/>
            </button>
            <div class="navw-sub {{ $grp['open'] ? '' : 'hidden' }}">
              @foreach($grp['items'] as [$label, $href, $aktif])
                @if($href === '#')
                  <p>{{ $label }}</p>
                @else
                  <a href="{{ $href }}" class="{{ $aktif ? 'on' : '' }}" @if($aktif) aria-current="page" @endif>{{ $label }}</a>
                @endif
              @endforeach
            </div>
          </div>
        @endforeach
      @else
        <p class="navw-sep"><span>Wilayah</span></p>
        <a href="{{ route('dinas.dashboard') }}" class="navw-area {{ request()->routeIs('dinas.dashboard') ? 'on' : '' }}">Dashboard Wilayah</a>
        <a href="{{ route('dinas.sekolah') }}" class="navw-area {{ request()->routeIs('dinas.sekolah*') ? 'on' : '' }}">Sekolah</a>
        <a href="{{ route('dinas.alokasi') }}" class="navw-area {{ request()->routeIs('dinas.alokasi') ? 'on' : '' }}">Pagu / Alokasi</a>
        <a href="{{ route('dinas.konsolidasi') }}" class="navw-area {{ request()->routeIs('dinas.konsolidasi') ? 'on' : '' }}">Konsolidasi Wilayah</a>
        <p class="navw-sep"><span>Verifikasi</span></p>
        <a href="{{ route('dinas.rkas') }}" class="navw-area {{ request()->routeIs('dinas.rkas*') ? 'on' : '' }}">RKAS Masuk</a>
        <a href="{{ route('dinas.tahap') }}" class="navw-area {{ request()->routeIs('dinas.tahap') ? 'on' : '' }}">Kontrol Tahap</a>
        <p class="navw-sep"><span>Kelola</span></p>
        <a href="{{ route('dinas.pengguna') }}" class="navw-area {{ request()->routeIs('dinas.pengguna') ? 'on' : '' }}">Pengguna</a>
        <a href="{{ route('dinas.master') }}" class="navw-area {{ request()->routeIs('dinas.master*') ? 'on' : '' }}">Master Data</a>
        <a href="{{ route('referensi') }}" class="navw-area {{ request()->routeIs('referensi') ? 'on' : '' }}">Referensi</a>
        <a href="{{ route('dinas.keamanan') }}" class="navw-area {{ request()->routeIs('dinas.keamanan') ? 'on' : '' }}">Keamanan</a>
        <a href="{{ route('dinas.aktivitas') }}" class="navw-area {{ request()->routeIs('dinas.aktivitas') ? 'on' : '' }}">Log Aktivitas</a>
        <a href="{{ route('dinas.pengumuman') }}" class="navw-area {{ request()->routeIs('dinas.pengumuman') ? 'on' : '' }}">Pengumuman</a>
        <a href="{{ route('dinas.backup') }}" class="navw-area" data-konfirmasi="Unduh backup database sekarang?">Backup DB</a>
        <a href="{{ route('password.edit') }}" class="navw-area {{ request()->routeIs('password.edit') ? 'on' : '' }}">Ganti Password</a>
      @endif
    </nav>

    <div class="px-[18px] pb-5 pt-3">
      @if(!$u->isDinas() && \App\Services\TarikArkasService::tersedia())
        <form method="post" action="{{ route('tarik-arkas.tarik') }}" class="mb-2" data-tombol-sibuk="⏳ Menarik &amp; Sinkron ARKAS…" data-konfirmasi="Tarik seluruh data dari aplikasi ARKAS Desktop ke aplikasi ini dan sinkronkan otomatis ke website sekarang? Pastikan aplikasi ARKAS sedang ditutup.">
          @csrf
          <input type="hidden" name="tahun" value="{{ $tahunAktif }}">
          <button type="submit" class="navw-util w-full text-left font-semibold text-white">
            <x-icon name="refresh"/>
            <span>Tarik Data dari ARKAS</span>
          </button>
        </form>
      @endif
      <a href="{{ route('bantuan') }}#versi"
         class="sr-only"
         title="SiapBOSP {{ config('app.version', 'v4.3.0') }} — selaras ARKAS 4 v{{ config('app.arkas_acuan', '4.3.0') }}. Klik untuk versi &amp; pembaruan.">
        ⟳ {{ config('app.version', 'v4.3.0') }}
      </a>
      <p class="sr-only">Bantuan &amp; Panduan</p>
      <a href="{{ route('bantuan') }}#tutorial" class="navw-util {{ request()->routeIs('bantuan') ? 'on' : '' }}">
        <x-icon name="info"/>
        <span>Tutorial</span>
      </a>
      <a href="{{ route('bantuan') }}#faq" class="navw-util">
        <x-icon name="chat"/>
        <span>Pertanyaan Umum</span>
      </a>
      <a href="{{ route('bantuan') }}#kontak" class="navw-util">
        <x-icon name="help"/>
        <span>Hubungi Kami</span>
      </a>
      <a href="{{ route('pengaturan.versi') }}" class="navw-util {{ request()->is('pengaturan/deteksi-versi*') ? 'on' : '' }}">
        <x-icon name="refresh"/>
        <span>Perbarui Aplikasi</span>
      </a>
    </div>
  </aside>

  <div class="arkas-content min-w-0 flex-1 {{ $fullScreen ? '!bg-white !min-h-screen' : '' }}">
    <main class="arkas-main {{ $fullScreen ? '!max-w-[1440px] !px-6 !py-5' : '' }}">
      <div class="arkas-page-head nonprint {{ ($fullScreen || $hidePageHead) ? 'sr-only' : '' }}">
        <div class="min-w-0">
          <h1 class="arkas-page-title">{{ $title ?? '' }}</h1>
          @hasSection('subtitle')
            <p class="arkas-page-sub">@yield('subtitle')</p>
          @endif
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
          <form method="post" action="{{ route('sesi.tahun') }}" class="flex items-center gap-1.5">
            @csrf
            <label for="tahun-anggaran" class="text-[11px] font-semibold text-[#65696c]">TA</label>
            <select id="tahun-anggaran" name="tahun" class="arkas-year" data-kirim-otomatis aria-label="Tahun Anggaran">
              @for($y = min(2023, (int) $tahunAktif); $y <= max(2028, (int) $tahunAktif); $y++) <option value="{{ $y }}" @selected($tahunAktif === $y)>{{ $y }}</option> @endfor
            </select>
          </form>

          @php
            $notifBelum = $u->unreadNotifications()->count();
            $notifList = $u->notifications()->latest()->take(6)->get();
            $pajakIngat = $u->school_id ? (int) \App\Models\KasTax::where('status', 'belum')
                ->whereHas('transaksi', fn ($q) => $q->where('school_id', $u->school_id)->where('type', 'belanja'))->sum('nilai') : 0;
          @endphp

          @if($notifBelum > 0 || $notifList->isNotEmpty() || $pajakIngat > 0)
            <details class="relative" data-dropdown>
              <summary class="arkas-icon-btn relative list-none [&amp;::-webkit-details-marker]:hidden" title="Notifikasi" aria-label="Notifikasi">
                <x-icon name="bell" class="h-5 w-5"/>
                @if($notifBelum)
                  <span class="absolute -right-2 -top-2 min-w-5 rounded-full bg-[#e02d38] px-1 text-center text-[10px] font-bold leading-5 text-white">{{ $notifBelum }}</span>
                @endif
              </summary>
              <div class="absolute right-0 z-50 mt-1 max-h-96 w-[19rem] overflow-y-auto rounded-b border border-[#d9dbdd] bg-white p-2 text-sm shadow-[0_8px_20px_rgba(37,40,43,0.2)]">
                <div class="mb-1 flex items-center justify-between border-b border-[#efefef] px-2 py-1.5">
                  <b class="text-[#2f3031]">Notifikasi</b>
                  @if($notifBelum)
                    <form method="post" action="{{ route('notifikasi.bacaSemua') }}">@csrf
                      <button class="text-[11px] font-semibold text-[#0b5fef] hover:underline">Tandai semua dibaca</button>
                    </form>
                  @endif
                </div>
                @if($pajakIngat > 0)
                  <a href="{{ route('setorpajak.index') }}" class="mb-1 block rounded border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800">
                    Pajak belum disetor <b>{{ rp($pajakIngat) }}</b> — segera setor.
                  </a>
                @endif
                @forelse($notifList as $n)
                  <a href="{{ route('notifikasi.buka', $n->id) }}" class="block rounded px-2.5 py-2 hover:bg-[#f5f5f5] {{ $n->read_at ? '' : 'bg-[#f6fafe]' }}">
                    <div class="text-xs font-semibold text-[#2f3031]">{{ $n->data['judul'] ?? '' }}</div>
                    <div class="text-[11px] text-[#65696c]">{{ $n->data['isi'] ?? '' }}</div>
                    <div class="mt-0.5 text-[10px] text-[#808489]">{{ $n->created_at->diffForHumans() }}</div>
                  </a>
                @empty
                  <div class="px-2 py-6 text-center text-xs text-[#808489]">Belum ada notifikasi.</div>
                @endforelse
              </div>
            </details>
          @endif

          @yield('actions')
        </div>
      </div>

      <div class="space-y-5">
        @if(session('ok'))
          <div class="alert alert-ok nonprint" role="status"><x-icon name="check-circle" class="h-5 w-5 shrink-0"/><span>{{ session('ok') }}</span></div>
        @endif
        @if(session('warning'))
          <div class="alert alert-warn nonprint" role="status"><x-icon name="alert" class="h-5 w-5 shrink-0"/><span>{{ session('warning') }}</span></div>
        @endif
        @if(session('error'))
          <div class="alert alert-err nonprint" role="alert"><x-icon name="x-circle" class="h-5 w-5 shrink-0"/><span>{{ session('error') }}</span></div>
        @endif
        @if($errors->any())
          <div class="alert alert-err nonprint" role="alert">
            <x-icon name="x-circle" class="h-5 w-5 shrink-0"/>
            <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
          </div>
        @endif

        @yield('content')
      </div>

      <footer class="nonprint mt-8 border-t border-[#d9dbdd] pb-2 pt-4 text-center text-[11px] text-[#808489]">
        SiapBOSP · Sistem Akuntansi Dana BOSP · Selaras ARKAS 4 v{{ config('app.arkas_acuan', '4.3.0') }} &amp; Permendikdasmen No. 8 Tahun 2026 · TA {{ $tahunAktif }}
      </footer>
      <div class="cetak-kaki">
        Dicetak dari <b>SiapBOSP {{ config('app.version', 'v4.3.0') }}</b> — bukan dokumen resmi ARKAS ·
        {{ now()->format('d/m/Y H:i') }} · {{ $u->school->nama ?? 'Dinas Pendidikan' }}
      </div>
    </main>
  </div>
</div>

@if(!$u->isDinas() && $u->school)
  @php
    $schoolObj = $u->school;
    $kepsekObj = $schoolObj->penandatangans()->where('jabatan', 'Kepala Sekolah')->first();
    $bendaharaObj = $schoolObj->penandatangans()->where('jabatan', 'Bendahara')->first();
    $komiteNama = $schoolObj->nama_komite ?? $schoolObj->penandatangans()->where('jabatan', 'Komite Sekolah')->value('nama');
    $komiteEmail = $schoolObj->email_komite;
    $akhirJabatan = (int) ($schoolObj->akhir_jabatan_bulan ?: 12);
  @endphp
  <div id="modal-penanggung-jawab" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm hidden nonprint" role="dialog" aria-modal="true" aria-labelledby="modal-pj-title">
    <div class="w-full max-w-4xl rounded-2xl bg-white p-7 shadow-2xl relative">
      <div class="mb-5">
        <h2 id="modal-pj-title" class="text-xl font-bold text-slate-800">Data Penanggung Jawab</h2>
        <p class="text-[13px] text-slate-500 mt-1">Anda dapat memasukkan data penanggung jawab yang berbeda untuk periode waktu yang berbeda.</p>
      </div>

      <form method="post" action="{{ route('penandatangan.penanggung-jawab') }}">
        @csrf
        <div class="mb-5 max-w-[160px]">
          <select name="tahun" class="inp font-semibold text-slate-800 text-sm">
            <option value="{{ $tahunAktif }}">{{ $tahunAktif }}</option>
          </select>
        </div>

        <div class="rounded-xl border border-slate-200 p-5 mb-6">
          <div class="flex flex-wrap items-center justify-between gap-2 mb-5 pb-3 border-b border-slate-100">
            <span class="font-bold text-slate-800 text-sm">Periode 1</span>
            <div class="flex items-center gap-2 text-xs text-slate-600">
              <span class="font-medium">Bulan Awal</span>
              <input type="text" value="Januari" disabled class="w-24 bg-slate-100 text-slate-600 rounded px-2.5 py-1 text-center font-medium border border-slate-200">
              <span class="text-slate-400">—</span>
              <span class="font-medium">Bulan Akhir</span>
              <select name="akhir_jabatan_bulan" class="w-28 bg-white border border-slate-300 rounded px-2.5 py-1 font-medium text-slate-700">
                <option value="12" @selected($akhirJabatan === 12)>Desember</option>
                <option value="11" @selected($akhirJabatan === 11)>November</option>
                <option value="10" @selected($akhirJabatan === 10)>Oktober</option>
                <option value="9" @selected($akhirJabatan === 9)>September</option>
                <option value="8" @selected($akhirJabatan === 8)>Agustus</option>
                <option value="7" @selected($akhirJabatan === 7)>Juli</option>
                <option value="6" @selected($akhirJabatan === 6)>Juni</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="space-y-4">
              <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Kepala Sekolah</label>
                <input type="text" name="nama_kepsek" value="{{ $kepsekObj?->nama ?? '' }}" required class="inp text-sm" placeholder="Nama Kepala Sekolah">
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">NIP Kepala Sekolah</label>
                <input type="text" name="nip_kepsek" value="{{ $kepsekObj?->nip ?? '' }}" class="inp text-sm" placeholder="19811207.200701.2.013">
              </div>
            </div>

            <div class="space-y-4">
              <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Bendahara</label>
                <input type="text" name="nama_bendahara" value="{{ $bendaharaObj?->nama ?? '' }}" required class="inp text-sm" placeholder="Nama Bendahara">
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">NIP Bendahara</label>
                <input type="text" name="nip_bendahara" value="{{ $bendaharaObj?->nip ?? '' }}" class="inp text-sm" placeholder="19970812.202421.2.017">
              </div>
            </div>

            <div class="space-y-4">
              <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Komite</label>
                <input type="text" name="nama_komite" value="{{ $komiteNama ?? '' }}" class="inp text-sm" placeholder="Nama Komite">
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Email Komite</label>
                <input type="email" name="email_komite" value="{{ $komiteEmail ?? '' }}" class="inp text-sm" placeholder="komite@sekolah.demo">
              </div>
            </div>
          </div>
        </div>

        <div class="flex flex-col items-end gap-1.5">
          <div class="flex items-center gap-3">
            <button type="button" data-modal-tutup="modal-penanggung-jawab" class="btn2 px-5 py-2 text-sm font-semibold">
              Batal
            </button>
            <button type="submit" class="btn px-6 py-2 text-sm font-semibold bg-[#2c70e5] hover:bg-blue-700 text-white">
              Perbarui
            </button>
          </div>
          <span class="text-[11px] text-[#0b5fef] font-medium">"Perbarui" membutuhkan koneksi internet</span>
        </div>
      </form>
    </div>
  </div>
@endif
