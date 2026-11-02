@extends('layouts.app', ['title' => 'Dashboard Wilayah'])
@section('subtitle', 'Ringkasan serapan, aktivitas, dan notifikasi sekolah di wilayah')
@section('content')
@php $totalPagu=$schools->sum('pagu'); $totalBelanja=$schools->sum('belanja'); @endphp

<div class="grid gap-4 sm:grid-cols-3">
  <div class="card">
    <div class="text-xs text-[#65696c]">Sekolah Aktif</div>
    <div class="mt-1 text-2xl font-bold text-[#2f3031]">{{ $schools->where('is_active')->count() }} / {{ $schools->count() }}</div>
    @if($belumAktif>0)
      <span class="badge mt-1 bg-red-100 text-red-700">{{ $belumAktif }} sekolah tak aktif 30+ hari</span>
    @endif
  </div>
  <div class="card">
    <div class="text-xs text-[#65696c]">Total Pagu ({{ $tahun }})</div>
    <div class="mt-1 text-2xl font-bold text-[#0b5fef]">{{ rp($totalPagu) }}</div>
  </div>
  <div class="card">
    <div class="text-xs text-[#65696c]">Realisasi Wilayah</div>
    <div class="mt-1 text-2xl font-bold text-emerald-700">{{ rp($totalBelanja) }}</div>
    <div class="text-xs text-[#808489]">Serapan rata-rata {{ $totalPagu>0?round($totalBelanja/$totalPagu*100,1):0 }}%</div>
    @if($pajakBelumTotal>0)
      <div class="mt-1 text-[11px] text-amber-600">Pajak belum disetor wilayah: {{ rp($pajakBelumTotal) }}</div>
    @endif
  </div>
</div>

<div class="grid gap-4 lg:grid-cols-2">
  <div class="card">
    <h3 class="card-title mb-3 flex items-center gap-2"><x-icon name="chart" class="h-4 w-4 text-slate-500"/> Peringkat Serapan (Top 8)</h3>
    <div class="space-y-2.5">
      @forelse($peringkat as $i => $s)
        <div>
          <div class="mb-0.5 flex justify-between text-xs">
            <span class="text-[#65696c]"><b class="text-[#a0a4a8]">#{{ $i + 1 }}</b> {{ Str::limit($s->nama, 34) }}</span>
            <span class="font-bold {{ $s->serapan >= 80 ? 'text-emerald-600' : ($s->serapan >= 40 ? 'text-amber-500' : 'text-rose-500') }}">{{ $s->serapan }}%</span>
          </div>
          <div class="h-2 rounded-full bg-slate-100">
            <div class="h-2 rounded-full {{ $s->serapan >= 80 ? 'bg-emerald-500' : ($s->serapan >= 40 ? 'bg-amber-400' : 'bg-rose-400') }}" style="width:{{ min(100, $s->serapan) }}%"></div>
          </div>
        </div>
      @empty
        <p class="text-sm text-slate-400">Belum ada data.</p>
      @endforelse
    </div>
  </div>

  <div class="card">
    <h3 class="card-title mb-3 flex items-center gap-2"><x-icon name="cash" class="h-4 w-4 text-slate-500"/> Belanja Wilayah per Tahap</h3>
    <div class="space-y-2.5">
      @forelse($perTahapWilayah as $tp)
        <div>
          <div class="mb-0.5 flex justify-between text-xs">
            <span class="font-semibold text-[#65696c]">{{ $tp['nama'] }}</span>
            <span class="font-bold text-slate-700">{{ rp($tp['belanja']) }}</span>
          </div>
          <div class="h-2 rounded-full bg-slate-100">
            <div class="h-2 rounded-full bg-[#0b5fef]" style="width:{{ $perTahapWilayah->max('belanja') > 0 ? round($tp['belanja'] / $perTahapWilayah->max('belanja') * 100) : 0 }}%"></div>
          </div>
        </div>
      @empty
        <p class="text-sm text-slate-400">Belum ada belanja wilayah tahun ini.</p>
      @endforelse
    </div>
  </div>
</div>

