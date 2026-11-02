@extends('layouts.app', ['title' => 'Susun Kertas Kerja RKAS (' . (\App\Models\RkasHeader::LABEL_JENIS[$jenis] ?? 'Kertas Kerja') . ')'])
@section('subtitle', ($aturanJuknis['label_aturan'] ?? '') . ' · Pagu Acuan: ' . rp($pagu))
@section('content')
@php
  $bulanPendek = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
@endphp

@if($jenis === 'pergeseran')
<div class="card border-l-4 border-sky-500 bg-sky-50/60 text-xs text-sky-900"> <b>Mode Kertas Kerja Pergeseran (Aturan ARKAS 4 Bab IX):</b> Pergeseran hanya diperbolehkan <b>dalam kelompok akun belanja yang sama</b> (<code>5.1.02</code> Barang &amp; Jasa · <code>5.2.02</code> Modal Peralatan · <code>5.2.05</code> Modal Aset Tetap), total per kelompok akun <b>tidak boleh berubah (Selisih = Rp 0)</b>, dan rincian yang sudah direalisasikan di BKU tidak boleh diturunkan di bawah realisasi.
</div>
@elseif($jenis === 'perubahan')
<div class="card border-l-4 border-amber-500 bg-amber-50/60 text-xs text-amber-900"> <b>Mode Kertas Kerja Perubahan (Aturan ARKAS 4 Bab XI):</b> Perubahan memperbolehkan realokasi <b>lintas jenis/akun belanja</b> maupun penyesuaian perubahan pagu/SiLPA, namun rincian yang sudah direalisasikan di BKU tetap tidak boleh di bawah nilai realisasi.
</div>
@endif

@if(count($rekomendasi ?? []))
<div class="card !border-amber-300 !bg-amber-50/60"> <h3 class="card-title mb-1"> Sudah pernah dibelanjakan di BKU — wajib tetap terakomodasi dalam revisi</h3> <p class="page-desc mb-2">Rekening di bawah sudah memiliki realisasi tahun {{ $tahun }}. Klik <b>+ Bawa</b> untuk memasukkannya ke rincian revisi ini.</p> <div class="tbl-wrap"><table class="tbl"> <thead><tr><th>Kode</th><th>Uraian RKAS</th><th class="num">Anggaran</th><th class="num">Realisasi</th><th>Sumber</th><th></th></tr></thead> <tbody> @foreach($rekomendasi as $i => $rc)
      <tr> <td class="font-mono text-xs">{{ $rc['kode'] }}</td> <td>{{ Str::limit($rc['uraian'], 45) }}</td> <td class="num">{{ rp($rc['anggaran']) }}</td> <td class="num font-semibold text-rose-700">{{ rp($rc['realisasi']) }}</td> <td>@if($rc['siplah'])<span class="badge bg-sky-100 text-sky-700">SIPLah</span>@else<span class="badge bg-slate-100 text-slate-600">langsung</span>@endif</td> <td><button type="button" class="btn btn-sm" data-bawa-rekomendasi="{{ json_encode($rc) }}">+ Bawa</button></td> </tr> @endforeach
    </tbody> </table></div>
</div>
@endif

{{-- Panel Kalkulator Live Kertas Kerja ala ARKAS 4 --}}
<div class="card bg-slate-900 text-white grid grid-cols-2 lg:grid-cols-5 gap-3 text-xs sticky top-16 z-10 shadow-lg"> <div> <div class="text-slate-400">Pagu Sumber Dana</div> <div class="font-bold text-base">{{ rp($pagu) }}</div> </div> <div> <div class="text-slate-400">Sudah Dianggarkan</div> <div class="font-bold text-base text-emerald-400" id="liveTotal">Rp 0</div> </div> <div> <div class="text-slate-400">Belum Dianggarkan (Wajib Rp 0)</div> <div class="font-bold text-base text-amber-300" id="liveSisa">{{ rp($pagu) }}</div> </div> <div> <div class="text-slate-400">Tahap 1 (Jan–Jun · Maks 50%)</div> <div class="font-bold text-base text-sky-300" id="liveTahap1">Rp 0 (0%)</div> </div> <div> <div class="text-slate-400">Tahap 2 (Jul–Des)</div> <div class="font-bold text-base text-indigo-300" id="liveTahap2">Rp 0 (0%)</div> </div>
</div> <form method="post" action="{{ route('rkas.store') }}" class="space-y-5"> @csrf
  <input type="hidden" name="tahun" value="{{ $tahun }}"> @if($acuanHeader)<input type="hidden" name="acuan_header_id" value="{{ $acuanHeader->id }}">@endif

