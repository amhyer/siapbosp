@extends('layouts.app', ['title' => 'Arsip — RKAS Disahkan'])
@section('subtitle', 'Dokumen RKAS yang telah disahkan Dinas — sah untuk pemeriksaan')
@section('content')
<div class="card">
  <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
    <h3 class="card-title mb-0">🗄️ RKAS Disahkan</h3>
    @include('arsip.partials._filter', [
      'tahun' => $tahun,
      'daftarTahun' => $daftarTahun,
      'sumberFields' => $daftarSumber,
      'sumber' => $sumber,
    ])
  </div>
  <div class="overflow-x-auto">
    <table class="tbl">
      <thead><tr><th>Revisi</th><th>No RKAS</th><th>Jenis</th><th>Sumber Dana</th><th class="num">Rincian</th><th class="num">Total</th><th>Disahkan</th><th></th></tr></thead>
      <tbody>
        @forelse($items as $h)
          <tr>
            <td>Revisi ke-{{ $h->revisi_ke }}</td>
            <td>{{ $h->no_rkas ?? '—' }}</td>
            <td>{{ $h->labelJenis() }}</td>
            <td>{{ $h->sumber_dana ?? 'BOS Reguler' }}</td>
            <td class="num">{{ $h->details->count() }}</td>
            <td class="num font-semibold">{{ rp($h->details->sum('jumlah')) }}</td>
            <td class="text-[11px] text-slate-500">{{ $h->ditinjau_oleh ? $h->ditinjau_oleh.' · '.($h->ditinjau_at?->format('d/m/Y') ?? '') : '—' }}</td>
            <td class="whitespace-nowrap text-right">
              <a href="{{ route('rkas.pdf', $h) }}" class="btn2 btn-sm">📕 PDF</a>
              <a href="{{ route('rkas.xlsx', $h) }}" class="btn2 btn-sm">📗 Excel</a>
              <a href="{{ route('rkas.show', $h) }}" class="btn2 btn-sm">Lihat</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-slate-400 py-6">Belum ada RKAS disahkan untuk tahun {{ $tahun }}.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <p class="text-[11px] text-slate-400 mt-3">Dokumen di sini adalah arsip RKAS yang telah disahkan Dinas — sumber resmi untuk pemeriksaan dan laporan pertanggungjawaban.</p>
</div>
@endsection