<div class="grid gap-4 lg:grid-cols-2">
  <div class="card">
    <h3 class="card-title mb-3">Tren Belanja Wilayah per Bulan ({{ $tahun }})</h3>
    <div class="relative h-56"><canvas id="chTrenWil"></canvas></div>
  </div>
  <div class="card">
    <h3 class="card-title mb-3">Komposisi Belanja per Komponen</h3>
    <div class="relative h-56"><canvas id="chKomposisiWil"></canvas></div>
  </div>
</div>

<div class="card">
  <h3 class="card-title mb-2 flex items-center gap-2"><x-icon name="megaphone" class="h-4 w-4 text-slate-500"/> Notifikasi Email</h3>
  <p class="text-xs leading-5 text-[#65696c]">
    Peristiwa penting — RKAS masuk/disahkan/revisi, LPJ masuk/diterima/ditolak, tahap dibuka–ditutup,
    reset sandi, dan pengumuman — kini juga dikirim ke <b>email</b> pengguna.
    Driver aktif: <b class="text-[#0b5fef]">{{ config('mail.default') }}</b> · Pengirim: <b>{{ config('mail.from.address') }}</b>
    @if(config('mail.default') === 'log')
      <br><span class="text-amber-600">Mode LOG: surat tercatat utuh di <code>storage/logs/laravel.log</code>, belum masuk kotak masuk sungguhan. Untuk email riil, isi <code>MAIL_MAILER=smtp</code> + kredensial SMTP di file <code>.env</code>.</span>
    @endif
  </p>
  <form method="POST" action="{{ route('dinas.email-uji') }}" class="mt-3">
    @csrf
    <button class="btn text-xs"><x-icon name="upload" class="h-4 w-4"/> Kirim Email Uji ke {{ auth()->user()->email }}</button>
  </form>
</div>

<div class="card overflow-x-auto">
  <h3 class="card-title mb-3">Progres Serapan per Sekolah</h3>
  <table class="tbl">
    <thead><tr><th>Sekolah (NPSN)</th><th>Jenjang</th><th>Kecamatan</th><th class="num">Pagu</th><th class="num">Realisasi</th><th>Serapan</th><th>Aktivitas Terakhir</th><th>Status</th></tr></thead>
    <tbody>
      @foreach($schools as $s)
      <tr>
        <td>
          <a href="{{ route('dinas.sekolah.show', $s) }}" class="font-semibold text-[#0b5fef] hover:underline">{{ $s->nama }}</a>
          <div class="text-[11px] text-[#808489]">{{ $s->npsn }}@if($s->notAktif) · <span class="font-semibold text-red-500">tak aktif</span>@endif</div>
        </td>
        <td>{{ $s->jenjang }}</td>
        <td>{{ $s->kecamatan ?? '—' }}</td>
        <td class="num">{{ rp($s->pagu) }}</td>
        <td class="num">{{ rp($s->belanja) }}</td>
        <td style="min-width:120px">
          <div class="flex items-center gap-2">
            <div class="h-2 w-20 rounded-full bg-slate-100">
              <div class="h-2 rounded-full {{ $s->serapan>=80?'bg-emerald-500':($s->serapan>=40?'bg-amber-400':'bg-rose-400') }}" style="width:{{ min(100,$s->serapan) }}%"></div>
            </div>
            <span class="text-xs font-semibold">{{ $s->serapan }}%</span>
          </div>
        </td>
        <td class="text-xs text-[#808489]">{{ $s->last ? \Carbon\Carbon::parse($s->last)->diffForHumans() : '—' }}</td>
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
    data:{labels:LBL,datasets:[{data:@json(array_values($trenBulanan)),borderColor:'#0b5fef',backgroundColor:'rgba(26,86,219,.18)',fill:true,tension:.35,pointRadius:3,pointHoverRadius:6}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>fmtUang(c.parsed.y)}}},scales:{y:{ticks:{callback:fmtUang}}}}});
  new Chart(document.getElementById('chKomposisiWil'),{type:'doughnut',
    data:{labels:@json($komposisiKomponen->pluck('nama')),datasets:[{data:@json($komposisiKomponen->pluck('jumlah')),backgroundColor:['#0b5fef','#059669','#d97706','#dc2626','#0891b2','#7c3aed','#db2777','#65a30d','#9333ea']}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'right'},tooltip:{callbacks:{label:c=>' '+c.label+': '+fmtUang(c.parsed)}}}}});
})();
</script>
@endpush
@endsection
