@extends('layouts.app', ['title' => 'RKAS-BOSP'])
@section('content')
<div class="flex flex-wrap justify-between items-center gap-2">
  <p class="text-sm text-slate-500">Rencana Kegiatan dan Anggaran Sekolah dari Dana BOSP — Tahun {{ $tahun }}.</p>
  <a href="{{ route('rkas.create') }}" class="btn">➕ Susun RKAS Baru / Revisi</a>
</div>
<div class="card">
  <h3 class="font-semibold text-slate-700 text-sm mb-1">📥 Impor RKAS dari CSV</h3>
  <p class="text-[11px] text-slate-400 mb-2">Format: <code>kode_rekening;uraian;satuan;banyak;harga;sumber_dana</code> (sumber dana opsional).</p>
  <form method="post" action="{{ route('rkas.impor') }}" enctype="multipart/form-data" class="flex gap-2 items-center">
    @csrf
    <input type="file" name="berkas" accept=".csv,.txt" class="inp" required>
    <button class="btn2 btn whitespace-nowrap">Unggah & Buat Revisi</button>
  </form>
</div>
<div class="card overflow-x-auto">
  <table class="tbl">
    <thead><tr><th>Revisi</th><th>No RKAS</th><th>Jml Rincian</th><th class="num">Total</th><th>Status</th><th></th></tr></thead>
    <tbody>
      @forelse($items as $h)
      <tr>
        <td>Revisi ke-{{ $h->revisi_ke }}</td>
        <td>{{ $h->no_rkas ?? '—' }}</td>
        <td>{{ $h->details->count() }}</td>
        <td class="num">{{ rp($h->details->sum('jumlah')) }}</td>
        <td>@php $b = ['draft'=>'bg-slate-100 text-slate-600','diajukan'=>'bg-amber-100 text-amber-700','disetujui'=>'bg-sky-100 text-sky-700','dikirim_dinas'=>'bg-indigo-100 text-indigo-700','revisi'=>'bg-rose-100 text-rose-700','disahkan'=>'bg-emerald-100 text-emerald-700','dikunci'=>'bg-slate-200 text-slate-600']; @endphp<span class="badge {{ $b[$h->status] ?? 'bg-slate-100' }}">{{ \App\Models\RkasHeader::LABEL_STATUS[$h->status] ?? $h->status }}</span></td>
        <td class="text-right whitespace-nowrap">
          <a href="{{ route('rkas.pdf', $h) }}" class="btn2 btn" title="Unduh PDF">📕</a>
          <a href="{{ route('rkas.xlsx', $h) }}" class="btn2 btn" title="Unduh Excel">📗</a>
          <a href="{{ route('rkas.show', $h) }}" class="btn2 btn">Lihat</a></td>
      </tr>
      @empty
      <tr><td colspan="6" class="text-center text-slate-400 py-6">Belum ada RKAS.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
