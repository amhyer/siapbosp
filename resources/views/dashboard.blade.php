@extends('layouts.app', ['title' => 'Dashboard'])
@section('subtitle', 'Ringkasan kas, serapan, tenggat BOSP '.$tahun.', dan aktivitas tahun '.$tahun)

@section('actions')
  <a href="{{ route('belanja.cetak-batch', ['bulan' => $bulanAktifKey]) }}" target="_blank" class="btn !bg-indigo-600 hover:!bg-indigo-700 text-white font-bold btn-sm shadow-xs">
    <x-icon name="print" class="h-4 w-4"/> Cetak SPJ Bulan Ini (1-Klik)
  </a>
  <a href="{{ route('penatausahaan.index') }}" class="btn2 btn-sm">
    <x-icon name="book" class="h-4 w-4"/> BKU Sekolah
  </a>
  <a href="{{ route('laporan.format') }}" class="btn2 btn-sm">
    <x-icon name="folder" class="h-4 w-4"/> Format Laporan
  </a>
  <a href="{{ route('laporan.tahap') }}" data-cetak="LPJ per Tahap" class="btn2 btn-sm">
    <x-icon name="print" class="h-4 w-4"/> LPJ per Tahap
  </a>
@endsection

@section('content')
{{-- ============ 1 ATURAN EMAS BENDAHARA (MINDSET YANG JELAS & BEBAS BINGUNG) ============ --}}
<div class="card mb-4 border-l-4 border-l-[#0b5fef] bg-gradient-to-r from-blue-50/90 via-indigo-50/40 to-white shadow-xs p-4">
  <div class="flex items-start gap-3">
    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#0b5fef] text-white shadow-xs">
      <x-icon name="info" class="h-5 w-5"/>
    </div>
    <div class="flex-1 min-w-0">
      <div class="flex flex-wrap items-center gap-2">
        <span class="rounded bg-blue-100 px-2 py-0.5 text-[11px] font-extrabold uppercase tracking-wider text-blue-800">1 Aturan Emas Bendahara</span>
        <span class="text-xs text-slate-500 font-medium">Bekerja Tenang Tanpa Input Ganda</span>
      </div>
      <p class="mt-1 text-sm sm:text-base font-bold text-slate-900 leading-snug">
        &ldquo;Bapak/Ibu Bendahara tetap bekerja normal di ARKAS 4. Buka SiapBOSP hanya untuk 3 hal: Tarik Data ➔ Cetak Dokumen SPJ/Kwitansi ➔ Kirim ke Dinas.&rdquo;
      </p>
      <p class="mt-1 text-xs text-slate-600 leading-relaxed">
        <strong class="text-emerald-700 font-semibold">Anda tidak perlu menginput ulang belanja dari nol.</strong> Data pembukuan dari ARKAS di laptop ini otomatis terbaca, berkas kuitansi &amp; SPJ siap dicetak rapi, dan laporan langsung dikirim ke Dinas Pendidikan.
      </p>
    </div>
  </div>
</div>

