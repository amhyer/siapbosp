@extends('layouts.app', ['title' => 'Penganggaran / Kertas Kerja RKAS-BOSP'])
@section('subtitle', 'Kertas Kerja Murni, Pergeseran, & Perubahan sesuai ARKAS 4 v4.3.0 & Permendikdasmen No. 8 Tahun 2026')
@section('content')
<div class="card border-l-4 border-indigo-600 flex flex-wrap justify-between items-center gap-3">
  <div>
    <div class="font-bold text-slate-800 text-sm">📋 Aktivasi &amp; Penyusunan Kertas Kerja Tahun {{ $tahun }}</div>
    <p class="text-xs text-slate-500 mt-0.5">
      Pagu BOSP Reguler Aktif: <b class="text-slate-800">{{ rp($pagu) }}</b> · Status Sekolah: <b>{{ strtoupper($sekolah->status_sekolah ?? 'negeri') }} ({{ $sekolah->jenjang }})</b>
      @if(empty($sekolah->nama_komite))
        · <a href="{{ route('profil.edit') }}" class="text-amber-600 underline font-semibold">⚠️ Lengkapi Data Komite di Profil</a>
      @else
        · Komite: <b>{{ $sekolah->nama_komite }}</b>
      @endif
    </p>
  </div>
  <div class="flex flex-wrap gap-2">
    <a href="{{ route('perencanaan.index') }}" class="btn2 btn">🧭 Dari RKT (Pra-ARKAS)</a>
    @if($adaTahunLalu)
    <form method="post" action="{{ route('rkas.salin') }}">
      @csrf
      <button class="btn2 btn">📑 Salin Kertas Kerja Tahun {{ $tahun - 1 }}</button>
    </form>
    @endif
    <a href="{{ route('rkas.create', ['jenis' => 'murni', 'sumber' => $sumberFilter ?: 'BOS Reguler']) }}" class="btn">➕ Susun Kertas Kerja Baru</a>
    @if($aktifTerakhir)
      <a href="{{ route('rkas.create', ['jenis' => 'pergeseran', 'acuan' => $aktifTerakhir->id]) }}" class="btn2 btn">🔀 Buat Pergeseran</a>
      <a href="{{ route('rkas.create', ['jenis' => 'perubahan', 'acuan' => $aktifTerakhir->id]) }}" class="btn2 btn">♻️ Buat Perubahan</a>
    @endif
  </div>
</div>

{{-- Filter 6 Sumber Dana ARKAS 4 --}}
<div class="flex flex-wrap gap-2 text-xs">
  <a href="{{ route('rkas.index') }}" class="badge py-1.5 px-3 {{ $sumberFilter === '' ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-600' }}">Semua Sumber Dana</a>
  @foreach($sumberList as $sd => $lbl)
    <a href="{{ route('rkas.index', ['sumber' => $sd]) }}" class="badge py-1.5 px-3 {{ $sumberFilter === $sd ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-600' }}">{{ $lbl }}</a>
  @endforeach
</div>

<div class="card">
  <h3 class="font-semibold text-slate-700 text-sm mb-1">📥 Impor Kertas Kerja RKAS dari CSV</h3>
  <p class="text-[11px] text-slate-400 mb-2">Format: <code>kode_rekening;uraian;satuan;banyak;harga;sumber_dana</code> (sumber dana opsional). Berkas CSV maks. 2 MB; pemrosesan dilakukan di antrean dan statusnya dapat dipantau setelah unggah.</p>
  <form method="post" action="{{ route('rkas.impor') }}" enctype="multipart/form-data" class="flex gap-2 items-center">
    @csrf
    <input type="file" name="berkas" accept=".csv,.txt" class="inp" required>
    <button class="btn2 btn whitespace-nowrap">Unggah &amp; Buat Revisi</button>
  </form>
</div>

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
    <thead>
      <tr>
        <th>Revisi</th>
        <th>Jenis &amp; Sumber Dana</th>
        <th>No RKAS</th>
        <th>Jml Rincian</th>
        <th class="num">Total Anggaran</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      @forelse($items as $h)
      <tr>
        <td class="font-semibold">Revisi ke-{{ $h->revisi_ke }}</td>
        <td>
          <div class="font-semibold text-slate-700">{{ $h->labelJenis() }}</div>
          <div class="text-[11px] text-slate-500">{{ $sumberList[$h->sumber_dana] ?? ($h->sumber_dana ?? 'BOS Reguler') }}</div>
        </td>
        <td>{{ $h->no_rkas ?? '—' }}</td>
        <td>{{ $h->details->count() }}</td>
        <td class="num font-bold">{{ rp($h->details->sum('jumlah')) }}</td>
        <td>
          @php $b = ['draft'=>'bg-slate-100 text-slate-600','diajukan'=>'bg-amber-100 text-amber-700','disetujui'=>'bg-sky-100 text-sky-700','dikirim_dinas'=>'bg-indigo-100 text-indigo-700','revisi'=>'bg-rose-100 text-rose-700','disahkan'=>'bg-emerald-100 text-emerald-700','dikunci'=>'bg-slate-200 text-slate-600']; @endphp
          <span class="badge {{ $b[$h->status] ?? 'bg-slate-100' }}">{{ \App\Models\RkasHeader::LABEL_STATUS[$h->status] ?? $h->status }}</span>
        </td>
        <td class="text-right whitespace-nowrap">
          <a href="{{ route('rkas.salinArkas', $h) }}" class="btn2 btn" title="Mode Salin Cepat ke ARKAS 4 Desktop">📋 Salin ARKAS</a>
          <a href="{{ route('rkas.pdf', $h) }}" class="btn2 btn" title="Unduh PDF">📕</a>
          <a href="{{ route('rkas.xlsx', $h) }}" class="btn2 btn" title="Unduh Excel">📗</a>
          <a href="{{ route('rkas.show', $h) }}" class="btn2 btn">Review</a>
        </td>
      </tr>
      @empty
      <tr><td colspan="7" class="text-center text-slate-400 py-6">Belum ada Kertas Kerja RKAS. Susun dari <a href="{{ route('perencanaan.index') }}" class="text-indigo-600 underline">RKT Pra-ARKAS</a> atau klik <b>Susun Kertas Kerja Baru</b>.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
