@extends('layouts.app', ['title' => 'Kas Tunai & Kas Bank'])
@section('content')
<div class="flex gap-2 flex-wrap"> <a href="{{ route('pembukuan.register', ['jenis' => 'tunai']) }}" data-cetak="Register Kas Tunai" class="btn2 btn text-sm"> Register Kas Tunai</a> <a href="{{ route('pembukuan.register', ['jenis' => 'bank']) }}" data-cetak="Register Kas Bank" class="btn2 btn text-sm"> Register Kas Bank</a> @if(($saldo['saldoAwalTahun'] ?? 0) > 0)
  <span class="text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg px-3 py-2">ℹ Saldo awal dibawa dari tahun lalu (SILPA): <b>{{ rp($saldo['saldoAwalTahun']) }}</b></span> @endif
</div>
<div class="grid sm:grid-cols-2 gap-4"> <div class="card"><div class="text-xs text-slate-500">Saldo Kas Tunai</div><div class="text-2xl font-bold text-emerald-700">{{ rp($saldo['tunai']) }}</div></div> <div class="card"><div class="text-xs text-slate-500">Saldo Kas Bank</div><div class="text-2xl font-bold text-sky-700">{{ rp($saldo['bank']) }}</div></div>
</div>
<div class="card overflow-x-auto"> <h3 class="font-semibold text-slate-700 mb-3">Mutasi Kas {{ $tahun }} (Δ = perubahan)</h3> <table class="tbl"> <thead><tr><th>BU</th><th>Tanggal</th><th>Uraian</th><th>Jenis</th><th class="num">Δ Tunai</th><th class="num">Δ Bank</th><th class="num">Saldo Total</th></tr></thead> <tbody> @foreach($items as $it)
      @php $t=$it['trx']; @endphp
      <tr> <td>#{{ $t->no_bku }}</td><td>{{ $t->tanggal->format('d/m/Y') }}</td><td>{{ Str::limit($t->keterangan,50) }}</td> <td>{{ $t->labelType() }}</td> <td class="num {{ $it['dTunai']>0?'text-emerald-700':($it['dTunai']<0?'text-rose-700':'text-slate-300') }}">{{ $it['dTunai']!=0?rp($it['dTunai']):'' }}</td> <td class="num {{ $it['dBank']>0?'text-sky-700':($it['dBank']<0?'text-rose-700':'text-slate-300') }}">{{ $it['dBank']!=0?rp($it['dBank']):'' }}</td> <td class="num font-semibold">{{ rp($it['saldo']) }}</td> </tr> @endforeach
    </tbody> </table>
</div>
@endsection
