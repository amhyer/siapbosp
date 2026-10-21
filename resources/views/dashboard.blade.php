@extends('layouts.app', ['title' => 'Dashboard'])
@section('subtitle', 'Ringkasan kas, serapan, tenggat BOSP 2026, dan aktivitas tahun ' . $tahun)
@section('content')
<div class="page-head">
  <div>
    <p class="page-desc">Posisi kas terakhir, kepatuhan Permendikdasmen No. 8 Tahun 2026, status pajak, dan progres per tahap penyaluran.</p>
  </div>
  <div class="flex flex-wrap gap-2">
    <a href="{{ route('perencanaan.index') }}" class="btn2 btn-sm">🧭 PBD → RKT</a>
    <a href="{{ route('belanja.create') }}" class="btn btn-sm">➕ Input Belanja</a>
    <a href="{{ route('laporan.tahap') }}" data-cetak="LPJ per Tahap" class="btn2 btn-sm">🖨️ LPJ per Tahap</a>
  </div>
</div>

{{-- ============ PENGINGAT TENGGAT PASAL 59 PERMENDIKDASMEN 8/2026 + PAJAK TGL 20 + TUTUP BKU ============ --}}
@if(!empty($statusTenggat) && $statusTenggat['tahap1_terlambat'])
<div class="card border-l-4 border-rose-600 bg-rose-50/70 flex flex-wrap items-center gap-3 text-sm">
  <span class="text-xl">🚨</span>
  <div class="flex-1 min-w-[16rem] leading-snug text-rose-900">
    <b>Peringatan Pasal 59 Permendikdasmen No. 8 Tahun 2026:</b>
    <span> {{ $statusTenggat['pesan'] }}</span>
  </div>
  <a href="{{ route('bku', ['bulan' => $tahun . '-06']) }}" class="btn btn-sm bg-rose-600">Tutup BKU Tahap I →</a>
</div>
@elseif(!empty($statusTenggat))
<div class="card border-l-4 border-emerald-500 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600">
  <span>⚖️ <b>Tenggat Pelaporan BOSP (Pasal 59 Permendikdasmen No. 8/2026):</b> Tahap I maks. <b>31 Juli {{ $tahun }}</b> (potongan 2% Ags · 3% Sep · 4% 1–25 Okt) · Tahunan maks. <b>31 Januari {{ $tahun + 1 }}</b>.</span>
  @if($statusTenggat['tahap1_tuntas'])<span class="badge bg-emerald-100 text-emerald-700">✓ BKU Tahap I Tuntas</span>@endif
</div>
@endif

@if ($pajakBelum > 0 && now()->day >= 12 && now()->day <= 20)
<div class="card border-l-4 border-amber-500 flex flex-wrap items-center gap-3 text-sm">
  <span class="text-xl">⏰</span>
  <div class="flex-1 min-w-[16rem] leading-snug">
    <b>Tenggat pajak: setor &amp; lapor paling lambat <u>tgl 20</u></b>
    <span class="text-slate-500"> — {{ rp($pajakBelum) }} belum disetor (bukti lapor = NTPN).</span>
  </div>
  <a href="{{ route('setorpajak.index') }}" class="btn btn-sm">🗃️ Setor &amp; Lapor Pajak</a>
</div>
@endif
@if ($bkuTerbuka && now()->day >= 24)
<div class="card border-l-4 border-indigo-500 flex flex-wrap items-center gap-3 text-sm">
  <span class="text-xl">📕</span>
  <div class="flex-1 min-w-[16rem] leading-snug">
    <b>Akhir bulan: saatnya tutup BKU.</b>
    <span class="text-slate-500"> BKU bulan {{ \Carbon\Carbon::parse($bkuTerbuka.'-01')->translatedFormat('F Y') }} masih terbuka — tutup dulu agar bulan berikutnya bisa mulai.</span>
  </div>
  <a href="{{ route('bku', ['bulan' => $bkuTerbuka]) }}" class="btn btn-sm">Buka BKU →</a>
</div>
@endif

