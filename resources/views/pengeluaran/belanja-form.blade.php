@extends('layouts.app', ['title' => 'Input Belanja BKU'])
@section('subtitle', 'Pencatatan pembelanjaan Non-SIPLah & SIPLah lengkap dengan identitas penyedia & kalkulator pajak ARKAS 4')
@section('content')
<form method="post" action="{{ route('belanja.store') }}" class="space-y-5" enctype="multipart/form-data" id="formBelanja" data-wizard>
@csrf

{{-- Langkah wizard mengikuti ARKAS 4: Detail Transaksi -> Kegiatan & Rincian -> Perhitungan Pajak --}}
<div class="card">
  <ol class="flex flex-wrap items-center gap-2 text-xs" role="tablist" aria-label="Langkah input belanja">
    <li role="presentation">
      <button type="button" role="tab" data-langkah-tombol="1" id="tab-langkah-1" aria-selected="true" aria-controls="langkah-1"
              class="wizard-step on flex items-center gap-2 rounded-full border border-indigo-600 bg-white px-3 py-1.5 font-semibold text-indigo-700">
        <span class="wizard-num inline-flex h-5 w-5 items-center justify-center rounded-full bg-slate-100 text-[11px] font-bold text-slate-500" aria-hidden="true">1</span> Detail Transaksi
      </button>
    </li>
    <li role="presentation">
      <button type="button" role="tab" data-langkah-tombol="2" id="tab-langkah-2" aria-selected="false" aria-controls="langkah-2"
              class="wizard-step flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-slate-500">
        <span class="wizard-num inline-flex h-5 w-5 items-center justify-center rounded-full bg-slate-100 text-[11px] font-bold text-slate-500" aria-hidden="true">2</span> Kegiatan &amp; Rincian
      </button>
    </li>
    <li role="presentation">
      <button type="button" role="tab" data-langkah-tombol="3" id="tab-langkah-3" aria-selected="false" aria-controls="langkah-3"
              class="wizard-step flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-slate-500">
        <span class="wizard-num inline-flex h-5 w-5 items-center justify-center rounded-full bg-slate-100 text-[11px] font-bold text-slate-500" aria-hidden="true">3</span> Perhitungan Pajak
      </button>
    </li>
  </ol>
  <noscript><style>.js-langkah.hidden{display:block!important}.js-simpan[hidden]{display:inline-flex!important}</style></noscript>
</div>

<div role="tabpanel" id="langkah-1" data-langkah="1" aria-labelledby="tab-langkah-1">
<div class="card space-y-4">
  <h3 class="font-semibold text-slate-700">Detail Transaksi &amp; Penyedia <span class="text-xs font-normal text-slate-400">(ARKAS 4 Bab XV–XVI)</span></h3>
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
      <select name="sumber" id="sumberPengadaan" class="inp mt-1">
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
      <select name="metode_bayar" class="inp mt-1" id="metode">
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

  {{-- Lampiran bukti ikut langkah 1: bukti transaksi (kuitansi/nota). --}}
  <div class="border-t border-slate-100 pt-3">
    <h4 class="font-semibold text-slate-700 text-[13px] mb-2">📎 Lampiran Bukti
      <span class="text-xs font-normal text-slate-400">(kuitansi/foto nota — PDF/JPG/PNG, maks 4MB per berkas)</span>
    </h4>
    <input type="file" name="lampiran[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="inp py-2">
  </div>
</div>
</div>{{-- /langkah 1 --}}

<div role="tabpanel" id="langkah-2" data-langkah="2" aria-labelledby="tab-langkah-2" class="js-langkah hidden">
<div class="card">
  <div class="flex justify-between items-center mb-3">
    <h3 class="font-semibold text-slate-700">Kegiatan &amp; Rincian Belanja</h3>
    <button type="button" class="btn2 btn" data-tambah-rincian>➕ Tambah Rincian</button>
  </div>
  <div class="overflow-x-auto"><table class="tbl" id="tblR">
    <thead><tr><th style="min-width:260px">Kode Rekening BOS</th><th style="min-width:200px">Uraian</th><th style="width:150px">Nilai (Rp)</th><th></th></tr></thead>
    <tbody></tbody>
    <tfoot><tr><td colspan="2" class="text-right font-semibold">TOTAL BELANJA</td><td class="num font-bold" id="grandR">Rp 0</td><td></td></tr></tfoot>
  </table></div>
</div>
</div>{{-- /langkah 2 --}}

