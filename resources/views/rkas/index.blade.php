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
@include('rkas._juknis', ['juknis' => $juknis])
@if($rekapSumber->isNotEmpty())
<div class="card">
  <h3 class="font-semibold text-slate-700 text-sm mb-1">💼 Anggaran per sumber dana <span class="text-[11px] font-normal text-slate-400">(revisi terbaru per sumber)</span></h3>
  <div class="flex flex-wrap gap-2">
    @foreach($rekapSumber as $sumber => $r)
      <div class="border border-slate-200 rounded-lg px-3 py-2 min-w-[11rem]">
        <div class="text-[11px] text-slate-500">{!! \App\Support\SumberDana::badge($sumber) !!}</div>
        <div class="font-semibold text-slate-700">{{ rp($r['nilai']) }}</div>
        <div class="text-[11px] text-slate-400">{{ $r['baris'] }} baris rincian</div>
      </div>
    @endforeach
  </div>
  @if($rekapSumber->keys()->contains(fn ($s) => \App\Support\SumberDana::kunci($s) === 'afirmasi'))
    <p class="text-[11px] text-violet-700 mt-2">🌟 Tersedia sumber <b>BOSP Afirmasi</b> (ARKAS 4.2.16+, Permendikdasmen 8/2026 Pasal 46) — kode kegiatan khusus 06.33.x (Penguatan akses) &amp; 03.34.x (Penguatan mutu).</p>
  @endif
</div>
@endif
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
