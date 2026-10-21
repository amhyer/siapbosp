@extends('layouts.app', ['title' => 'Input Belanja BKU'])
@section('subtitle', 'Pencatatan pembelanjaan Non-SIPLah & SIPLah lengkap dengan identitas penyedia & kalkulator pajak ARKAS 4')
@section('content')
<form method="post" action="{{ route('belanja.store') }}" class="space-y-5" enctype="multipart/form-data">
@csrf
<div class="card space-y-4">
  <h3 class="font-semibold text-slate-700">1️⃣ Identitas Transaksi &amp; Penyedia (Sesuai ARKAS 4 Bab XV–XVI)</h3>
  <div class="grid sm:grid-cols-3 gap-4">
    <div>
      <label class="text-xs font-semibold text-slate-600">No Bukti (BKU / Kuitansi)</label>
      <input name="no_bukti" class="inp mt-1" value="{{ old('no_bukti') }}" placeholder="mis. BNU01 / KWT-001">
    </div>
    <div>
      <label class="text-xs font-semibold text-slate-600">Tanggal Transaksi</label>
      <input type="date" name="tanggal" class="inp mt-1" value="{{ old('tanggal', date('Y-m-d')) }}" required>
    </div>
    <div>
      <label class="text-xs font-semibold text-slate-600">Sumber Pengadaan</label>
      <select name="sumber" id="sumberPengadaan" class="inp mt-1" onchange="toggleSiplah()">
        <option value="langsung">Non-SIPLah (Toko / Penyedia Langsung / Honor)</option>
        <option value="siplah">SIPLah Kemendikdasmen (Otomatis Marketplace)</option>
      </select>
    </div>
    <div>
      <label class="text-xs font-semibold text-slate-600">Sumber Dana BOSP</label>
      <select name="bos_type_id" class="inp mt-1">@foreach($bosTypes as $b)<option value="{{ $b->id }}">{{ $b->nama }}</option>@endforeach</select>
    </div>
    <div>
      <label class="text-xs font-semibold text-slate-600">Tahap Penyaluran</label>
      <select name="stage_id" class="inp mt-1">@foreach($stages as $s)<option value="{{ $s->id }}">{{ $s->nama }}</option>@endforeach</select>
    </div>
    <div>
      <label class="text-xs font-semibold text-slate-600">Metode Pembayaran</label>
      <select name="metode_bayar" class="inp mt-1" id="metode" onchange="infoSaldo()">
        <option value="tunai">Tunai</option>
        <option value="transfer">Non-Tunai / Transfer Bank</option>
      </select>
      <div class="text-[11px] text-slate-500 mt-1">Saldo tersedia: <b id="infoSaldo" class="text-emerald-700"></b></div>
    </div>
    <div class="sm:col-span-3">
      <label class="text-xs font-semibold text-slate-600">Uraian / Keterangan Pembelanjaan</label>
      <input name="keterangan" class="inp mt-1" value="{{ old('keterangan') }}" placeholder="uraian belanja/pembayaran" required>
    </div>

    {{-- Detail Nota & Penyedia ala ARKAS 4 --}}
    <div>
      <label class="text-xs font-semibold text-slate-600">Nama Toko / Penyedia / Penerima</label>
      <input name="nama_toko" class="inp mt-1" value="{{ old('nama_toko') }}" placeholder="CV / Toko / Nama Penerima">
      <label class="inline-flex items-center gap-1.5 text-[11px] text-slate-500 mt-1">
        <input type="checkbox" name="usaha_perseorangan" value="1"> Usaha Perseorangan / Individu
      </label>
    </div>
    <div>
      <label class="text-xs font-semibold text-slate-600">NPWP Penyedia / NIK</label>
      <input name="npwp" class="inp mt-1" value="{{ old('npwp') }}" placeholder="15/16 digit NPWP">
      <label class="inline-flex items-center gap-1.5 text-[11px] text-amber-700 mt-1">
        <input type="checkbox" id="chkTanpaNpwp" name="tanpa_npwp" value="1"> Tidak memiliki NPWP (tarif PPh lebih tinggi)
      </label>
    </div>
    <div>
      <label class="text-xs font-semibold text-slate-600">No. Nota / Invoice &amp; Alamat</label>
      <input name="no_nota" class="inp mt-1" value="{{ old('no_nota') }}" placeholder="INV/2026/001">
    </div>

    {{-- Khusus SIPLah: Ongkos Kirim & Biaya Admin Marketplace --}}
    <div id="boxSiplah" class="hidden sm:col-span-3 grid sm:grid-cols-3 gap-3 bg-sky-50 border border-sky-200 rounded-lg p-3 text-xs">
      <div class="sm:col-span-3 font-semibold text-sky-900">🛒 Integrasi Transaksi SIPLah (Sesuai ARKAS 4 Bab XVI): Pajak otomatis dipungut/disetor oleh Mitra Pasar Daring SIPLah.</div>
      <div>
        <label class="font-semibold text-slate-600">Ongkos Kirim SIPLah (Rp)</label>
        <input type="number" min="0" value="0" name="ongkir_siplah" class="inp mt-1">
      </div>
      <div>
        <label class="font-semibold text-slate-600">Biaya Admin / Materai SIPLah (Rp)</label>
        <input type="number" min="0" value="0" name="biaya_admin_siplah" class="inp mt-1">
      </div>
      <div>
        <label class="font-semibold text-slate-600">Alamat / Mitra Pasar Daring</label>
        <input name="alamat_toko" class="inp mt-1" placeholder="mis. SIPLah Blibli / Toko Ladang">
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="flex justify-between items-center mb-3">
    <h3 class="font-semibold text-slate-700">2️⃣ Rincian Rekening Belanja</h3>
    <button type="button" class="btn2 btn" onclick="tambahRincian()">➕ Tambah Rincian</button>
  </div>
  <div class="overflow-x-auto"><table class="tbl" id="tblR">
    <thead><tr><th style="min-width:260px">Kode Rekening BOS</th><th style="min-width:200px">Uraian</th><th style="width:150px">Nilai (Rp)</th><th></th></tr></thead>
    <tbody></tbody>
    <tfoot><tr><td colspan="2" class="text-right font-semibold">TOTAL BELANJA</td><td class="num font-bold" id="grandR">Rp 0</td><td></td></tr></tfoot>
  </table></div>