<div role="tabpanel" id="langkah-3" data-langkah="3" aria-labelledby="tab-langkah-3" class="js-langkah hidden">
<div class="card space-y-2">
  <div class="flex flex-wrap justify-between items-center gap-2">
    <div>
      <h3 class="font-semibold text-slate-700">Perhitungan Pajak Otomatis <span class="text-xs font-normal text-slate-400">(ARKAS 4 v4.3.0)</span></h3>
      <p id="catatanPajakArkas" class="text-[11px] text-slate-500">Klik <b>⚡ Hitung Rekomendasi Pajak ARKAS 4</b> setelah mengisi rincian belanja untuk mengisi PPN (11%/12% &gt; Rp2 juta), PPh 21/22/23, atau SSPD 10% otomatis. Tarif PPN mengikuti tahun transaksi: <b>12%</b> untuk 2026 ke atas (penyesuaian PPN 12% pada ARKAS &amp; SiPLah), <b>11%</b> untuk tahun sebelumnya — nilai pajak yang sudah tersimpan tak dihitung ulang.</p>
    </div>
    <div class="flex gap-2">
      <button type="button" class="btn btn-sm bg-emerald-600" data-rekomendasi-pajak>⚡ Hitung Rekomendasi Pajak ARKAS 4</button>
      <button type="button" class="btn2 btn-sm" data-tambah-pajak>➕ Tambah Manual</button>
    </div>
  </div>
  <div class="overflow-x-auto"><table class="tbl" id="tblP">
    <thead><tr><th style="min-width:180px">Jenis Pajak</th><th style="width:100px">Tarif %</th><th style="min-width:160px">Kode Billing</th><th style="width:150px">Nilai Pajak (Rp)</th><th></th></tr></thead>
    <tbody></tbody>
  </table></div>
</div>

{{-- Ringkasan sebelum menyimpan (nilai dihitung ulang oleh JS) --}}
<div class="card bg-slate-50">
  <h4 class="font-semibold text-slate-700 text-[13px] mb-2">🧾 Ringkasan</h4>
  <dl class="grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
    <dt class="text-slate-500">Total belanja</dt>
    <dd class="font-bold text-slate-800" id="ringkasBelanja">Rp 0</dd>
    <dt class="text-slate-500">Pajak dipungut</dt>
    <dd class="font-bold text-slate-800" id="ringkasPajak">Rp 0</dd>
    <dt class="text-slate-500">Sumber</dt>
    <dd class="font-semibold text-slate-700" id="ringkasSumber">Non-SIPLah</dd>
    <dt class="text-slate-500">Metode bayar</dt>
    <dd class="font-semibold text-slate-700" id="ringkasMetode">Tunai</dd>
  </dl>
</div>
</div>{{-- /langkah 3 --}}

<div class="card flex flex-wrap items-center justify-between gap-2" data-wizard-nav>
  <button type="button" class="btn2 btn" data-langkah-mundur>← Kembali</button>
  <span class="text-xs text-slate-500" id="wizardPosisi" aria-live="polite">Langkah 1 dari 3</span>
  <div class="flex flex-wrap gap-2">
    <button type="button" class="btn" data-langkah-maju>Lanjut: Kegiatan &amp; Rincian →</button>
    <a href="{{ route('belanja.index') }}" class="btn2 btn" data-wizard-batal>Batal</a>
    <button class="btn js-simpan" data-wizard-simpan hidden>💾 Simpan Belanja</button>
  </div>
</div>

<p class="text-[11px] text-slate-400" id="wizardPesan" role="status" aria-live="polite"></p>
</form>

<template id="tplR"><tr>
  <td><select name="rekening_id[]" class="inp rekBelanja" required><option value="">— pilih —</option>
    @foreach($rekenings as $r)<option value="{{ $r->id }}">{{ $r->kode }} · {{ Str::limit($r->nama, 45) }}</option>@endforeach
  </select></td>
  <td><input name="uraian[]" class="inp" placeholder="uraian (opsional)"></td>
  <td><input name="nilai[]" type="number" min="1" value="0" class="inp nR"></td>
  <td><button type="button" class="badge bg-rose-50 text-rose-600" data-hapus-rincian>✖</button></td>
</tr></template>

<template id="tplP"><tr>
  <td><select name="pajak_id[]" class="inp pjk"><option value="">— pilih —</option>
    @foreach($pajaks as $p)<option value="{{ $p->id }}" data-kode="{{ $p->kode }}" data-tarif="{{ ['ppn'=>11,'pph22'=>1.5,'pph23'=>2,'pph42'=>10,'pph21'=>5,'sspd'=>10][$p->kode] ?? 0 }}">{{ $p->nama }}</option>@endforeach  </select></td>
  <td><input name="tarif_pajak[]" type="number" step="0.01" min="0" value="0" class="inp trf"></td>
  <td><input name="billing[]" class="inp" placeholder="kode billing"></td>
  <td><input name="nilai_pajak[]" type="number" min="0" value="0" class="inp nP"></td>
  <td><button type="button" class="badge bg-rose-50 text-rose-600" data-hapus-baris>✖</button></td>
</tr></template>