<form method="post" action="{{ route('rkas.store') }}" class="space-y-5">
  @csrf
  <input type="hidden" name="tahun" value="{{ $tahun }}">
  @if($acuanHeader)<input type="hidden" name="acuan_header_id" value="{{ $acuanHeader->id }}">@endif

  <div class="card grid sm:grid-cols-4 gap-4">
    <div>
      <label class="text-xs font-semibold text-slate-600">Jenis Kertas Kerja</label>
      <select name="jenis_rkas" class="inp mt-1">
        @foreach(\App\Models\RkasHeader::LABEL_JENIS as $kj => $lj)
          <option value="{{ $kj }}" @selected($jenis === $kj)>{{ $lj }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label class="text-xs font-semibold text-slate-600">Sumber Dana Utama (ARKAS 4)</label>
      <select name="sumber_dana" id="hdrSumber" class="inp mt-1">
        @foreach($sumberList as $ks => $ls)
          <option value="{{ $ks }}" @selected($sumber === $ks)>{{ $ls }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label class="text-xs font-semibold text-slate-600">No RKAS / Dokumen</label>
      <input name="no_rkas" class="inp mt-1" value="{{ old('no_rkas') }}" placeholder="mis. 420/001/RKAS/{{ $tahun }}">
    </div>
    <div>
      <label class="text-xs font-semibold text-slate-600">Catatan Revisi</label>
      <input name="catatan" class="inp mt-1" value="{{ old('catatan') }}" placeholder="opsional">
    </div>
  </div>

  <div class="card">
    <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
      <div>
        <h3 class="font-semibold text-slate-700">Rincian Kegiatan &amp; Jadwal Volume Bulanan (Januari–Desember)</h3>
        <p class="text-[11px] text-slate-400">Pilih Kode Kegiatan SNP, Referensi Standar Harga (opsional), Rekening Belanja, dan sebaran volume per bulan (Tahap 1: Jan–Jun · Tahap 2: Jul–Des).</p>
      </div>
      <button type="button" class="btn2 btn" data-tambah-baris>➕ Tambah Kegiatan</button>
    </div>
    <div class="overflow-x-auto">
      <table class="tbl" id="tblRkas">
        <thead><tr>
          <th style="min-width:165px">Kegiatan SNP</th>
          <th style="min-width:220px">Rekening Belanja &amp; SSH</th>
          <th style="min-width:210px">Uraian (maks 500 kar.)</th>
          <th>Satuan</th>
          <th>Vol</th>
          <th>Harga Satuan</th>
          <th style="min-width:290px">Sebaran Volume Jan–Des</th>
          <th class="num">Jumlah</th>
          <th></th>
        </tr></thead>
        <tbody></tbody>
        <tfoot><tr><td colspan="7" class="text-right font-semibold">TOTAL KERTAS KERJA</td><td class="num font-bold" id="grand">Rp 0</td><td></td></tr></tfoot>
      </table>
    </div>
    <div class="mt-4 flex flex-wrap gap-3 items-center">
      <button class="btn">💾 Simpan Kertas Kerja &amp; Cek Proporsi Juknis 2026</button>
      <a href="{{ route('rkas.index') }}" class="btn2 btn">Batal</a>
    </div>
  </div>
</form>

<template id="tpl">
<tr class="align-top">
  <td>
    <select name="kode_kegiatan[]" class="inp text-xs">
      @foreach($kodeKegiatanList as $kd => $ur)
        <option value="{{ $kd }}">{{ $kd }} · {{ Str::limit($ur, 26) }}</option>
      @endforeach
    </select>
  </td>
  <td class="space-y-1.5">
    <select name="rekening_id[]" class="inp text-xs rekSel" required>
      <option value="">— pilih rekening —</option>
      @foreach($rekenings as $r)<option value="{{ $r->id }}">{{ $r->kode }} · {{ Str::limit($r->nama, 38) }}</option>@endforeach
    </select>
    <select name="standar_harga_id[]" class="inp text-xs sshSel bg-slate-50">
      <option value="">— referensi SSH/SBU (opsional) —</option>
      @foreach($standarHargas as $sh)
        <option value="{{ $sh->id }}" data-nama="{{ $sh->nama_barang }}" data-satuan="{{ $sh->satuan }}" data-harga="{{ $sh->batas_harga }}">
          {{ $sh->nama_barang }} (maks {{ rp($sh->batas_harga) }}/{{ $sh->satuan }})
        </option>
      @endforeach
    </select>
  </td>
  <td>
    <input name="uraian[]" maxlength="500" class="inp uraianInp" required placeholder="uraian barang/jasa">
    {{-- Opsional: hanya dipakai lembar RKA/DPA Unit Kerja 2.2 (Lokasi Kegiatan &
         Target Kinerja Kuantitatif). Dikosongkan = memakai nama sekolah dan
         volume + satuan rincian saat dicetak. --}}
    <div class="mt-1 grid grid-cols-2 gap-1">
      <input name="lokasi[]" maxlength="120" class="inp text-xs lokasiInp" placeholder="lokasi kegiatan (opsional)">
      <input name="target[]" maxlength="120" class="inp text-xs targetInp" placeholder="target kinerja (opsional)">
    </div>
    <input type="hidden" name="sumber[]" class="sumberRow" value="{{ $sumber }}">
  </td>
  <td><input name="satuan[]" class="inp satInp" value="paket" style="width:76px"></td>
  <td><input name="banyak[]" type="number" min="1" value="1" class="inp qty" style="width:68px"></td>
  <td><input name="harga[]" type="number" min="0" value="0" class="inp hrg" style="width:120px"></td>
  <td>
    <div class="grid grid-cols-6 gap-1 text-[10px]">
      @foreach($bulanPendek as $m => $lbl)
      <label class="block">
        <span class="text-slate-400">{{ $lbl }}</span>
        <input type="number" min="0" value="{{ $m === 1 ? 1 : 0 }}" data-m="{{ $m }}" class="inp !px-1 !py-0.5 text-xs blnInp">
      </label>
      @endforeach
    </div>
  </td>
  <td class="num jml font-semibold whitespace-nowrap">Rp 0</td>
  <td><button type="button" class="badge bg-rose-50 text-rose-600" data-hapus-baris>✖</button></td>
</tr>
</template> <script nonce="{{ request()->attributes->get('csp_nonce') }}">
const PAGU_ACUAN = {{ (int) $pagu }};
const PREFILL = @json($prefillRows ?? []);

function reindexBulanNames(){
  document.querySelectorAll('#tblRkas tbody tr').forEach((tr, idx) => {
    tr.querySelectorAll('.blnInp').forEach(inp => {
      inp.name = `bulan[${idx}][${inp.dataset.m}]`;
    });
  });
}

function tambahBaris(init){
  const tbody = document.getElementById('tblRkas').querySelector('tbody');
  tbody.appendChild(document.getElementById('tpl').content.cloneNode(true));
  const tr = tbody.lastElementChild;
  if(init){
    if(init.kode_kegiatan) tr.querySelector('select[name="kode_kegiatan[]"]').value = init.kode_kegiatan;
    if(init.rekening_id) tr.querySelector('.rekSel').value = String(init.rekening_id);
    if(init.standar_harga_id) tr.querySelector('.sshSel').value = String(init.standar_harga_id);
    tr.querySelector('.uraianInp').value = init.uraian || '';
    if(init.lokasi_kegiatan) tr.querySelector('.lokasiInp').value = init.lokasi_kegiatan;
    if(init.target_kinerja) tr.querySelector('.targetInp').value = init.target_kinerja;
    tr.querySelector('.satInp').value = init.satuan || 'paket';
    tr.querySelector('.qty').value = init.banyak || 1;
    tr.querySelector('.hrg').value = init.harga || 0;
    if(init.bulan){
      tr.querySelectorAll('.blnInp').forEach(inp => {
        const m = inp.dataset.m;
        inp.value = init.bulan[m] ?? 0;
      });
    }
  }
  reindexBulanNames();
  hitung();
}

function hapusBaris(btn){
  btn.closest('tr').remove();
  reindexBulanNames();
  hitung();
}

function pilihSsh(sel){
  const opt = sel.selectedOptions[0];
  if(!opt || !opt.value) return;
  const tr = sel.closest('tr');
  if(!tr.querySelector('.uraianInp').value) tr.querySelector('.uraianInp').value = opt.dataset.nama || '';
  tr.querySelector('.satInp').value = opt.dataset.satuan || 'unit';
  tr.querySelector('.hrg').value = opt.dataset.harga || 0;
  hitung();
}

function sinkronDariBulan(inp){
  const tr = inp.closest('tr');
  let sum = 0;
  tr.querySelectorAll('.blnInp').forEach(b => sum += (+b.value || 0));
  if(sum > 0) tr.querySelector('.qty').value = sum;
  hitung();
}

function sinkronDariQty(inp){
  const tr = inp.closest('tr');
  let sumOthers = 0;
  const blns = [...tr.querySelectorAll('.blnInp')];
  blns.slice(1).forEach(b => sumOthers += (+b.value || 0));
  if(sumOthers === 0 && blns[0]) blns[0].value = +inp.value || 0;
  hitung();
}

function bawaRekomendasi(rc){
  tambahBaris({
    kode_kegiatan: rc.kode_kegiatan || '06.05',
    rekening_id: rc.rekening_id,
    uraian: rc.uraian || '',
    satuan: 'paket',
    banyak: 1,
    harga: Math.max(rc.anggaran, rc.realisasi),
    bulan: {1:1,2:0,3:0,4:0,5:0,6:0,7:0,8:0,9:0,10:0,11:0,12:0}
  });
}

function fmt(n){ return 'Rp ' + Math.round(n).toLocaleString('id-ID'); }

function hitung(){
  let g = 0, t1 = 0, t2 = 0;
  document.querySelectorAll('#tblRkas tbody tr').forEach(tr => {
    const q = +tr.querySelector('.qty').value || 0;
    const h = +tr.querySelector('.hrg').value || 0;
    let sumB = 0, q1 = 0, q2 = 0;
    tr.querySelectorAll('.blnInp').forEach(b => {
      const v = +b.value || 0;
      const m = +b.dataset.m;
      sumB += v;
      if(m <= 6) q1 += v; else q2 += v;
    });
    const effQty = sumB > 0 ? sumB : q;
    const j = effQty * h;
    g += j;
    if(sumB > 0){ t1 += q1 * h; t2 += q2 * h; } else { t1 += j; }
    tr.querySelector('.jml').textContent = fmt(j);
  });
  document.getElementById('grand').textContent = fmt(g);
  document.getElementById('liveTotal').textContent = fmt(g);
  const sisa = PAGU_ACUAN > 0 ? (PAGU_ACUAN - g) : 0;
  const elSisa = document.getElementById('liveSisa');
  elSisa.textContent = fmt(sisa);
  elSisa.className = 'font-bold text-base ' + (sisa === 0 ? 'text-emerald-400' : (sisa < 0 ? 'text-rose-400' : 'text-amber-300'));
  const base = PAGU_ACUAN > 0 ? PAGU_ACUAN : Math.max(1, g);
  const p1 = Math.round(t1 / base * 1000) / 10;
  const p2 = Math.round(t2 / base * 1000) / 10;
  document.getElementById('liveTahap1').textContent = `${fmt(t1)} (${p1}%)`;
  document.getElementById('liveTahap2').textContent = `${fmt(t2)} (${p2}%)`;
}

document.getElementById('hdrSumber').addEventListener('change', function(){
  document.querySelectorAll('.sumberRow').forEach(i => i.value = this.value);
});

// Delegasi event menggantikan atribut on* inline (audit R-03). Baris rincian
// dibuat dari <template>, jadi listener dipasang di document.
document.addEventListener('click', function (ev) {
  let el;
  if ((el = ev.target.closest('[data-tambah-baris]'))) { tambahBaris(); return; }
  if ((el = ev.target.closest('[data-hapus-baris]'))) { hapusBaris(el); return; }
  const rekomendasi = ev.target.closest('[data-bawa-rekomendasi]');
  if (rekomendasi) bawaRekomendasi(JSON.parse(rekomendasi.dataset.bawaRekomendasi));
});
document.addEventListener('input', function (ev) {
  const el = ev.target;
  if (el.classList.contains('qty')) { sinkronDariQty(el); return; }
  if (el.classList.contains('blnInp')) { sinkronDariBulan(el); return; }
  if (el.classList.contains('hrg')) hitung();
});
document.addEventListener('change', function (ev) {
  if (ev.target.classList.contains('sshSel')) pilihSsh(ev.target);
});

if(PREFILL.length){
  PREFILL.forEach(r => tambahBaris(r));
} else {
  tambahBaris();
}
</script>
@endsection
