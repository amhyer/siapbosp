@extends('layouts.app', ['title' => 'Dashboard Wilayah'])
@section('content')
@php $totalPagu=$schools->sum('pagu'); $totalBelanja=$schools->sum('belanja'); @endphp
<div class="grid sm:grid-cols-3 gap-4">
  <div class="card"><div class="text-xs text-slate-500">Sekolah Aktif</div><div class="text-2xl font-bold text-slate-700">{{ $schools->where('is_active')->count() }} / {{ $schools->count() }}</div>
    @if($belumAktif>0)<span class="badge bg-red-100 text-red-700 mt-1">⚠️ {{ $belumAktif }} sekolah tak aktif 30+ hari</span>@endif</div>
  <div class="card"><div class="text-xs text-slate-500">Total Pagu ({{ $tahun }})</div><div class="text-2xl font-bold text-indigo-700">{{ rp($totalPagu) }}</div></div>
  <div class="card"><div class="text-xs text-slate-500">Realisasi Wilayah</div>
    <div class="text-2xl font-bold text-emerald-700">{{ rp($totalBelanja) }}</div>
    <div class="text-xs text-slate-400">Serapan rata² {{ $totalPagu>0?round($totalBelanja/$totalPagu*100,1):0 }}%</div>
    @if($pajakBelumTotal>0)<div class="text-[11px] text-amber-600 mt-1">🧾 Pajak belum disetor wilayah: {{ rp($pajakBelumTotal) }}</div>@endif</div>
</div>
<div class="grid gap-4 lg:grid-cols-2">
  <div class="card">
    <h3 class="font-semibold text-slate-700 text-sm mb-3">🏆 Peringkat Serapan (Top 8)</h3>
    <div class="space-y-2.5">
      @forelse($peringkat as $i => $s)
      <div>
        <div class="flex justify-between text-xs mb-0.5">
          <span class="text-slate-600"><b class="text-slate-400">#{{ $i + 1 }}</b> {{ Str::limit($s->nama, 34) }}</span>
          <span class="font-bold {{ $s->serapan >= 80 ? 'text-emerald-600' : ($s->serapan >= 40 ? 'text-amber-500' : 'text-rose-500') }}">{{ $s->serapan }}%</span>
        </div>
        <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full {{ $s->serapan >= 80 ? 'bg-emerald-500' : ($s->serapan >= 40 ? 'bg-amber-400' : 'bg-rose-400') }}" style="width:{{ min(100, $s->serapan) }}%"></div></div>
      </div>
      @empty
      <p class="text-sm text-slate-400">Belum ada data.</p>
      @endforelse
    </div>
  </div>
  <div class="card">
    <h3 class="font-semibold text-slate-700 text-sm mb-3">📈 Belanja Wilayah per Tahap</h3>
    <div class="space-y-2.5">
      @forelse($perTahapWilayah as $tp)
      <div>
        <div class="flex justify-between text-xs mb-0.5">
          <span class="font-semibold text-slate-600">{{ $tp['nama'] }}</span>
          <span class="font-bold text-slate-700">{{ rp($tp['belanja']) }}</span>
        </div>
        <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-indigo-500" style="width:{{ $perTahapWilayah->max('belanja') > 0 ? round($tp['belanja'] / $perTahapWilayah->max('belanja') * 100) : 0 }}%"></div></div>
      </div>
      @empty
      <p class="text-sm text-slate-400">Belum ada belanja wilayah tahun ini.</p>
      @endforelse
    </div>
  </div>
</div>
<div class="grid gap-4 lg:grid-cols-2">
  <div class="card">
    <h3 class="font-semibold text-slate-700 text-sm mb-3">📈 Tren Belanja Wilayah per Bulan ({{ $tahun }})</h3>
    <div class="relative h-56"><canvas id="chTrenWil"></canvas></div>
  </div>
  <div class="card">
    <h3 class="font-semibold text-slate-700 text-sm mb-3">🧩 Komposisi Belanja per Komponen</h3>
    <div class="relative h-56"><canvas id="chKomposisiWil"></canvas></div>
  </div>