</div>

<div class="card space-y-2">
  <div class="flex flex-wrap justify-between items-center gap-2">
    <div>
      <h3 class="font-semibold text-slate-700">3️⃣ Perhitungan Pajak Otomatis (ARKAS 4 v4.3.0)</h3>
      <p id="catatanPajakArkas" class="text-[11px] text-slate-500">Klik <b>⚡ Hitung Rekomendasi Pajak ARKAS 4</b> setelah mengisi rincian belanja untuk mengisi PPN (11%/12% &gt; Rp2 juta), PPh 21/22/23, atau SSPD 10% otomatis. Tarif PPN mengikuti tahun transaksi: <b>12%</b> untuk 2026 ke atas (penyesuaian PPN 12% pada ARKAS &amp; SiPLah), <b>11%</b> untuk tahun sebelumnya — nilai pajak yang sudah tersimpan tak dihitung ulang.</p>
    </div>
    <div class="flex gap-2">
      <button type="button" class="btn btn-sm bg-emerald-600" onclick="rekomendasiPajakOtomatis()">⚡ Hitung Rekomendasi Pajak ARKAS 4</button>
      <button type="button" class="btn2 btn-sm" onclick="tambahPajak()">➕ Tambah Manual</button>
    </div>
  </div>
  <div class="overflow-x-auto"><table class="tbl" id="tblP">
    <thead><tr><th style="min-width:180px">Jenis Pajak</th><th style="width:100px">Tarif %</th><th style="min-width:160px">Kode Billing</th><th style="width:150px">Nilai Pajak (Rp)</th><th></th></tr></thead>
    <tbody></tbody>
  </table></div>
</div>

<div class="card">
  <h3 class="font-semibold text-slate-700 mb-2">4️⃣ Lampiran Bukti <span class="text-xs font-normal text-slate-400">(kuitansi/foto nota — PDF/JPG/PNG, maks 4MB per berkas)</span></h3>
  <input type="file" name="lampiran[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="inp py-2">
</div>

<div class="flex gap-3">
  <button class="btn">💾 Simpan Belanja</button>
  <a href="{{ route('belanja.index') }}" class="btn2 btn">Batal</a>
</div>
</form>

<template id="tplR"><tr>
  <td><select name="rekening_id[]" class="inp rekBelanja" required><option value="">— pilih —</option>
    @foreach($rekenings as $r)<option value="{{ $r->id }}">{{ $r->kode }} · {{ Str::limit($r->nama, 45) }}</option>@endforeach
  </select></td>
  <td><input name="uraian[]" class="inp" placeholder="uraian (opsional)"></td>
  <td><input name="nilai[]" type="number" min="1" value="0" class="inp nR" oninput="hitung()"></td>
  <td><button type="button" class="badge bg-rose-50 text-rose-600" onclick="this.closest('tr').remove();hitung()">✖</button></td>
</tr></template>