{{-- ============ DASHBOARD "3 LANGKAH UTAMA" (ACTION CARDS BESAR) ============ --}}
<div class="card mb-5 border border-slate-200 shadow-xs p-4 sm:p-5">
  <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-3.5 mb-4">
    <div>
      <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Pusat Aksi Cepat Bendahara</div>
      <h2 class="text-base sm:text-lg font-black text-slate-800 flex items-center gap-2 mt-0.5">
        <span class="inline-block h-3 w-3 rounded-full bg-emerald-500 animate-pulse"></span>
        STATUS BOSP BULAN INI: {{ strtoupper($namaBulanAktif) }}
      </h2>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <a href="{{ route('belanja.cetak-batch', ['bulan' => $bulanAktifKey]) }}" target="_blank" class="btn !bg-indigo-600 hover:!bg-indigo-700 text-white font-bold text-xs py-2 px-3 shadow-xs inline-flex items-center gap-1.5">
        <x-icon name="print" class="h-4 w-4"/>
        <span>Cetak SPJ Bulan Ini (1-Klik)</span>
      </a>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    {{-- LANGKAH 1: TARIK DARI ARKAS --}}
    <div class="flex flex-col justify-between rounded-xl border-2 {{ $terakhirTarikWaktu ? 'border-blue-200 bg-blue-50/40' : 'border-amber-300 bg-amber-50/40' }} p-4 transition-all hover:shadow-sm">
      <div>
        <div class="flex items-center justify-between">
          <span class="rounded-full bg-blue-100 text-blue-700 font-bold px-2 py-0.5 text-[10px] tracking-wider uppercase">Langkah 1</span>
          <span class="text-2xl" role="img" aria-label="Tarik">📥</span>
        </div>
        <h3 class="text-base font-extrabold text-slate-900 mt-2">TARIK DARI ARKAS</h3>
        <div class="mt-3 space-y-1.5 text-xs">
          <div class="text-slate-500">Terakhir ditarik:</div>
          <div class="font-extrabold text-slate-800 text-sm">{{ $terakhirTarikStr }}</div>
          <div class="inline-flex items-center gap-1.5 mt-0.5 text-[11px] font-semibold {{ $terakhirTarikWaktu ? 'text-emerald-700' : 'text-amber-700' }}">
            <span class="h-2 w-2 rounded-full {{ $terakhirTarikWaktu ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
            {{ $terakhirTarikWaktu ? 'Data sudah baru & sinkron' : 'Belum pernah ditarik' }}
          </div>
        </div>
      </div>
      <div class="mt-4 pt-3 border-t border-slate-200">
        <a href="{{ route('pengaturan.sinkronisasi') }}" class="btn w-full justify-center !text-xs !py-2 font-bold shadow-xs">
          <x-icon name="refresh" class="h-3.5 w-3.5 mr-1"/>
          <span>↻ Tarik Ulang dari ARKAS</span>
        </a>
      </div>
    </div>

    {{-- LANGKAH 2: CETAK SPJ & KWITANSI --}}
    <div class="flex flex-col justify-between rounded-xl border-2 {{ $pajakTanpaNtpn > 0 ? 'border-amber-300 bg-amber-50/40' : 'border-indigo-200 bg-indigo-50/40' }} p-4 transition-all hover:shadow-sm">
      <div>
        <div class="flex items-center justify-between">
          <span class="rounded-full bg-indigo-100 text-indigo-700 font-bold px-2 py-0.5 text-[10px] tracking-wider uppercase">Langkah 2</span>
          <span class="text-2xl" role="img" aria-label="Cetak">🖨️</span>
        </div>
        <h3 class="text-base font-extrabold text-slate-900 mt-2">CETAK SPJ &amp; KWITANSI</h3>
        <div class="mt-3 space-y-1.5 text-xs">
          <div class="flex items-center justify-between">
            <span class="text-slate-500">Kuitansi Siap:</span>
            <span class="font-bold text-slate-800 text-sm">{{ $kuitansiBulanIni }} Kuitansi Siap</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-500">Status Pajak:</span>
            @if($pajakTanpaNtpn > 0)
              <span class="font-bold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded text-[11px]">{{ $pajakTanpaNtpn }} belum ada NTPN</span>
            @else
              <span class="font-bold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded text-[11px]">Semua NTPN lengkap ✓</span>
            @endif
          </div>
          <div class="text-[11px] text-slate-400 mt-0.5">
            Total transaksi belanja TA {{ $tahun }}: {{ $totalKuitansiTahun }} kuitansi
          </div>
        </div>
      </div>
      <div class="mt-4 pt-3 border-t border-slate-200">
        <a href="{{ route('belanja.cetak-batch', ['bulan' => $bulanAktifKey]) }}" target="_blank" class="btn2 w-full justify-center !text-xs !py-2 font-bold shadow-xs">
          <x-icon name="print" class="h-3.5 w-3.5 mr-1"/>
          <span>Buka Berkas Cetak</span>
        </a>
      </div>
    </div>

    {{-- LANGKAH 3: KIRIM KE DINAS --}}
    <div class="flex flex-col justify-between rounded-xl border-2 {{ $totalKuitansiTahun > 0 ? 'border-emerald-300 bg-emerald-50/40' : 'border-slate-200 bg-slate-50/50' }} p-4 transition-all hover:shadow-sm">
      <div>
        <div class="flex items-center justify-between">
          <span class="rounded-full bg-emerald-100 text-emerald-700 font-bold px-2 py-0.5 text-[10px] tracking-wider uppercase">Langkah 3</span>
          <span class="text-2xl" role="img" aria-label="Kirim">🚀</span>
        </div>
        <h3 class="text-base font-extrabold text-slate-900 mt-2">KIRIM KE DINAS</h3>
        <div class="mt-3 space-y-1.5 text-xs">
          <div class="text-slate-500">Status Pelaporan:</div>
          <div class="font-bold text-slate-800 text-sm">
            @if($totalKuitansiTahun > 0)
              Siap Dikirim ({{ $totalKuitansiTahun }} transaksi tercatat)
            @else
              Menunggu Transaksi ARKAS
            @endif
          </div>
          <div class="text-[11px] text-slate-500 mt-0.5">
            Terakhir dikirim: <span class="font-semibold text-slate-700">{{ $terakhirKirimStr }}</span>
          </div>
        </div>
      </div>
      <div class="mt-4 pt-3 border-t border-slate-200">
        <a href="{{ route('pengaturan.sinkronisasi') }}" class="btn !bg-emerald-600 hover:!bg-emerald-700 text-white w-full justify-center !text-xs !py-2 font-bold shadow-xs">
          <x-icon name="check-circle" class="h-3.5 w-3.5 mr-1"/>
          <span>Kirim Laporan Sekarang</span>
        </a>
      </div>
    </div>
  </div>
