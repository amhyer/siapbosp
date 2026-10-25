@extends('layouts.app', ['title' => 'Pendapatan'])
@section('content')
<div class="grid lg:grid-cols-3 gap-5">
<div class="card">
  <div class="flex justify-between text-xs text-slate-500 mb-3"><span>Saldo Bank:</span><b class="text-sky-700">{{ rp($saldo['bank']) }}</b></div>
  <h3 class="font-semibold text-slate-700 mb-3">Input Pendapatan</h3>
  <form method="post" action="{{ route('pendapatan.store') }}" class="space-y-3">
    @csrf
    <div><label class="text-xs font-semibold text-slate-600">Tanggal Transaksi</label><input type="date" name="tanggal" class="inp mt-1" value="{{ date('Y-m-d') }}" required></div>
    <div><label class="text-xs font-semibold text-slate-600">Jenis BOS</label>
      <select name="bos_type_id" class="inp mt-1">@foreach($bosTypes as $b)<option value="{{ $b->id }}">{{ $b->nama }}</option>@endforeach</select></div>
    <div><label class="text-xs font-semibold text-slate-600">Tahap</label>
      <select name="stage_id" class="inp mt-1">@foreach($stages as $s)<option value="{{ $s->id }}">{{ $s->nama }}</option>@endforeach</select></div>
    <div><label class="text-xs font-semibold text-slate-600">Keterangan</label><input name="keterangan" class="inp mt-1" placeholder="mis. Pencairan Tahap 1" required></div>
    <div class="border-t pt-3">
      <div class="flex justify-between items-center mb-2"><span class="text-xs font-semibold text-slate-600">Rincian</span>
        <button type="button" class="badge bdan" data-tambah-rincian>➕ Tambah Rincian</button></div>
      <div id="rows" class="space-y-2"></div>
      <div class="flex justify-between text-sm font-bold mt-3"><span>Total</span><span id="grand">Rp 0</span></div>
    </div>
    <button class="btn w-full justify-center">💾 Simpan (masuk Kas Bank)</button>
  </form>
</div>
<div class="card">
  <h3 class="font-semibold text-slate-700 text-sm mb-2">💰 Bunga Bank</h3>
  <p class="text-[11px] text-slate-400 mb-2 leading-snug">Penerimaan insidental dari bunga rekening sekolah — di luar RKAS, langsung masuk Kas Bank. Ikut mengangkat saldo dan menjadi bagian SiLPA akhir tahun.</p>
  <form method="post" action="{{ route('pendapatan.bunga') }}" class="space-y-2">
    @csrf
    <input type="date" name="tanggal" class="inp text-sm" value="{{ date('Y-m-d') }}" required>
    <select name="bos_type_id" class="inp text-sm">@foreach($bosTypes as $b)<option value="{{ $b->id }}">{{ $b->nama }}</option>@endforeach</select>
    <select name="stage_id" class="inp text-sm">@foreach($stages as $s)<option value="{{ $s->id }}">{{ $s->nama }}</option>@endforeach</select>
    <input type="number" name="nilai" min="1" step="1" class="inp text-sm" placeholder="Nilai bunga (Rp)" required>
    <button class="btn btn-sm w-full justify-center">💰 Catat Bunga Bank</button>
  </form>
</div>
<div class="card lg:col-span-2">
  @php $kelebihan = $pagu > 0 && $diterimaTahun > $pagu; @endphp
  <div class="rounded-lg p-3 mb-3 text-xs @if($kelebihan) bg-rose-50 border border-rose-200 @else bg-slate-50 border border-slate-200 @endif">
    <div class="grid grid-cols-3 gap-2 text-center">
      <div><div class="text-slate-500">Pagu BOSP {{ $tahun }}</div><div class="font-bold text-sm">{{ rp($pagu) }}</div></div>
      <div><div class="text-slate-500">Diterima {{ $tahun }}</div><div class="font-bold text-sm {{ $kelebihan ? 'text-rose-700' : 'text-emerald-700' }}">{{ rp($diterimaTahun) }}</div></div>
      <div><div class="text-slate-500">SILPA Tahun Lalu</div><div class="font-bold text-sm">{{ rp($silpa) }}</div></div>
    </div>
    @if ($kelebihan)
      <div class="mt-2 text-rose-700 font-semibold">⚠️ Total penerimaan melewati pagu — cek penyaluran/konfirmasi dana di Dinas.</div>
    @endif
  </div>
  <h3 class="font-semibold text-slate-700 mb-3">Riwayat Pendapatan {{ $tahun }}</h3>
  <form method="get" class="flex gap-2 mb-3">
    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="🔍 Cari keterangan / no bukti..." class="inp !py-1.5 text-sm w-64">
    <button class="btn2 btn text-sm">Cari</button>
    @if(!empty($q))<a href="{{ route('pendapatan.index') }}" class="text-xs text-slate-400 hover:text-rose-500 self-center">✖ reset</a>@endif
  </form>
  <table class="tbl">
    <thead><tr><th>BKU</th><th>Tanggal</th><th>Uraian</th><th>Rincian</th><th class="num">Nilai</th><th></th></tr></thead>
    <tbody>
      @forelse($items as $t)
      <tr>
        <td>#{{ $t->no_bku }}</td><td>{{ $t->tanggal->format('d/m/Y') }}</td><td>{{ $t->keterangan }}</td>
        <td class="text-xs text-slate-500">@foreach($t->details as $d)<div>{{ $d->rekening->kode ?? '' }} — {{ rp($d->nilai) }}</div>@endforeach</td>
        <td class="num font-semibold text-emerald-700">{{ rp($t->total) }}</td>
        <td><form method="post" action="{{ route('penerimaan.destroy', $t) }}" data-konfirmasi="Hapus pendapatan ini?">@csrf @method('DELETE')<button class="badge bdel">✖</button></form></td>
      </tr>
      @empty
      <tr><td colspan="6" class="text-center text-slate-400 py-6">Belum ada pendapatan.</td></tr>
      @endforelse
    </tbody>
  </table>
  {{ $items->links() }}
</div>
</div>
<template id="tpl">
  <div class="flex gap-2">
    <select name="rekening_id[]" class="inp" required>@foreach($rekenings as $r)<option value="{{ $r->id }}">{{ $r->kode }} · {{ $r->nama }}</option>@endforeach</select>
    <input name="nilai[]" type="number" min="1" value="0" class="inp nilai" style="width:150px">
    <button type="button" class="badge bdel" data-hapus-rincian>✖</button>
  </div>
</template>
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
function tambah(){document.getElementById('rows').appendChild(document.getElementById('tpl').content.cloneNode(true));}
function hitung(){let g=0;document.querySelectorAll('.nilai').forEach(i=>g+=+i.value||0);document.getElementById('grand').textContent='Rp '+g.toLocaleString('id-ID');}
// Delegasi event menggantikan atribut on* inline (audit R-03). Baris rincian
// dibuat dari <template>, jadi listener dipasang di document.
document.addEventListener('click', function (ev) {
  const tambahBaru = ev.target.closest('[data-tambah-rincian]');
  if (tambahBaru) { tambah(); return; }
  const hapus = ev.target.closest('[data-hapus-rincian]');
  if (hapus) { hapus.parentElement.remove(); hitung(); }
});
document.addEventListener('input', function (ev) {
  if (ev.target.classList.contains('nilai')) hitung();
});
tambah();
</script>
@endsection
