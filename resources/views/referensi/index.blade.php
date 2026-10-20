@extends('layouts.app', ['title' => 'Referensi'])
@section('content')
<div class="card">
  <div class="flex gap-2 text-sm mb-4" id="tabs">
    <button data-t="rek" class="btn2 btn">Kode Rekening</button>
    <button data-t="pjk" class="btn2 btn">Rekening Pajak</button>
    <button data-t="kom" class="btn2 btn">Komponen BOSP</button>
    <button data-t="lain" class="btn2 btn">Jenis BOS & Tahap</button>
  </div>
  <div id="t-rek">
    <table class="tbl"><thead><tr><th>Kode</th><th>Nama Rekening</th><th>Tipe</th><th>Komponen</th></tr></thead>
    <tbody>@foreach($rekenings as $r)<tr><td class="font-mono">{{ $r->kode }}</td><td>{{ $r->nama }}</td><td><span class="badge {{ $r->tipe==='pendapatan'?'bg-emerald-100 text-emerald-700':'bg-rose-100 text-rose-700' }}">{{ $r->tipe }}</span></td><td>{{ $r->komponen->nama ?? '—' }}</td></tr>@endforeach</tbody></table>
  </div>
  <div id="t-pjk" class="hidden">
    <table class="tbl"><thead><tr><th>Kode</th><th>Nama Pajak</th></tr></thead>
    <tbody>@foreach($pajaks as $p)<tr><td class="font-mono">{{ $p->kode }}</td><td>{{ $p->nama }}</td></tr>@endforeach</tbody></table>
  </div>
  <div id="t-kom" class="hidden">
    <table class="tbl"><thead><tr><th>Kode</th><th>Komponen BOSP</th></tr></thead>
    <tbody>@foreach($komponens as $k)<tr><td class="font-mono">{{ $k->kode }}</td><td>{{ $k->nama }}</td></tr>@endforeach</tbody></table>
  </div>
  <div id="t-lain" class="hidden grid sm:grid-cols-2 gap-6">
    <div><h4 class="font-semibold mb-2 text-sm">Jenis BOS</h4>
      <table class="tbl"><tbody>@foreach($bosTypes as $b)<tr><td class="font-mono">{{ $b->kode }}</td><td>{{ $b->nama }}</td></tr>@endforeach</tbody></table></div>
    <div><h4 class="font-semibold mb-2 text-sm">Tahap Penyaluran</h4>
      <table class="tbl"><tbody>@foreach($stages as $s)<tr><td>{{ $s->tahun }}</td><td>{{ $s->nama }}</td><td>{{ $s->is_open ? '🟢 buka' : '🔴 tutup' }}</td></tr>@endforeach</tbody></table></div>
  </div>
</div>
<script>
document.querySelectorAll('#tabs [data-t]').forEach(b=>b.addEventListener('click',()=>{
  ['rek','pjk','kom','lain'].forEach(t=>document.getElementById('t-'+t).classList.add('hidden'));
  document.getElementById('t-'+b.dataset.t).classList.remove('hidden');
}));
</script>
@endsection