</div>

{{-- ============ LAMPU INDIKATOR KESIAPAN DOKUMEN SPJ ============ --}}
<div class="card mb-5 border border-slate-200 bg-slate-50/80 p-3.5 sm:p-4">
  <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
      <span>🚥</span> Lampu Indikator Kesiapan Dokumen SPJ
    </h3>
    <span class="text-[11px] text-slate-500">Pemeriksaan otomatis sebelum penutupan kas &amp; pelaporan</span>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
    {{-- Indikator ARKAS --}}
    <div class="flex items-center gap-3 rounded-lg border bg-white p-3 shadow-2xs">
      <span class="flex h-3.5 w-3.5 shrink-0 rounded-full {{ $terakhirTarikWaktu ? 'bg-emerald-500 ring-4 ring-emerald-100' : 'bg-amber-400 ring-4 ring-amber-100' }}"></span>
      <div class="min-w-0">
        <div class="text-xs font-bold text-slate-800">ARKAS</div>
        <div class="text-[11px] text-slate-500 truncate">
          {{ $terakhirTarikWaktu ? 'Data sudah sinkron dengan versi terbaru' : 'Belum ditarik dari aplikasi ARKAS 4' }}
        </div>
      </div>
    </div>

    {{-- Indikator Pajak --}}
    <div class="flex items-center gap-3 rounded-lg border bg-white p-3 shadow-2xs">
      <span class="flex h-3.5 w-3.5 shrink-0 rounded-full {{ $pajakTanpaNtpn === 0 ? 'bg-emerald-500 ring-4 ring-emerald-100' : 'bg-amber-400 ring-4 ring-amber-100' }}"></span>
      <div class="min-w-0">
        <div class="text-xs font-bold text-slate-800">Pajak</div>
        <div class="text-[11px] text-slate-500 truncate">
          @if($pajakTanpaNtpn === 0)
            Semua potongan belanja ber-NTPN sah
          @else
            {{ $pajakTanpaNtpn }} transaksi belum diinput kode NTPN-nya
          @endif
        </div>
      </div>
    </div>

    {{-- Indikator Saldo Kas --}}
    <div class="flex items-center gap-3 rounded-lg border bg-white p-3 shadow-2xs">
      <span class="flex h-3.5 w-3.5 shrink-0 rounded-full bg-emerald-500 ring-4 ring-emerald-100"></span>
      <div class="min-w-0">
        <div class="text-xs font-bold text-slate-800">Saldo Kas</div>
        <div class="text-[11px] text-slate-500 truncate">
          Kas Tunai ({{ rp($saldo['tunai']) }}) + Bank ({{ rp($saldo['bank']) }}) seimbang
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ============ CTA UNDUH APLIKASI DESKTOP (PUSAT) — penarikan data ARKAS di laptop, bukan di sini ============ --}}
@unless(\App\Http\Controllers\UnduhAplikasiController::isPortable())
<div class="card flex flex-wrap items-center justify-between gap-3 mb-4 border-l-4 border-indigo-600">
  <div>
    <div class="text-xs font-bold uppercase tracking-wider text-indigo-600">Aplikasi Desktop Bendahara</div>
    <div class="text-sm font-bold text-slate-800 mt-0.5">Jalankan SiapBOSP Bendahara langsung di laptop sekolah</div>
    <p class="text-[12px] text-slate-500 mt-0.5">Website pusat menyimpan &amp; memantau data dinas; penarikan data otomatis dari ARKAS 4 dijalankan dari aplikasi desktop di laptop bendahara.</p>
  </div>
  <a href="{{ route('aplikasi-desktop') }}" class="btn">
    <x-icon name="download" class="h-4 w-4"/> Unduh Aplikasi Desktop
  </a>
</div>
@endunless