<script nonce="{{ request()->attributes->get('csp_nonce') }}">
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

// Delegasi event menggantikan atribut on* inline (audit R-03). Baris rincian
// dan pajak dibuat dari <template>, jadi listener dipasang di document.
document.addEventListener('click', function (ev) {
  let el;
  if ((el = ev.target.closest('[data-tambah-rincian]'))) { tambahRincian(); return; }
  if ((el = ev.target.closest('[data-tambah-pajak]'))) { tambahPajak(); return; }
  if ((el = ev.target.closest('[data-rekomendasi-pajak]'))) { rekomendasiPajakOtomatis(); return; }
  if ((el = ev.target.closest('[data-hapus-baris]'))) { el.closest('tr').remove(); return; }
  if ((el = ev.target.closest('[data-hapus-rincian]'))) { el.closest('tr').remove(); hitung(); }
});
document.addEventListener('input', function (ev) {
  if (ev.target.classList.contains('nR')) { hitung(); return; }
  if (ev.target.classList.contains('trf')) hitungPajakRow(ev.target);
});
document.addEventListener('change', function (ev) {
  if (ev.target.classList.contains('pjk')) tarifOtomatis(ev.target);
});
document.getElementById('sumberPengadaan').addEventListener('change', toggleSiplah);
document.getElementById('metode').addEventListener('change', infoSaldo);

tambahRincian(); infoSaldo(); toggleSiplah();
</script>