{{-- ============ RINGKASAN KEPATUHAN KERTAS KERJA RKAS TERHADAP JUKNIS 2026 ============ --}}
@if(!empty($ringkasanJuknis) && !empty($rkasAktif))
<div class="card flex flex-wrap items-center justify-between gap-3">
  <div>
    <div class="text-xs font-bold uppercase tracking-wider text-indigo-600">📋 Kertas Kerja Aktif · {{ $rkasAktif->labelJenis() }} (Revisi ke-{{ $rkasAktif->revisi_ke }})</div>
    <div class="text-xs text-slate-500 mt-0.5">{{ $ringkasanJuknis['aturan']['label_aturan'] }} · Belum Dianggarkan: <b>{{ rp($ringkasanJuknis['belum_dianggarkan']) }}</b> · Tahap 1: <b>{{ $ringkasanJuknis['tahap1_pct'] }}%</b></div>
  </div>
  <div class="flex flex-wrap gap-2 items-center text-xs">
    @if($ringkasanJuknis['aturan']['mode'] === 'reguler')
      <span class="badge {{ $ringkasanJuknis['proporsi']['buku']['ok'] ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">📚 Buku {{ $ringkasanJuknis['proporsi']['buku']['pct'] }}% (min {{ $ringkasanJuknis['aturan']['buku_min_pct'] }}%)</span>
      <span class="badge {{ $ringkasanJuknis['proporsi']['sarpras']['ok'] ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">🛠️ Sarpras {{ $ringkasanJuknis['proporsi']['sarpras']['pct'] }}% (maks {{ $ringkasanJuknis['aturan']['sarpras_max_pct'] }}%)</span>
      <span class="badge {{ $ringkasanJuknis['proporsi']['honor']['ok'] ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">👩‍🏫 Honor {{ $ringkasanJuknis['proporsi']['honor']['pct'] }}% (maks {{ $ringkasanJuknis['aturan']['honor_max_pct'] }}%)</span>
    @endif
    <a href="{{ route('rkas.salinArkas', $rkasAktif) }}" class="btn2 btn-sm">📋 Salin ke ARKAS 4</a>
  </div>
</div>
@endif