{{-- ============ WIZARD ALUR TAHAP BOSP (STEPPER) ============ --}}
@if(!empty($alurTahapBosp))
<div class="card bg-gradient-to-r from-blue-50/70 via-indigo-50/50 to-white border border-blue-100 shadow-sm mb-4">
  <div class="flex flex-wrap items-center justify-between gap-2 border-b border-blue-100/80 pb-3 mb-3">
    <div>
      <h2 class="text-sm font-bold text-slate-800 flex items-center gap-2">
        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-600 text-[11px] font-extrabold text-white">★</span>
        Alur Siklus Kerja Dana BOSP (TA {{ $tahun }})
      </h2>
      <p class="text-[12px] text-slate-500 mt-0.5">Panduan langkah terarah: perencanaan ARKAS ➔ SPJ kuitansi ➔ pajak ➔ tutup buku ➔ LPJ &amp; sinkronisasi dinas.</p>
    </div>
    <span class="text-[11px] font-semibold px-2 py-0.5 rounded bg-blue-100 text-blue-700">Panduan Operasional</span>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2.5">
    @foreach($alurTahapBosp as $st)
      @php
        $warnaBg = match($st['status']) {
          'selesai' => 'border-emerald-200 bg-emerald-50/60 text-emerald-800',
          'aktif' => 'border-blue-300 bg-blue-50 text-blue-800 ring-2 ring-blue-400/40',
          'perhatian' => 'border-amber-300 bg-amber-50 text-amber-800 ring-1 ring-amber-300',
          default => 'border-slate-200 bg-white/70 text-slate-600'
        };
        $warnaIcon = match($st['status']) {
          'selesai' => 'bg-emerald-600 text-white',
          'aktif' => 'bg-blue-600 text-white',
          'perhatian' => 'bg-amber-500 text-white',
          default => 'bg-slate-200 text-slate-600'
        };
      @endphp
      <div class="flex flex-col justify-between rounded-lg border p-2.5 transition-all {{ $warnaBg }}">
        <div>
          <div class="flex items-center justify-between gap-1 mb-1.5">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $warnaIcon }}">
              @if($st['status'] === 'selesai')
                ✓
              @else
                {{ $st['urutan'] }}
              @endif
            </span>
            <span class="text-[10px] uppercase font-bold tracking-wider opacity-75">
              Langkah {{ $st['urutan'] }}
            </span>
          </div>
          <div class="font-bold text-xs leading-tight text-slate-900">{{ $st['judul'] }}</div>
          <div class="text-[11px] mt-0.5 text-slate-600 leading-snug">{{ $st['deskripsi'] }}</div>
        </div>
        <div class="mt-2.5 pt-2 border-t border-slate-200/50">
          <a href="{{ $st['url'] }}" class="inline-flex items-center justify-center w-full rounded py-1 px-2 text-[11px] font-semibold {{ $st['status'] === 'aktif' ? 'bg-blue-600 text-white hover:bg-blue-700' : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-50' }} transition">
            {{ $st['aksi'] }} →
          </a>
        </div>
      </div>
    @endforeach
  </div>
</div>
@endif

{{-- ============ PUSAT PERINGATAN DINI (EARLY WARNING SYSTEM) ============ --}}
@if(!empty($earlyWarnings) && $earlyWarnings['total_peringatan'] > 0)
<div class="card border-l-4 border-l-amber-500 bg-amber-50/40 mb-4 p-3.5">
  <div class="flex items-start justify-between gap-2">
    <div class="flex items-center gap-2">
      <span class="flex h-7 w-7 items-center justify-center rounded-full bg-amber-100 text-amber-700 font-bold text-sm">⚠️</span>
      <div>
        <h3 class="font-bold text-sm text-slate-800">Deteksi Validasi &amp; Kepatuhan Dini ({{ $earlyWarnings['total_peringatan'] }} Catatan)</h3>
        <p class="text-xs text-slate-500">Periksa dan tuntaskan catatan berikut sebelum melakukan tutup buku bulanan atau pelaporan ke dinas:</p>
      </div>
    </div>
  </div>
  <div class="mt-3 space-y-2">
    @foreach($earlyWarnings['items'] as $item)
      <div class="flex flex-wrap items-center justify-between gap-2 rounded border border-amber-200/80 bg-white p-2 text-xs">
        <div class="flex-1 min-w-[200px]">
          <div class="font-bold text-slate-800 flex items-center gap-1.5">
            @if($item['tipe'] === 'bahaya')
              <span class="h-2 w-2 rounded-full bg-rose-600"></span>
            @elseif($item['tipe'] === 'peringatan')
              <span class="h-2 w-2 rounded-full bg-amber-500"></span>
            @else
              <span class="h-2 w-2 rounded-full bg-sky-500"></span>
            @endif
            {{ $item['judul'] }}
          </div>
          <div class="text-[11px] text-slate-600 mt-0.5 leading-relaxed">{{ $item['pesan'] }}</div>
        </div>
        @if(!empty($item['aksi_url']) && !empty($item['aksi_label']))
          <a href="{{ $item['aksi_url'] }}" class="btn btn-sm !text-[11px] !py-0.5 !px-2 shrink-0">
            {{ $item['aksi_label'] }}
          </a>
        @endif
      </div>
    @endforeach
  </div>
