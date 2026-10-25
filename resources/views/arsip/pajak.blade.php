@extends('layouts.app', ['title' => 'Arsip — Dokumen Pajak'])
@section('subtitle', 'Pajak dipungut, status setor & laporan, serta NTPN')
@section('content')
<div class="card">
  <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
    <h3 class="card-title mb-0">🧾 Dokumen Pajak</h3>
    <form method="get" class="flex gap-2 items-end">
      <div><label class="lbl">Tahun</label>
        <select name="tahun" class="inp !w-auto" data-kirim-otomatis>
          @for($y = 2023; $y <= 2028; $y++)<option value="{{ $y }}" @selected($tahun === $y)>{{ $y }}</option>@endfor
        </select>
      </div>
    </form>
  </div>
  <div class="overflow-x-auto">
    <table class="tbl">
      <thead><tr><th>Tanggal</th><th>No Bukti</th><th>Jenis Pajak</th><th class="num">Nilai</th><th>Status</th><th>NTPN</th><th>Kode Billing</th><th>Transaksi</th></tr></thead>
      <tbody>
        @forelse($pajak as $p)
          <tr>
            <td>{{ $p->transaksi?->tanggal?->format('d/m/Y') ?? '—' }}</td>
            <td class="font-mono text-[11px]">{{ $p->transaksi?->no_bukti ?? '—' }}</td>
            <td>{{ $p->rekeningPajak?->nama ?? '—' }}</td>
            <td class="num font-semibold">{{ rp($p->nilai) }}</td>
            <td>
              @if($p->status === 'sudah')
                <span class="badge bg-emerald-100 text-emerald-700">✓ sudah lapor</span>
              @else
                <span class="badge bg-rose-100 text-rose-700">⚠ wajib lapor</span>
              @endif
            </td>
            <td class="font-mono text-[11px]">{{ $p->ntpn ?? '—' }}</td>
            <td class="font-mono text-[11px]">{{ $p->kode_billing ?? '—' }}</td>
            <td class="text-[11px] text-slate-500">{{ \Illuminate\Support\Str::limit($p->transaksi?->keterangan ?? '', 40) }}</td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-slate-400 py-6">Belum ada pajak tercatat pada TA {{ $tahun }}.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3 flex gap-2">
    <a href="{{ route('setorpajak.index') }}" class="btn2 btn-sm">💸 Setor Pajak</a>
    <a href="{{ route('laporan.pajak-tw') }}" class="btn2 btn-sm">🧾 Pajak Triwulan</a>
  </div>
</div>
@endsection