{{-- Wizard 3 langkah: hanya tampilan. Seluruh field tetap di dalam SATU form,
     jadi tombol Simpan mengirim POST yang sama seperti sebelumnya. --}}
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(function () {
  const form = document.getElementById('formBelanja');
  if (!form) return;

  const JUDUL = { 1: 'Detail Transaksi', 2: 'Kegiatan & Rincian', 3: 'Perhitungan Pajak' };
  const MAJU  = { 1: 'Lanjut: Kegiatan & Rincian →', 2: 'Lanjut: Perhitungan Pajak →', 3: '' };
  const TOTAL = 3;
  let langkah = 1;

  const panel = (n) => form.querySelector('[data-langkah="' + n + '"]');
  const tombol = (n) => form.querySelector('[data-langkah-tombol="' + n + '"]');
  const btnMaju = form.querySelector('[data-langkah-maju]');
  const btnMundur = form.querySelector('[data-langkah-mundur]');
  const btnSimpan = form.querySelector('[data-wizard-simpan]');
  const posisi = document.getElementById('wizardPosisi');
  const pesan = document.getElementById('wizardPesan');

  function kelasTombol(n, aktif, sudahDilewati) {
    const el = tombol(n);
    if (!el) return;
    el.classList.toggle('on', aktif);
    el.classList.toggle('border-indigo-600', aktif);
    el.classList.toggle('text-indigo-700', aktif);
    el.classList.toggle('bg-white', true);
    el.classList.toggle('border-slate-200', !aktif);
    el.classList.toggle('text-slate-500', !aktif);
    el.classList.toggle('font-semibold', aktif);
    el.setAttribute('aria-selected', aktif ? 'true' : 'false');
    const num = el.querySelector('.wizard-num');
    if (num) {
      num.classList.toggle('bg-indigo-600', aktif);
      num.classList.toggle('text-white', aktif);
      num.classList.toggle('bg-emerald-100', !aktif && sudahDilewati);
      num.classList.toggle('text-emerald-700', !aktif && sudahDilewati);
      // Status netral: kembalikan gaya dasar supaya warna tidak bertumpuk.
      num.classList.toggle('bg-slate-100', !aktif && !sudahDilewati);
      num.classList.toggle('text-slate-500', !aktif && !sudahDilewati);
    }
  }

  function tampilkan(n, fokus) {
    langkah = Math.min(TOTAL, Math.max(1, n));
    for (let i = 1; i <= TOTAL; i++) {
      const pnl = panel(i);
      if (pnl) pnl.classList.toggle('hidden', i !== langkah);
      kelasTombol(i, i === langkah, i < langkah);
    }
    if (posisi) posisi.textContent = 'Langkah ' + langkah + ' dari ' + TOTAL + ' — ' + JUDUL[langkah];
    if (btnMundur) btnMundur.classList.toggle('invisible', langkah === 1);
    if (btnMaju) {
      btnMaju.classList.toggle('hidden', langkah === TOTAL);
      btnMaju.textContent = MAJU[langkah] || '';
    }
    if (btnSimpan) btnSimpan.classList.toggle('hidden', langkah !== TOTAL);
    if (pesan) pesan.textContent = '';
    if (langkah === TOTAL) perbaruiRingkasan();
    if (fokus) {
      const pertama = panel(langkah)?.querySelector('input:not([type=hidden]),select,textarea');
      if (pertama) pertama.focus({ preventScroll: false });
    }
  }

  /** Kembalikan elemen pertama yang tidak valid pada satu langkah. */
  function salahDi(n, abaikanTersembunyi) {
    const pnl = panel(n);
    if (!pnl) return null;
    for (const el of pnl.querySelectorAll('input,select,textarea')) {
      if (el.disabled || el.type === 'hidden') continue;
      if (!abaikanTersembunyi && el.offsetParent === null) continue;
      if (!el.checkValidity()) return el;
    }
    return null;
  }

  /** Lompat ke langkah tempat elemen berada. */
  function pindahKe(el) {
    const pnl = el && el.closest('[data-langkah]');
    if (!pnl) return;
    const n = parseInt(pnl.dataset.langkah, 10);
    if (n !== langkah) tampilkan(n, false);
  }

  function pesanUntuk(el) {
    if (!el) return 'Lengkapi dulu isian yang wajib.';
    const label = el.closest('div')?.querySelector('label')?.textContent?.trim();
    return '⚠️ ' + (label ? label.replace(/\s+/g, ' ') + ' belum lengkap. ' : '') + (el.validationMessage || '');
  }

  function bolehLanjut(n) {
    const el = salahDi(n);
    if (el) {
      if (pesan) pesan.textContent = pesanUntuk(el);
      el.focus();
      return false;
    }
    return true;
  }

  form.addEventListener('click', function (ev) {
    const maju = ev.target.closest('[data-langkah-maju]');
    if (maju) {
      if (bolehLanjut(langkah)) tampilkan(langkah + 1, true);
      return;
    }
    if (ev.target.closest('[data-langkah-mundur]')) { tampilkan(langkah - 1, true); return; }
    const tab = ev.target.closest('[data-langkah-tombol]');
    if (tab) {
      const tujuan = parseInt(tab.dataset.langkahTombol, 10);
      // Maju hanya bila langkah sekarang valid; mundur selalu boleh.
      if (tujuan > langkah && !bolehLanjut(langkah)) return;
      tampilkan(tujuan, true);
    }
  });

  // Cegah Enter mengirim form saat masih di langkah 1–2.
  form.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter' && langkah !== TOTAL && ev.target.tagName !== 'TEXTAREA'
        && !(ev.target.tagName === 'BUTTON')) {
      ev.preventDefault();
    }
  });

  // Validasi menyeluruh sebelum benar-benar dikirim.
  form.addEventListener('submit', function (ev) {
    for (let i = 1; i <= TOTAL; i++) {
      const el = salahDi(i, true);
      if (el) {
        ev.preventDefault();
        tampilkan(i, false);
        if (pesan) pesan.textContent = pesanUntuk(el);
        el.focus();
        return;
      }
    }
  });

  // Validasi natif browser hanya bisa fokus ke satu field; kalau field itu ada di
  // langkah lain yang tersembunyi, pengguna akan mentok tanpa pesan. Karena itu
  // field pertama yang invalid dipakai sebagai penentu langkah.
  let invalidPertama = null;
  form.addEventListener('invalid', function (ev) {
    if (!invalidPertama) invalidPertama = ev.target;
  }, true);
  form.addEventListener('click', function (ev) {
    if (!ev.target.closest('[data-wizard-simpan]')) return;
    invalidPertama = null;
    setTimeout(function () {
      if (!invalidPertama) return;
      pindahKe(invalidPertama);
      if (pesan) pesan.textContent = pesanUntuk(invalidPertama);
    }, 0);
  });

  function angka(v) { return parseInt(v, 10) || 0; }

  function perbaruiRingkasan() {
    const belanja = Array.from(form.querySelectorAll('.nR')).reduce((a, el) => a + angka(el.value), 0);
    const pajak = Array.from(form.querySelectorAll('.nP')).reduce((a, el) => a + angka(el.value), 0);
    const rp = (n) => 'Rp ' + Math.round(n).toLocaleString('id-ID');
    const set = (id, teks) => { const el = document.getElementById(id); if (el) el.textContent = teks; };
    set('ringkasBelanja', rp(belanja));
    set('ringkasPajak', rp(pajak));
    set('ringkasSumber', document.getElementById('sumberPengadaan')?.value === 'siplah' ? 'SIPLah' : 'Non-SIPLah');
    set('ringkasMetode', document.getElementById('metode')?.value === 'transfer' ? 'Non-tunai / transfer' : 'Tunai');
  }

  document.addEventListener('input', function (ev) {
    if (ev.target.classList.contains('nR') || ev.target.classList.contains('nP')) perbaruiRingkasan();
  });
  document.addEventListener('change', function (ev) {
    if (['sumberPengadaan', 'metode'].includes(ev.target.id)) perbaruiRingkasan();
  });

  tampilkan(1, false);
})();
</script>

@endsection