</div>
@endif

{{-- ============ PENGINGAT TENGGAT PASAL 59 PERMENDIKDASMEN 8/2026 + PAJAK TGL 20 + TUTUP BKU ============ --}}
@if(!empty($statusTenggat) && $statusTenggat['tahap1_terlambat'])
<div class="alert alert-err">
  <x-icon name="alert" class="h-5 w-5 shrink-0"/>
  <div class="flex-1 leading-snug">
    <b>Peringatan Pasal 59 Permendikdasmen No. 8 Tahun 2026:</b>
    <span> {{ $statusTenggat['pesan'] }}</span>
  </div>
  <a href="{{ route('bku', ['bulan' => $tahun . '-06']) }}" class="btn btn-sm !bg-[#e02d38]">Tutup BKU Tahap I</a>
</div>
@elseif(!empty($statusTenggat))
<div class="alert alert-ok">
  <x-icon name="check-circle" class="h-5 w-5 shrink-0"/>
  <span class="flex-1">
    <b>Tenggat Pelaporan BOSP (Pasal 59 Permendikdasmen No. 8/2026):</b> Tahap I maks. <b>31 Juli {{ $tahun }}</b>
    (potongan 2% Ags · 3% Sep · 4% 1–25 Okt) · Tahunan maks. <b>31 Januari {{ $tahun + 1 }}</b>.
  </span>
  @if($statusTenggat['tahap1_tuntas'])
    <span class="badge bg-emerald-100 text-emerald-700">BKU Tahap I tuntas</span>
  @endif
</div>
@endif

@if ($pajakBelum > 0 && now()->day >= 12 && now()->day <= 20)
<div class="alert alert-warn">
  <x-icon name="alert" class="h-5 w-5 shrink-0"/>
  <div class="flex-1 leading-snug">
    <b>Tenggat pajak: setor &amp; lapor paling lambat tgl 20</b>
    <span class="text-[#65696c]"> — {{ rp($pajakBelum) }} belum disetor (bukti lapor = NTPN).</span>
  </div>
  <a href="{{ route('setorpajak.index') }}" class="btn btn-sm">Setor &amp; Lapor Pajak</a>
</div>
@endif

@if ($bkuTerbuka && now()->day >= 24)
<div class="alert alert-warn">
  <x-icon name="book" class="h-5 w-5 shrink-0"/>
  <div class="flex-1 leading-snug">
    <b>Akhir bulan: saatnya tutup BKU.</b>
    <span class="text-[#65696c]"> BKU bulan {{ \Carbon\Carbon::parse($bkuTerbuka.'-01')->translatedFormat('F Y') }} masih terbuka — tutup dulu agar bulan berikutnya bisa mulai.</span>
  </div>
  <a href="{{ route('bku', ['bulan' => $bkuTerbuka]) }}" class="btn btn-sm">Buka BKU</a>
</div>
@endif