<template id="tplP"><tr>
  <td><select name="pajak_id[]" class="inp pjk" onchange="tarifOtomatis(this)"><option value="">— pilih —</option>
    @foreach($pajaks as $p)<option value="{{ $p->id }}" data-kode="{{ $p->kode }}" data-tarif="{{ ['ppn'=>11,'pph22'=>1.5,'pph23'=>2,'pph42'=>10,'pph21'=>5,'sspd'=>10][$p->kode] ?? 0 }}">{{ $p->nama }}</option>@endforeach  </select></td>
  <td><input name="tarif_pajak[]" type="number" step="0.01" min="0" value="0" class="inp trf" oninput="hitungPajakRow(this)"></td>
  <td><input name="billing[]" class="inp" placeholder="kode billing"></td>
  <td><input name="nilai_pajak[]" type="number" min="0" value="0" class="inp nP"></td>
  <td><button type="button" class="badge bg-rose-50 text-rose-600" onclick="this.closest('tr').remove()">✖</button></td>
</tr></template>

<script>
const saldo = {tunai: {{ $saldo['tunai'] }}, bank: {{ $saldo['bank'] }}};
const PAJAK_ID_BY_KODE = @json($pajaks->pluck('id', 'kode'));
function fmt(n){return 'Rp '+Math.round(n).toLocaleString('id-ID');}
function infoSaldo(){ const m=document.getElementById('metode').value; document.getElementById('infoSaldo').textContent = fmt(m==='tunai'?saldo.tunai:saldo.bank); }
function toggleSiplah(){
  const isSiplah = document.getElementById('sumberPengadaan').value === 'siplah';
  document.getElementById('boxSiplah').classList.toggle('hidden', !isSiplah);
}
function tambahRincian(){document.querySelector('#tblR tbody').appendChild(document.getElementById('tplR').content.cloneNode(true));}
function tambahPajak(init){
  document.querySelector('#tblP tbody').appendChild(document.getElementById('tplP').content.cloneNode(true));
  if(init){
    const tr = document.querySelector('#tblP tbody tr:last-child');
    if(init.pajak_id) tr.querySelector('.pjk').value = String(init.pajak_id);
    tr.querySelector('.trf').value = init.tarif ?? 0;
    tr.querySelector('.nP').value = init.nilai ?? 0;
  }
}
function grandTotal(){let g=0;document.querySelectorAll('.nR').forEach(i=>g+=+i.value||0);return g;}
// Tarif PPN mengikuti tahun transaksi: 12% sejak 2026 (penyesuaian PPN 12%),
// 11% untuk tahun sebelumnya. Nilai pajak lama tidak dihitung ulang.
function tahunTransaksi(){const v=document.querySelector('input[name="tanggal"]')?.value||'';return v.slice(0,4)||String({{ (int) session('tahun', now()->year) }});}
function tarifPpn(){return parseInt(tahunTransaksi(),10)>=2026?12:11;}
function hitung(){document.getElementById('grandR').textContent=fmt(grandTotal());document.querySelectorAll('#tblP tbody tr').forEach(tr=>hitungPajakRow(tr.querySelector('.trf')));}
function tarifOtomatis(sel){const tr=sel.closest('tr');const kode=sel.selectedOptions[0].dataset.kode;const tarif=(kode==='ppn')?tarifPpn():(sel.selectedOptions[0].dataset.tarif||0);tr.querySelector('.trf').value=tarif;hitungPajakRow(tr.querySelector('.trf'));}
function hitungPajakRow(inp){const tr=inp.closest('tr');const t=+tr.querySelector('.trf').value||0;if(t>0){tr.querySelector('.nP').value=Math.round(grandTotal()*t/100);}}

async function rekomendasiPajakOtomatis(){
  const sumber = document.getElementById('sumberPengadaan').value;
  const nilai = grandTotal();
  const rekId = document.querySelector('.rekBelanja')?.value || '';
  const tanpaNpwp = document.getElementById('chkTanpaNpwp').checked ? 1 : 0;
  const res = await fetch(`{{ route('belanja.hitungPajak') }}?sumber=${sumber}&nilai=${nilai}&rekening_id=${rekId}&tanpa_npwp=${tanpaNpwp}&tahun=${tahunTransaksi()}`);
  const data = await res.json();
  document.getElementById('catatanPajakArkas').textContent = 'ℹ️ ' + (data.catatan || '');
  const tbody = document.querySelector('#tblP tbody');
  tbody.innerHTML = '';
  (data.items || []).forEach(it => {
    tambahPajak({
      pajak_id: PAJAK_ID_BY_KODE[it.kode] || '',
      tarif: it.tarif,
      nilai: it.nilai
    });
  });
}

tambahRincian(); infoSaldo(); toggleSiplah();
</script>
@endsection