</div>
<div class="card">
  <h3 class="font-semibold text-slate-700 text-sm mb-2">📧 Notifikasi Email</h3>
  <p class="text-xs text-slate-500 leading-5">
    Peristiwa penting — RKAS masuk/disahkan/revisi, LPJ masuk/diterima/ditolak, tahap dibuka–ditutup, reset sandi, dan pengumuman — kini juga dikirim ke <b>email</b> pengguna.
    Driver aktif: <b class="text-indigo-700">{{ config('mail.default') }}</b> · Pengirim: <b>{{ config('mail.from.address') }}</b>
    @if(config('mail.default') === 'log')
      <br><span class="text-amber-600">Mode LOG: surat tercatat utuh di <code>storage/logs/laravel.log</code>, belum masuk kotak masuk sungguhan. Untuk email riil, isi <code>MAIL_MAILER=smtp</code> + kredensial SMTP di file <code>.env</code>.</span>
    @endif
  </p>
  <form method="POST" action="{{ route('dinas.email-uji') }}" class="mt-3">@csrf
    <button class="btn bg-indigo-600 text-white text-xs">📨 Kirim Email Uji ke {{ auth()->user()->email }}</button>
  </form>
</div>
<div class="card overflow-x-auto">
  <h3 class="font-semibold text-slate-700 mb-3">Progres Serapan per Sekolah</h3>
  <table class="tbl">
    <thead><tr><th>Sekolah (NPSN)</th><th>Jenjang</th><th>Kecamatan</th><th class="num">Pagu</th><th class="num">Realisasi</th><th>Serapan</th><th>Aktivitas Terakhir</th><th>Status</th></tr></thead>
    <tbody>
      @foreach($schools as $s)
      <tr>
        <td><a href="{{ route('dinas.sekolah.show', $s) }}" class="font-semibold text-indigo-700 hover:underline">{{ $s->nama }}</a>
          <div class="text-[11px] text-slate-400">{{ $s->npsn }}@if($s->notAktif) · <span class="text-red-500 font-semibold">⚠️ tak aktif</span>@endif</div></td>
        <td>{{ $s->jenjang }}</td><td>{{ $s->kecamatan ?? '—' }}</td>
        <td class="num">{{ rp($s->pagu) }}</td><td class="num">{{ rp($s->belanja) }}</td>
        <td style="min-width:120px"><div class="flex items-center gap-2"><div class="h-2 w-20 rounded-full bg-slate-100"><div class="h-2 rounded-full {{ $s->serapan>=80?'bg-emerald-500':($s->serapan>=40?'bg-amber-400':'bg-rose-400') }}" style="width:{{ min(100,$s->serapan) }}%"></div></div><span class="text-xs font-semibold">{{ $s->serapan }}%</span></div></td>
        <td class="text-xs text-slate-500">{{ $s->last ? \Carbon\Carbon::parse($s->last)->diffForHumans() : '—' }}</td>
        <td>{!! $s->is_active ? '<span class="badge bg-emerald-100 text-emerald-700">aktif</span>' : '<span class="badge bg-slate-200 text-slate-500">nonaktif</span>' !!}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}" src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(function(){
  if(!window.Chart) return;
  const LBL=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
  const fmtUang=v=>'Rp '+(v>=1000000?(v/1000000).toLocaleString('id-ID')+' jt':(v/1000).toLocaleString('id-ID')+' rb');
  new Chart(document.getElementById('chTrenWil'),{type:'line',
    data:{labels:LBL,datasets:[{data:@json(array_values($trenBulanan)),borderColor:'#1a56db',backgroundColor:'rgba(26,86,219,.18)',fill:true,tension:.35,pointRadius:3,pointHoverRadius:6}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>fmtUang(c.parsed.y)}}},scales:{y:{ticks:{callback:fmtUang}}}}});
  new Chart(document.getElementById('chKomposisiWil'),{type:'doughnut',
    data:{labels:@json($komposisiKomponen->pluck('nama')),datasets:[{data:@json($komposisiKomponen->pluck('jumlah')),backgroundColor:['#1a56db','#059669','#d97706','#dc2626','#0891b2','#7c3aed','#db2777','#65a30d','#9333ea']}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'right'},tooltip:{callbacks:{label:c=>' '+c.label+': '+fmtUang(c.parsed)}}}}});
})();
</script>
@endpush
@endsection