{{-- ============ RINGKASAN KEPATUHAN KERTAS KERJA RKAS TERHADAP JUKNIS 2026 ============ --}}
@if(!empty($ringkasanJuknis) && !empty($rkasAktif))
<div class="card flex flex-wrap items-center justify-between gap-3">
  <div>
    <div class="text-xs font-bold uppercase tracking-wider text-indigo-600">Kertas Kerja Aktif · {{ $rkasAktif->labelJenis() }} (Revisi ke-{{ $rkasAktif->revisi_ke }})</div>
    <div class="text-xs text-slate-500 mt-0.5">{{ $ringkasanJuknis['aturan']['label_aturan'] }} · Belum Dianggarkan: <b>{{ rp($ringkasanJuknis['belum_dianggarkan']) }}</b> · Tahap 1: <b>{{ $ringkasanJuknis['tahap1_pct'] }}%</b></div>
  </div>
  <div class="flex flex-wrap gap-2 items-center text-xs">
    @if($ringkasanJuknis['aturan']['mode'] === 'reguler')
      <span class="badge {{ $ringkasanJuknis['proporsi']['buku']['ok'] ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">Buku {{ $ringkasanJuknis['proporsi']['buku']['pct'] }}% (min {{ $ringkasanJuknis['aturan']['buku_min_pct'] }}%)</span>
      <span class="badge {{ $ringkasanJuknis['proporsi']['sarpras']['ok'] ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">Sarpras {{ $ringkasanJuknis['proporsi']['sarpras']['pct'] }}% (maks {{ $ringkasanJuknis['aturan']['sarpras_max_pct'] }}%)</span>
      <span class="badge {{ $ringkasanJuknis['proporsi']['honor']['ok'] ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">Honor {{ $ringkasanJuknis['proporsi']['honor']['pct'] }}% (maks {{ $ringkasanJuknis['aturan']['honor_max_pct'] }}%)</span>
    @endif
    <a href="{{ route('rkas.salinArkas', $rkasAktif) }}" class="btn2 btn-sm">Salin ke ARKAS 4</a>
  </div>
</div>
@endif

{{-- ============ BARIS 1: KARTU UTAMA DENGAN PERSENTASE ============ --}}
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

  <div class="card relative overflow-hidden">
    <div class="absolute left-0 top-0 h-full w-1 bg-emerald-500"></div>
    <div class="text-xs text-slate-500">Saldo Kas Tunai</div>
    <div class="text-xl font-bold text-emerald-700 mt-1">{{ rp($saldo['tunai']) }}</div>
    <div class="mt-2 flex items-center justify-between text-[11px]">
      <span class="text-slate-400">dari total kas</span>
      <span class="badge bg-emerald-100 text-emerald-700">{{ $tunaiPct }}%</span>
    </div>
    <div class="mt-1 h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-emerald-500" style="width:{{ $tunaiPct }}%"></div></div>
  </div>

  <div class="card relative overflow-hidden">
    <div class="absolute left-0 top-0 h-full w-1 bg-sky-500"></div>
    <div class="text-xs text-slate-500">Saldo Kas Bank</div>
    <div class="text-xl font-bold text-sky-700 mt-1">{{ rp($saldo['bank']) }}</div>
    <div class="mt-2 flex items-center justify-between text-[11px]">
      <span class="text-slate-400">dari total kas</span>
      <span class="badge bg-sky-100 text-sky-700">{{ $bankPct }}%</span>
    </div>
    <div class="mt-1 h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-sky-500" style="width:{{ $bankPct }}%"></div></div>
  </div>

  <div class="card relative overflow-hidden">
    <div class="absolute left-0 top-0 h-full w-1 bg-indigo-600"></div>
    <div class="text-xs text-slate-500">Dana Diterima ({{ $tahun }})</div>
    <div class="text-xl font-bold text-indigo-700 mt-1">{{ rp($pendapatan) }}</div>
    <div class="mt-2 flex items-center justify-between text-[11px]">
      <span class="text-slate-400">dari pagu {{ rp($pagu) }}</span>
      <span class="badge bg-indigo-100 text-indigo-700">{{ $diterimaPct }}%</span>
    </div>
    <div class="mt-1 h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-indigo-600" style="width:{{ min(100,$diterimaPct) }}%"></div></div>
  </div>

  <div class="card flex items-center gap-4">
    <div class="w-16 h-16 rounded-full flex items-center justify-center shrink-0"
         style="background:conic-gradient(#0b5fef {{ min(100,$serapan)*3.6 }}deg, #e2e8f0 0deg)">
      <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center text-sm font-bold text-indigo-700">{{ $serapan }}%</div>
    </div>
    <div>
      <div class="text-xs text-slate-500">Serapan Anggaran</div>
      <div class="text-sm font-bold text-slate-700">{{ rp($belanja) }}</div>
      <div class="text-[11px] text-slate-400">Dibelanjakan · bulan ini {{ rp($bulanIni) }}</div>
    </div>
  </div>
</div>

