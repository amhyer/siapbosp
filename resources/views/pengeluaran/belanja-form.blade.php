@extends('layouts.app', ['title' => 'Input Belanja'])
@section('content')
<form method="post" action="{{ route('belanja.store') }}" class="space-y-5" enctype="multipart/form-data">
@csrf
<div class="card">
  <h3 class="font-semibold text-slate-700 mb-3">1️⃣ Form Input Belanja</h3>
  <div class="grid sm:grid-cols-3 gap-4">
    <div><label class="text-xs font-semibold text-slate-600">No Bukti (ARKAS)</label><input name="no_bukti" class="inp mt-1" value="{{ old('no_bukti') }}" placeholder="No kuitansi/BKU"></div>
    <div><label class="text-xs font-semibold text-slate-600">Tanggal</label><input type="date" name="tanggal" class="inp mt-1" value="{{ old('tanggal', date('Y-m-d')) }}" required></div>
    <div><label class="text-xs font-semibold text-slate-600">Jenis BOS</label>
      <select name="bos_type_id" class="inp mt-1">@foreach($bosTypes as $b)<option value="{{ $b->id }}">{{ $b->nama }}</option>@endforeach</select></div>
    <div><label class="text-xs font-semibold text-slate-600">Tahap</label>
      <select name="stage_id" class="inp mt-1">@foreach($stages as $s)<option value="{{ $s->id }}">{{ $s->nama }}</option>@endforeach</select></div>
    <div><label class="text-xs font-semibold text-slate-600">Pembayaran</label>
      <select name="metode_bayar" class="inp mt-1" id="metode" onchange="infoSaldo()">
        <option value="tunai">Tunai</option><option value="transfer">Transfer (Bank)</option>
      </select>
      <div class="text-[11px] text-slate-500 mt-1">Tersedia: <b id="infoSaldo" class="text-emerald-700"></b></div></div>
    <div class="sm:col-span-3"><label class="text-xs font-semibold text-slate-600">Keterangan</label><input name="keterangan" class="inp mt-1" value="{{ old('keterangan') }}" placeholder="uraian belanja/pembayaran" required></div>
    <div><label class="text-xs font-semibold text-slate-600">Sumber Pengadaan</label>
      <select name="sumber" class="inp mt-1"><option value="langsung">Langsung (toko/rekanan)</option><option value="siplah">SIPLah Kemdikbud</option></select></div>
  </div>
</div>

<div class="card">
  <div class="flex justify-between items-center mb-3"><h3 class="font-semibold text-slate-700">2️⃣ Form Rincian Belanja</h3>
    <button type="button" class="btn2 btn" onclick="tambahRincian()">➕ Tambah Rincian</button></div>
  <div class="overflow-x-auto"><table class="tbl" id="tblR">
    <thead><tr><th style="min-width:240px">Kode Rekening BOS</th><th style="min-width:200px">Uraian</th><th style="width:150px">Nilai (Rp)</th><th></th></tr></thead>
    <tbody></tbody>
    <tfoot><tr><td colspan="2" class="text-right font-semibold">TOTAL BELANJA</td><td class="num font-bold" id="grandR">Rp 0</td><td></td></tr></tfoot>
  </table></div>
</div>

<div class="card">
  <div class="flex justify-between items-center mb-3"><h3 class="font-semibold text-slate-700">3️⃣ Form Terima Pajak <span class="text-xs font-normal text-slate-400">(jika ada pajak yang dipungut)</span></h3>
    <button type="button" class="btn2 btn" onclick="tambahPajak()">➕ Tambah Pajak</button></div>
  <div class="overflow-x-auto"><table class="tbl" id="tblP">
    <thead><tr><th style="min-width:170px">Rekening Pajak</th><th style="width:100px">Tarif %</th><th style="min-width:160px">Kode Billing</th><th style="width:150px">Nilai Pajak (Rp)</th><th></th></tr></thead>
    <tbody></tbody>
  </table></div>
  <p class="text-[11px] text-slate-400 mt-2">Tarif terisi otomatis (PPN 11% · PPh 22 1,5% · PPh 23 2% · PPh 4(2) 10% · PPh 21 5%) dari <b>total belanja</b> — bisa diubah manual. Pajak yang diterima wajib disetor via menu Setor Pajak (isikan NTPN).</p>
