@extends('layouts.app', ['title' => 'Arsip — BKU Ditutup'])
@section('subtitle', 'Periode pencatatan yang sudah dikunci')
@section('content')
{{-- Filter tahun: sebelumnya halaman ini tidak punya filter sama sekali. --}}
<div class="card flex flex-wrap items-end justify-between gap-2">
  <div>
    <div class="flex items-center gap-2 text-sm font-bold text-[#2f3031]"><x-icon name="folder" class="h-4 w-4 text-[#0b5fef]"/> Arsip BKU</div>
    <p class="text-[11px] text-slate-500">Bulan dan tahap yang sudah dikunci — padanan arsip BKU ARKAS 4.</p>
  </div>
  @include('arsip.partials._filter', ['tahun' => $tahun, 'daftarTahun' => $daftarTahun])
</div>

<div class="grid lg:grid-cols-2 gap-4">
  <div class="card">
    <h3 class="card-title mb-3 flex items-center gap-2"><x-icon name="lock" class="h-4 w-4 text-slate-500"/> Penutupan BKU Bulanan</h3>
    <p class="text-[11px] text-slate-500 mb-2">Bulan yang sudah ditutup tidak dapat diubah (kecuali Kepala Sekolah membuka kembali).</p>
    <div class="overflow-x-auto">
      <table class="tbl">
        <thead><tr><th>Bulan</th><th>Status</th><th>Ditutup oleh</th><th>Waktu</th></tr></thead>
        <tbody>
          @forelse($bulan as $b)
            <tr>
              @php($bulanKe = (int) substr((string) $b->bulan, 5, 2))
              <td>{{ \App\Services\CekIntegritasService::BULAN[$bulanKe] ?? $b->bulan }} {{ $b->tahun }}</td>
              <td><span class="badge bg-emerald-100 text-emerald-700">Tertutup</span></td>
              <td>{{ $b->ditutup_oleh ?? '—' }}</td>
              <td class="text-[11px] text-slate-500">{{ $b->ditutup_at?->format('d/m/Y H:i') ?? '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-slate-400 py-5">Belum ada bulan yang ditutup pada TA {{ $tahun }}.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h3 class="card-title mb-3 flex items-center gap-2"><x-icon name="lock" class="h-4 w-4 text-slate-500"/> Penutupan Buku per Tahap</h3>
    <p class="text-[11px] text-slate-500 mb-2">Tahap yang ditutup mengunci seluruh transaksi pada tahap tersebut.</p>
    <div class="overflow-x-auto">
      <table class="tbl">
        <thead><tr><th>Tahap</th><th>Status</th><th>Ditutup oleh</th><th>Waktu</th></tr></thead>
        <tbody>
          @forelse($tahap as $t)
            <tr>
              <td>{{ $t->stage?->nama ?? 'Tahap '.$t->stage_id }}</td>
              <td><span class="badge bg-emerald-100 text-emerald-700">Tertutup</span></td>
              <td>{{ $t->ditutup_oleh ?? '—' }}</td>
              <td class="text-[11px] text-slate-500">{{ $t->ditutup_at?->format('d/m/Y H:i') ?? '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-slate-400 py-5">Belum ada tahap yang ditutup pada TA {{ $tahun }}.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-3 flex gap-2">
      <a href="{{ route('bku') }}" class="btn2 btn-sm"><x-icon name="book" class="h-4 w-4"/> Buka BKU Bulanan</a>
      <a href="{{ route('laporan-arkas.penutupan-kas') }}" class="btn2 btn-sm"><x-icon name="receipt" class="h-4 w-4"/> Register Penutupan Kas</a>
    </div>
  </div>
</div>
@endsection