{{-- ============ BARIS 2: PAJAK + REALISASI PER TAHAP ============ --}}
<div class="grid gap-4 lg:grid-cols-3">
  <div class="card">
    <div class="flex items-center justify-between">
      <h3 class="card-title flex items-center gap-2">
        <x-icon name="receipt" class="h-4 w-4 text-slate-500"/> Status Pajak
        <span class="text-[10px] font-normal text-slate-400">(wajib lapor)</span>
      </h3>
      <span class="badge {{ $pajakPct===100.0?'bg-emerald-100 text-emerald-700':'bg-amber-100 text-amber-700' }}">{{ $pajakPct }}% disetor</span>
    </div>
    <div class="mt-3 h-2.5 rounded-full bg-amber-100"><div class="h-2.5 rounded-full bg-emerald-500" style="width:{{ $pajakPct }}%"></div></div>
    <div class="mt-3 grid grid-cols-3 gap-2 text-center text-[11px] text-slate-500">
      <div class="bg-slate-50 rounded py-2"><div class="font-bold text-slate-700 text-sm">{{ rp($pajakTerima) }}</div>Diterima</div>
      <div class="bg-slate-50 rounded py-2"><div class="font-bold text-emerald-700 text-sm">{{ rp($pajakSudah) }}</div>Sudah setor</div>
      <div class="bg-slate-50 rounded py-2"><div class="font-bold {{ $pajakBelum>0?'text-amber-600':'text-slate-400' }} text-sm">{{ rp($pajakBelum) }}</div>Belum setor</div>
    </div>
    @if($pajakBelum > 0)
      <div class="mt-3 text-[10px] text-slate-500 leading-snug">Zona pajak ARKAS: setor &amp; lapor <b>paling lambat tgl 20</b> bulan berikutnya — bukti lapor = NTPN.</div>
      <a href="{{ route('setorpajak.index') }}" class="mt-2 btn w-full justify-center text-xs">Segera Setor &amp; Lapor Pajak</a>
    @endif
  </div>

  <div class="card lg:col-span-2">
    <h3 class="card-title mb-3 flex items-center gap-2">
      <x-icon name="chart" class="h-4 w-4 text-slate-500"/> Realisasi per Tahap Penyaluran
    </h3>
    <div class="space-y-3">
      @forelse($perTahap as $tp)
      <div>
        <div class="flex justify-between text-xs mb-1">
          <span class="font-semibold text-slate-600">{{ $tp['nama'] }}</span>
          <span class="text-slate-400">cair {{ rp($tp['pendapatan']) }} · dibelanjakan <b class="text-slate-600">{{ rp($tp['belanja']) }}</b></span>
          <span class="font-bold {{ $tp['persenSerapan']>=80?'text-emerald-600':($tp['persenSerapan']>=40?'text-amber-500':'text-rose-500') }}">{{ $tp['persenSerapan'] }}%</span>
        </div>
        <div class="h-2.5 rounded-full bg-slate-100">
          <div class="h-2.5 rounded-full {{ $tp['persenSerapan']>=80?'bg-emerald-500':($tp['persenSerapan']>=40?'bg-amber-400':'bg-rose-400') }}" style="width:{{ min(100,$tp['persenSerapan']) }}%"></div>
        </div>
      </div>
      @empty
      <p class="text-sm text-slate-400">Belum ada dana cair atau belanja per tahap.</p>
      @endforelse
    </div>
  </div>
</div>

{{-- ============ BARIS 3: GRAFIK + KOMPOSISI + TRANSAKSI + PENGUMUMAN ============ --}}
<div class="grid gap-4 lg:grid-cols-3">

  <div class="card">
    <h3 class="card-title mb-4">Belanja Bulanan</h3>
    <div class="flex items-end gap-1.5 h-32">
      @php $bulanSingkat = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des']; @endphp
      @foreach($perBulan as $idx => $nilai)
        <div class="flex-1 flex flex-col items-center gap-1 group" title="{{ $bulanSingkat[$idx] }}: {{ rp($nilai) }}">
          <span class="text-[9px] text-slate-400 opacity-0 group-hover:opacity-100">{{ $maxBulan>0 ? round($nilai/$maxBulan*100) : 0 }}%</span>
          <div class="w-full rounded-t {{ $nilai>0?'bg-indigo-400 group-hover:bg-indigo-600':'' }}" style="height:{{ $maxBulan>0 ? max(3, round($nilai/$maxBulan*100)) : 0 }}%"></div>
          <span class="text-[9px] text-slate-400">{{ $bulanSingkat[$idx] }}</span>
        </div>
      @endforeach
    </div>
  </div>

  <div class="card">
    <h3 class="card-title mb-3">Komposisi Belanja (Top 5 Rekening)</h3>
    <div class="space-y-3">
      @forelse($topRekening as $tr)
      <div>
        <div class="flex justify-between text-xs mb-1">
          <span class="text-slate-600"><span class="font-mono text-[10px] text-slate-400">{{ $tr['kode'] }}</span> {{ Str::limit($tr['nama'],30) }}</span>
          <span class="font-bold text-slate-700">{{ $tr['persen'] }}%</span>
        </div>
        <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-indigo-500" style="width:{{ $tr['persen'] }}%"></div></div>
      </div>
      @empty
      <p class="text-sm text-slate-400">Belum ada belanja.</p>
      @endforelse
    </div>
  </div>

  <div class="card">
    <h3 class="card-title mb-3 flex items-center gap-2">
      <x-icon name="megaphone" class="h-4 w-4 text-slate-500"/> Pengumuman Dinas
    </h3>
    @forelse($pengumuman as $p)
      <div class="border-l-4 border-indigo-200 pl-3 py-1.5 mb-2">
        <div class="font-semibold text-sm">{{ $p->judul }}</div>
        <div class="text-xs text-slate-500">{{ Str::limit($p->isi, 90) }}</div>
        <div class="text-[10px] text-slate-400 mt-0.5">{{ $p->created_at->format('d/m/Y H:i') }}</div>
      </div>
    @empty
      <p class="text-sm text-slate-400">Tidak ada pengumuman.</p>
    @endforelse
  </div>