</div>

<div class="card">
  <h3 class="font-semibold text-slate-700 mb-2">4️⃣ Lampiran Bukti <span class="text-xs font-normal text-slate-400">(kuitansi/foto nota — PDF/JPG/PNG, maks 4MB per berkas)</span></h3>
  <input type="file" name="lampiran[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="inp py-2">
</div>

<div class="flex gap-3"><button class="btn">💾 Simpan Belanja</button>
  <a href="{{ route('belanja.index') }}" class="btn2 btn">Batal</a></div>
</form>

<template id="tplR"><tr>
  <td><select name="rekening_id[]" class="inp" required><option value="">— pilih —</option>
    @foreach($rekenings as $r)<option value="{{ $r->id }}">{{ $r->kode }} · {{ Str::limit($r->nama, 45) }}</option>@endforeach
  </select></td>
  <td><input name="uraian[]" class="inp" placeholder="uraian (opsional)"></td>
  <td><input name="nilai[]" type="number" min="1" value="0" class="inp nR" oninput="hitung()"></td>
  <td><button type="button" class="badge bdel" onclick="this.closest('tr').remove();hitung()">✖</button></td>
</tr></template>
<template id="tplP"><tr>
  <td><select name="pajak_id[]" class="inp pjk" onchange="tarifOtomatis(this)"><option value="">— pilih —</option>
    @foreach($pajaks as $p)<option value="{{ $p->id }}" data-tarif="{{ ['ppn'=>11,'pph22'=>1.5,'pph23'=>2,'pph42'=>10,'pph21'=>5][$p->kode] ?? 0 }}">{{ $p->nama }}</option>@endforeach
  </select></td>
  <td><input name="tarif_pajak[]" type="number" step="0.01" min="0" value="0" class="inp trf" oninput="hitungPajakRow(this)"></td>
  <td><input name="billing[]" class="inp" placeholder="kode billing"></td>
  <td><input name="nilai_pajak[]" type="number" min="0" value="0" class="inp nP"></td>
  <td><button type="button" class="badge bdel" onclick="this.closest('tr').remove()">✖</button></td>
</tr></template>
<script>
const saldo = {tunai: {{ $saldo['tunai'] }}, bank: {{ $saldo['bank'] }}};
function fmt(n){return 'Rp '+n.toLocaleString('id-ID');}
function infoSaldo(){ const m=document.getElementById('metode').value; document.getElementById('infoSaldo').textContent = fmt(m==='tunai'?saldo.tunai:saldo.bank); }
function tambahRincian(){document.querySelector('#tblR tbody').appendChild(document.getElementById('tplR').content.cloneNode(true));}
function tambahPajak(){document.querySelector('#tblP tbody').appendChild(document.getElementById('tplP').content.cloneNode(true));}
function grandTotal(){let g=0;document.querySelectorAll('.nR').forEach(i=>g+=+i.value||0);return g;}
function hitung(){document.getElementById('grandR').textContent=fmt(grandTotal());document.querySelectorAll('#tblP tbody tr').forEach(tr=>hitungPajakRow(tr.querySelector('.trf')));}
function tarifOtomatis(sel){const tr=sel.closest('tr');tr.querySelector('.trf').value=sel.selectedOptions[0].dataset.tarif||0;hitungPajakRow(tr.querySelector('.trf'));}
function hitungPajakRow(inp){const tr=inp.closest('tr');const t=+tr.querySelector('.trf').value||0;if(t>0){tr.querySelector('.nP').value=Math.round(grandTotal()*t/100);}}
tambahRincian(); infoSaldo();
</script>
@endsection
