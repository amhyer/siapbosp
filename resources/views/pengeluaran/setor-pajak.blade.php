@extends('layouts.app', ['title' => 'Setor & Lapor Pajak'])
@section('content')
<div class="grid lg:grid-cols-2 gap-5">
  <div class="card">
    <h3 class="font-semibold text-slate-700 mb-1">Pajak Terima yang Belum Disetor</h3>
    <p class="text-xs text-slate-500 mb-3">Centang rincian pajak dari belanja, isi keterangan, lalu setorkan. Mengisi <b>NTPN</b> = bukti <b>lapor pajak</b> ke negara (ala ARKAS; batas wajib lapor tgl 20 bulan berikutnya).</p>
    <form method="post" action="{{ route('setorpajak.store') }}">
    @csrf
    <div class="grid grid-cols-2 gap-3 mb-3">
      <div><label class="text-xs font-semibold text-slate-600">Tanggal Setor</label><input type="date" name="tanggal" class="inp mt-1" value="{{ date('Y-m-d') }}" required></div>
      <div><label class="text-xs font-semibold text-slate-600">Keterangan</label><input name="keterangan" class="inp mt-1" placeholder="Setor pajak ..." required></div>
    </div>
    @php $any=false; @endphp
    @foreach($belum as $belanjaId => $taxes)
      @php $any=true; $trx=$taxes->first()->transaksi; @endphp
      <div class="border border-slate-200 rounded-lg p-3 mb-3">
        <div class="text-xs text-slate-500 mb-2">No BKU Belanja <b>#{{ $trx->no_bku }}</b> · {{ $trx->tanggal->format('d/m/Y') }} · {{ Str::limit($trx->keterangan, 40) }}</div>
        @foreach($taxes as $p)
        <div class="flex items-center gap-2 text-sm py-1 border-b border-dashed border-slate-100">
          <input type="checkbox" name="tax_id[]" value="{{ $p->id }}" class="w-4 h-4" checked>
          <span class="flex-1">{{ $p->rekeningPajak->nama }} <span class="text-xs font-mono text-slate-400">({{ $p->kode_billing ?? '-' }})</span></span>
          <span class="num nilp" data-v="{{ $p->nilai }}">{{ rp($p->nilai) }}</span>
          <input name="ntpn[]" class="inp !w-36" placeholder="NTPN">
        </div>
        @endforeach
      </div>
    @endforeach
    @if(!$any)<p class="text-sm text-slate-400">Tidak ada pajak yang menunggu setor. 👍</p>@endif
    @if($any)
    <div class="flex justify-between items-center border-t pt-3 mt-2">
      <div class="text-sm">Total disetor: <b id="grandT" class="text-rose-700"></b> <span class="text-[11px] text-slate-400">(keluar Kas Bank)</span></div>
      <button class="btn">💾 Setor</button>
    </div>
    @endif
    </form>
  </div>

  <div class="space-y-5">
    <div class="card">
      <h3 class="font-semibold text-slate-700 mb-3">Setor Utang Pajak</h3>
      <form method="post" action="{{ route('setorutang.store') }}" class="space-y-3">
        @csrf
        <div class="grid grid-cols-2 gap-3">
          <div><label class="text-xs font-semibold text-slate-600">Tanggal</label><input type="date" name="tanggal" class="inp mt-1" value="{{ date('Y-m-d') }}" required></div>
          <div><label class="text-xs font-semibold text-slate-600">Keterangan</label><input name="keterangan" class="inp mt-1" required></div>
        </div>
        <div id="rowsU" class="space-y-2"></div>
        <button type="button" class="badge bdan" data-tambah-rincian>➕ Tambah Rincian Pajak</button>
        <button class="btn w-full justify-center">💾 Setor</button>
      </form>
    </div>

    <div class="card">
      <h3 class="font-semibold text-slate-700 mb-3">Riwayat Setoran</h3>
      <table class="tbl">
        <thead><tr><th>BKU</th><th>Tanggal</th><th>Jenis</th><th class="num">Total</th></tr></thead>
        <tbody>
          @foreach($riwayat as $t)
          <tr><td>#{{ $t->no_bku }}</td><td>{{ $t->tanggal->format('d/m/Y') }}</td>
            <td>{{ $t->labelType() }}<div class="text-[11px] text-slate-400">@foreach($t->taxes as $p){{ $p->rekeningPajak->kode ?? '' }}: NTPN {{ $p->ntpn ?? '-' }}; @endforeach</div></td>
            <td class="num">{{ rp($t->total) }}</td></tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
<template id="tplU"><div class="flex gap-2">
  <select name="pajak_id[]" class="inp" required>@foreach($rekeningPajaks as $p)<option value="{{ $p->id }}">{{ $p->nama }}</option>@endforeach</select>
  <input name="ntpn[]" class="inp" placeholder="NTPN" style="width:160px">
  <input name="nilai_pajak[]" type="number" min="1" class="inp" placeholder="nilai" style="width:130px" required>
  <button type="button" class="badge bdel" data-hapus-baris>✖</button>
</div></template>
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
function total(){let g=0;document.querySelectorAll('input[name="tax_id[]"]:checked').forEach(c=>{g+=+c.parentElement.querySelector('.nilp').dataset.v;});const e=document.getElementById('grandT');if(e)e.textContent='Rp '+g.toLocaleString('id-ID');}
function tambahU(){document.getElementById('rowsU').appendChild(document.getElementById('tplU').content.cloneNode(true));}
// Delegasi event menggantikan atribut on* inline (audit R-03).
document.addEventListener('click', function (ev) {
  if (ev.target.closest('[data-tambah-rincian]')) { tambahU(); return; }
  const hapus = ev.target.closest('[data-hapus-baris]');
  if (hapus) hapus.parentElement.remove();
});
document.addEventListener('change', function (ev) {
  if (ev.target.matches('input[name="tax_id[]"]')) total();
});
total(); tambahU();
</script>
@endsection