</div>

<div class="card">
  <h3 class="card-title mb-3 flex items-center gap-2">
    <x-icon name="receipt" class="h-4 w-4 text-slate-500"/> Transaksi Terbaru
  </h3>
  <div class="tbl-wrap">
  <table class="tbl">
    <thead><tr><th>BKU</th><th>Tanggal</th><th>Jenis</th><th>Uraian</th><th class="num">Nilai</th></tr></thead>
    <tbody>
      @forelse($terbaru as $t)
      <tr>
        <td>#{{ $t->no_bku }}</td>
        <td>{{ $t->tanggal->format('d/m/Y') }}</td>
        <td><span class="badge {{ $t->type==='pendapatan'?'bg-emerald-100 text-emerald-700':($t->type==='belanja'?'bg-rose-100 text-rose-700':'bg-slate-100 text-slate-600') }}">{{ $t->labelType() }}</span></td>
        <td>{{ Str::limit($t->keterangan, 60) }}</td>
        <td class="num">{{ rp($t->total) }}</td>
      </tr>
      @empty
      <tr><td colspan="5" class="text-center text-slate-400 py-6">Belum ada transaksi tahun {{ $tahun }}.</td></tr>
      @endforelse
    </tbody>
  </table>
  </div>
</div>
<div class="grid gap-4 lg:grid-cols-2">
  <div class="card">
    <h3 class="card-title mb-3">Tren Belanja per Bulan — interaktif ({{ $tahun }})</h3>
    <div class="relative h-56"><canvas id="chBulanSkl"></canvas></div>
  </div>
  <div class="card">
    <h3 class="card-title mb-3">Anggaran vs Realisasi per Komponen</h3>
    @if(count($serapanKomponen ?? []))
      <div class="relative h-56"><canvas id="chKompSkl"></canvas></div>
    @else
      <p class="text-xs text-slate-400 py-10 text-center">Belum ada RKAS disahkan maupun belanja tahun ini.</p>
    @endif
  </div>
</div>
@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}" src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(function(){
  if(!window.Chart) return;
  const LBL=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
  const fmtUang=v=>'Rp '+(v>=1000000?(v/1000000).toLocaleString('id-ID')+' jt':(v/1000).toLocaleString('id-ID')+' rb');
  new Chart(document.getElementById('chBulanSkl'),{type:'line',
    data:{labels:LBL,datasets:[{data:@json(array_values($perBulan)),borderColor:'#0b5fef',backgroundColor:'rgba(11,95,239,.12)',fill:true,tension:.35,pointRadius:3,pointHoverRadius:6}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>fmtUang(c.parsed.y)}}},scales:{y:{ticks:{callback:fmtUang}}}}});
  const elK=document.getElementById('chKompSkl');
  if(elK){
    new Chart(elK,{type:'bar',
      data:{labels:@json(($serapanKomponen ?? collect())->pluck('nama')),datasets:[
        {label:'Anggaran RKAS',data:@json(($serapanKomponen ?? collect())->pluck('anggaran')),backgroundColor:'#bfdbfe',borderRadius:4},
        {label:'Realisasi',data:@json(($serapanKomponen ?? collect())->pluck('realisasi')),backgroundColor:'#0b5fef',borderRadius:4}]},
      options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{tooltip:{callbacks:{label:c=>c.dataset.label+': '+fmtUang(c.parsed.x)}}},scales:{x:{ticks:{callback:fmtUang}}}}});
  }
})();
</script>
@endpush
@endsection
