@extends('layouts.app', ['title' => 'Referensi'])
@section('content')
@php
  $tabAktif = request('tab', 'rek');
  $tabs = [
    'rek' => 'Rekening Belanja & Pendapatan',
    'pjk' => 'Rekening Pajak',
    'program' => 'Program BOSP',
    'kegiatan' => 'Kegiatan (Kode ARKAS)',
    'satuan' => 'Satuan',
    'uraian' => 'Uraian',
    'ptk' => 'PTK',
    'lain' => 'Jenis BOS & Tahap',
  ];
@endphp
<div class="card">
  <div class="flex flex-wrap gap-2 text-sm mb-4" id="tabs">
    @foreach($tabs as $k => $label)
      <button data-t="{{ $k }}" class="btn2 btn {{ $tabAktif === $k ? '!bg-indigo-600 !text-white' : '' }}">{{ $label }}</button>
    @endforeach
  </div>

  <div id="t-rek" class="{{ $tabAktif === 'rek' ? '' : 'hidden' }}">
    <p class="text-[11px] text-slate-500 mb-2">Master rekening belanja &amp; pendapatan. Perubahan data master dilakukan Dinas (Master Data).</p>
    <table class="tbl"><thead><tr><th>Kode</th><th>Nama Rekening</th><th>Tipe</th><th>Program/Komponen</th></tr></thead>
    <tbody>@foreach($rekenings as $r)<tr><td class="font-mono">{{ $r->kode }}</td><td>{{ $r->nama }}</td><td><span class="badge {{ $r->tipe==='pendapatan'?'bg-emerald-100 text-emerald-700':'bg-rose-100 text-rose-700' }}">{{ $r->tipe }}</span></td><td>{{ $r->komponen->nama ?? '—' }}</td></tr>@endforeach</tbody></table>
  </div>

  <div id="t-pjk" class="{{ $tabAktif === 'pjk' ? '' : 'hidden' }}">
    <table class="tbl"><thead><tr><th>Kode</th><th>Nama Pajak</th></tr></thead>
    <tbody>@foreach($pajaks as $p)<tr><td class="font-mono">{{ $p->kode }}</td><td>{{ $p->nama }}</td></tr>@endforeach</tbody></table>
  </div>

  <div id="t-program" class="{{ $tabAktif === 'program' ? '' : 'hidden' }}">
    <p class="text-[11px] text-slate-500 mb-2">Program = komponen penggunaan dana BOSP (ARKAS: Program &gt; Kegiatan &gt; Sub-kegiatan). Sesuai Juknis 8/2026,emblerالية "Penerimaan Murid Baru".</p>
    <table class="tbl"><thead><tr><th>Kode</th><th>Nama Program</th></tr></thead>
    <tbody>@foreach($komponens as $k)<tr><td class="font-mono">{{ $k->kode }}</td><td>{{ $k->nama }}</td></tr>@endforeach</tbody></table>
  </div>

  <div id="t-kegiatan" class="{{ $tabAktif === 'kegiatan' ? '' : 'hidden' }}">
    <div class="flex flex-wrap items-end gap-2 mb-2">
      <form method="get" action="{{ route('referensi') }}" class="flex gap-2 items-end">
        <input type="hidden" name="tab" value="kegiatan">
        <div><label class="lbl">Cari kegiatan</label><input name="cari" value="{{ request('cari', '') }}" class="inp" placeholder="kode atau uraian"></div>
        <button class="btn2 btn">🔍 Cari</button>
      </form>
      <a href="{{ route('referensi', ['tab' => 'kegiatan']) }}" class="btn2 btn-sm">Reset</a>
      <span class="text-[11px] text-slate-500">{{ $kegiatan->total() }} kode kegiatan (ARKAS Ref_Kode)</span>
    </div>
    <table class="tbl"><thead><tr><th>Kode</th><th>Uraian</th><th>Level</th></tr></thead>
    <tbody>
      @forelse($kegiatan as $k)
        <tr><td class="font-mono">{{ $k->kode }}</td><td>{{ $k->uraian }}</td><td>{{ $k->level }}</td></tr>
      @empty
        <tr><td colspan="3" class="text-slate-400 text-center py-4">Tidak ada kode kegiatan yang cocok.</td></tr>
      @endforelse
    </tbody></table>
    @if($kegiatan->hasPages())
      <div class="mt-3">{{ $kegiatan->links() }}</div>
    @endif
  </div>

  <div id="t-satuan" class="{{ $tabAktif === 'satuan' ? '' : 'hidden' }}">
    <p class="text-[11px] text-slate-500 mb-2">Master satuan — diimpor otomatis dari ARKAS saat data ditarik.</p>
    <table class="tbl"><thead><tr><th>Satuan</th><th>Unit</th></tr></thead>
    <tbody>@forelse($satuans as $s)<tr><td>{{ $s->nama }}</td><td>{{ $s->unit ?? '—' }}</td></tr>@empty
      <tr><td colspan="2" class="text-slate-400 text-center py-4">Belum ada master satuan. Tarik/import data ARKAS untuk mengisinya.</td></tr>@endforelse</tbody></table>
  </div>

  <div id="t-uraian" class="{{ $tabAktif === 'uraian' ? '' : 'hidden' }}">
    <p class="text-[11px] text-slate-500 mb-2">Master uraian — dikumpulkan dari rincian RKAS hasil impor ARKAS.</p>
    <table class="tbl"><thead><tr><th>Uraian</th><th class="num">Dipakai</th></tr></thead>
    <tbody>@forelse($uraian as $u)
      <tr><td>{{ $u->uraian }}</td><td class="num">{{ $u->dipakai }}</td></tr>
    @empty
      <tr><td colspan="2" class="text-slate-400 text-center py-4">Belum ada master uraian.</td></tr>@endforelse</tbody></table>
  </div>

  <div id="t-ptk" class="{{ $tabAktif === 'ptk' ? '' : 'hidden' }}">
    <p class="text-[11px] text-slate-500 mb-2">Data PTK (Pendidik &amp; Tensional Kependidikan) dari ARKAS — dipakai untuk acuan honor &amp; cut-off.</p>
    <table class="tbl"><thead><tr><th>Nama</th><th>JK</th><th class="num">Masa Kerja</th><th>NUPTK</th><th>Sertifikasi</th><th>Cut-off</th></tr></thead>
    <tbody>@forelse($ptks as $p)
      <tr><td>{{ $p->nama }}</td><td>{{ $p->jenis_kelamin }}</td>
        <td class="num">{{ $p->masa_kerja_tahun }} th {{ $p->masa_kerja_bulan }} bln</td>
        <td class="font-mono text-[11px]">{{ $p->nuptk ?? '—' }}</td>
        <td>{{ $p->pernah_sertifikasi ? '✅ Ya' : '—' }}</td>
        <td>{{ $p->is_cut_off ? '⚠️ Ya' : '—' }}</td></tr>
    @empty
      <tr><td colspan="6" class="text-slate-400 text-center py-4">Belum ada data PTK. Tarik/import data ARKAS untuk mengisinya.</td></tr>@endforelse</tbody></table>
  </div>

  <div id="t-lain" class="{{ $tabAktif === 'lain' ? '' : 'hidden' }} grid sm:grid-cols-2 gap-6">
    <div><h4 class="font-semibold mb-2 text-sm">Jenis BOS</h4>
      <table class="tbl"><tbody>@foreach($bosTypes as $b)<tr><td class="font-mono">{{ $b->kode }}</td><td>{{ $b->nama }}</td></tr>@endforeach</tbody></table></div>
    <div><h4 class="font-semibold mb-2 text-sm">Tahap Penyaluran</h4>
      <table class="tbl"><tbody>@foreach($stages as $s)<tr><td>{{ $s->tahun }}</td><td>{{ $s->nama }}</td><td>{{ $s->is_open ? '🟢 buka' : '🔴 tutup' }}</td></tr>@endforeach</tbody></table></div>
  </div>
</div>
<script>
document.querySelectorAll('#tabs [data-t]').forEach(b=>b.addEventListener('click',()=>{
  document.querySelectorAll('#tabs [data-t]').forEach(x=>x.classList.remove('!bg-indigo-600','!text-white'));
  b.classList.add('!bg-indigo-600','!text-white');
  document.querySelectorAll('div[id^="t-"]').forEach(p=>p.classList.add('hidden'));
  document.getElementById('t-'+b.dataset.t).classList.remove('hidden');
}));
</script>
@endsection