{{-- ============ BARIS 1: KARTU UTAMA DENGAN PERSENTASE ============ --}}
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

  <div class="card relative overflow-hidden">
    <div class="absolute left-0 top-0 h-full w-1.5 bg-emerald-500"></div>
    <div class="text-xs text-slate-500">Saldo Kas Tunai</div>
    <div class="text-xl font-bold text-emerald-700 mt-1">{{ rp($saldo['tunai']) }}</div>
    <div class="mt-2 flex items-center justify-between text-[11px]">
      <span class="text-slate-400">dari total kas</span>
      <span class="badge bg-emerald-100 text-emerald-700">{{ $tunaiPct }}%</span>
    </div>
    <div class="mt-1 h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-emerald-500" style="width:{{ $tunaiPct }}%"></div></div>
  </div>

  <div class="card relative overflow-hidden">
    <div class="absolute left-0 top-0 h-full w-1.5 bg-sky-500"></div>
    <div class="text-xs text-slate-500">Saldo Kas Bank</div>
    <div class="text-xl font-bold text-sky-700 mt-1">{{ rp($saldo['bank']) }}</div>
    <div class="mt-2 flex items-center justify-between text-[11px]">
      <span class="text-slate-400">dari total kas</span>
      <span class="badge bg-sky-100 text-sky-700">{{ $bankPct }}%</span>
    </div>
    <div class="mt-1 h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-sky-500" style="width:{{ $bankPct }}%"></div></div>
  </div>

  <div class="card relative overflow-hidden">
    <div class="absolute left-0 top-0 h-full w-1.5 bg-indigo-500"></div>
    <div class="text-xs text-slate-500">Dana Diterima ({{ $tahun }})</div>
    <div class="text-xl font-bold text-indigo-700 mt-1">{{ rp($pendapatan) }}</div>
    <div class="mt-2 flex items-center justify-between text-[11px]">
      <span class="text-slate-400">dari pagu {{ rp($pagu) }}</span>
      <span class="badge bg-indigo-100 text-indigo-700">{{ $diterimaPct }}%</span>
    </div>
    <div class="mt-1 h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-indigo-500" style="width:{{ min(100,$diterimaPct) }}%"></div></div>
  </div>

  <div class="card flex items-center gap-4">
    <div class="w-16 h-16 rounded-full flex items-center justify-center shrink-0"
         style="background:conic-gradient(#6366f1 {{ min(100,$serapan)*3.6 }}deg, #e2e8f0 0deg)">
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
      <h3 class="font-semibold text-slate-700 text-sm">🧾 Status Pajak <span class="text-[10px] text-slate-400">(wajib lapor)</span></h3>
      <span class="badge {{ $pajakPct===100.0?'bg-emerald-100 text-emerald-700':'bg-amber-100 text-amber-700' }}">{{ $pajakPct }}% disetor</span>
    </div>
    <div class="mt-3 h-2.5 rounded-full bg-amber-100"><div class="h-2.5 rounded-full bg-emerald-500" style="width:{{ $pajakPct }}%"></div></div>
    <div class="mt-3 grid grid-cols-3 gap-2 text-center text-[11px] text-slate-500">
      <div class="bg-slate-50 rounded-lg py-2"><div class="font-bold text-slate-700 text-sm">{{ rp($pajakTerima) }}</div>Diterima</div>
      <div class="bg-slate-50 rounded-lg py-2"><div class="font-bold text-emerald-700 text-sm">{{ rp($pajakSudah) }}</div>Sudah setor</div>
      <div class="bg-slate-50 rounded-lg py-2"><div class="font-bold {{ $pajakBelum>0?'text-amber-600':'text-slate-400' }} text-sm">{{ rp($pajakBelum) }}</div>Belum setor</div>
    </div>
    @if($pajakBelum > 0)
      <div class="mt-3 text-[10px] text-slate-500 leading-snug">Zona pajak ARKAS: setor &amp; lapor <b>paling lambat tgl 20</b> bulan berikutnya — bukti lapor = NTPN.</div>
      <a href="{{ route('setorpajak.index') }}" class="mt-1 btn w-full justify-center text-xs">➜ Segera Setor &amp; Lapor Pajak</a>
    @endif
  </div>

  <div class="card lg:col-span-2">
    <h3 class="font-semibold text-slate-700 text-sm mb-3">📈 Realisasi per Tahap Penyaluran</h3>
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
    <h3 class="font-semibold text-slate-700 text-sm mb-4">📊 Belanja Bulanan</h3>
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
    <h3 class="font-semibold text-slate-700 text-sm mb-3">🥧 Komposisi Belanja (Top 5 Rekening)</h3>
    <div class="space-y-3">
      @forelse($topRekening as $tr)
      <div>
        <div class="flex justify-between text-xs mb-1">
          <span class="text-slate-600"><span class="font-mono text-[10px] text-slate-400">{{ $tr['kode'] }}</span> {{ Str::limit($tr['nama'],30) }}</span>
          <span class="font-bold text-slate-700">{{ $tr['persen'] }}%</span>
        </div>
        <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-violet-500" style="width:{{ $tr['persen'] }}%"></div></div>
      </div>
      @empty
      <p class="text-sm text-slate-400">Belum ada belanja.</p>
      @endforelse
    </div>
  </div>

  <div class="card">
    <h3 class="font-semibold text-slate-700 text-sm mb-3">📣 Pengumuman Dinas</h3>
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
  <h3 class="card-title mb-3">🕘 Transaksi Terbaru</h3>
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
    <h3 class="font-semibold text-slate-700 text-sm mb-3">📈 Tren Belanja per Bulan — interaktif ({{ $tahun }})</h3>
    <div class="relative h-56"><canvas id="chBulanSkl"></canvas></div>
  </div>
  <div class="card">
    <h3 class="font-semibold text-slate-700 text-sm mb-3">🎯 Anggaran vs Realisasi per Komponen</h3>
    @if(count($serapanKomponen ?? []))
      <div class="relative h-56"><canvas id="chKompSkl"></canvas></div>
    @else
      <p class="text-xs text-slate-400 py-10 text-center">Belum ada RKAS disahkan maupun belanja tahun ini.</p>
    @endif
  </div>
</div>
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function(){
  if(!window.Chart) return;
  const LBL=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
  const fmtUang=v=>'Rp '+(v>=1000000?(v/1000000).toLocaleString('id-ID')+' jt':(v/1000).toLocaleString('id-ID')+' rb');
  new Chart(document.getElementById('chBulanSkl'),{type:'line',
    data:{labels:LBL,datasets:[{data:@json(array_values($perBulan)),borderColor:'#0891b2',backgroundColor:'rgba(8,145,178,.15)',fill:true,tension:.35,pointRadius:3,pointHoverRadius:6}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>fmtUang(c.parsed.y)}}},scales:{y:{ticks:{callback:fmtUang}}}}});
  const elK=document.getElementById('chKompSkl');
  if(elK){
    new Chart(elK,{type:'bar',
      data:{labels:@json(($serapanKomponen ?? collect())->pluck('nama')),datasets:[
        {label:'Anggaran RKAS',data:@json(($serapanKomponen ?? collect())->pluck('anggaran')),backgroundColor:'#c7d2fe',borderRadius:4},
        {label:'Realisasi',data:@json(($serapanKomponen ?? collect())->pluck('realisasi')),backgroundColor:'#4f46e5',borderRadius:4}]},
      options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{tooltip:{callbacks:{label:c=>c.dataset.label+': '+fmtUang(c.parsed.x)}}},scales:{x:{ticks:{callback:fmtUang}}}}});
  }
})();
</script>
@endpush
@endsection
