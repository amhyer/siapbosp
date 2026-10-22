@extends('layouts.app', ['title' => 'Arsip — BKU Ditutup'])
@section('subtitle', 'Periode pencatatan yang sudah dikunci')
@section('content')
<div class="grid lg:grid-cols-2 gap-4">
  <div class="card">
    <h3 class="card-title mb-3">🔒 Penutupan BKU Bulanan</h3>
    <p class="text-[11px] text-slate-500 mb-2">Bulan yang sudah ditutup tidak dapat diubah (kecuali Kepala Sekolah membuka kembali).</p>
    <div class="overflow-x-auto">
      <table class="tbl">
        <thead><tr><th>Bulan</th><th>Status</th><th>Ditutup oleh</th><th>Waktu</th></tr></thead>
        <tbody>
          @forelse($bulan as $b)
            <tr>
              <td>{{ \App\Services\CekIntegritasService::BULAN[(int) $b->bulan] ?? $b->bulan }} {{ substr((string) $b->bulan, -2) }}</td>
              <td><span class="badge bg-emerald-100 text-emerald-700">🔒 Tertutup</span></td>
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
    <h3 class="card-title mb-3">🔒 Penutupan Buku per Tahap</h3>
    <p class="text-[11px] text-slate-500 mb-2">Tahap yang ditutup mengunci seluruh transaksi pada tahap tersebut.</p>
    <div class="overflow-x-auto">
      <table class="tbl">
        <thead><tr><th>Tahap</th><th>Status</th><th>Ditutup oleh</th><th>Waktu</th></tr></thead>
        <tbody>
          @forelse($tahap as $t)
            <tr>
              <td>{{ $t->stage?->nama ?? 'Tahap '.$t->stage_id }}</td>
              <td><span class="badge bg-emerald-100 text-emerald-700">🔒 Tertutup</span></td>
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
      <a href="{{ route('bku') }}" class="btn2 btn-sm">📒 Buka BKU Bulanan</a>
      <a href="{{ route('laporan-arkas.penutupan-kas') }}" class="btn2 btn-sm">🧾 Register Penutupan Kas</a>
    </div>
  </div>
</div>
@endsection
