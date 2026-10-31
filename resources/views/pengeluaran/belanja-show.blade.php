@extends('layouts.app', ['title' => 'Detail Belanja #' . $t->no_bku])
@section('content')
<div class="mb-3 flex gap-2"> <a href="{{ route('belanja.kwitansi', $t) }}" data-cetak="Kwitansi" class="btn2 btn text-sm"> Cetak Kwitansi</a> <a href="{{ route('belanja.spj', $t) }}" class="btn2 btn text-sm"> Paket Dokumen SPJ</a> <a href="{{ route('belanja.index') }}" class="btn2 btn text-sm">← Daftar Belanja</a>
</div>
<div class="card grid sm:grid-cols-3 gap-3 text-sm"> <div><div class="text-xs text-slate-500">No Bukti / BKU</div><div class="font-bold">{{ $t->no_bukti ?? '—' }} · #{{ $t->no_bku }}</div></div> <div><div class="text-xs text-slate-500">Tanggal</div><div class="font-bold">{{ $t->tanggal->format('d/m/Y') }}</div></div> <div><div class="text-xs text-slate-500">Dana / Tahap</div><div class="font-bold">{{ $t->bosType->nama ?? '—' }} · {{ $t->stage->nama ?? '—' }}</div></div> <div><div class="text-xs text-slate-500">Pembayaran</div><div class="font-bold">{{ ucfirst($t->metode_bayar) }}</div></div> <div class="sm:col-span-2"><div class="text-xs text-slate-500">Keterangan</div><div class="font-bold">{{ $t->keterangan }}</div></div>
</div>
<div class="card"> <h3 class="font-semibold text-slate-700 mb-3">Rincian Belanja</h3> <table class="tbl"> <thead><tr><th>Kode Rekening</th><th>Nama</th><th>Uraian</th><th class="num">Nilai</th></tr></thead> <tbody>@foreach($t->details as $d)<tr><td class="font-mono">{{ $d->rekening->kode ?? '' }}</td><td>{{ $d->rekening->nama ?? '' }}</td><td>{{ $d->uraian }}</td><td class="num">{{ rp($d->nilai) }}</td></tr>@endforeach</tbody> <tfoot><tr class="font-bold"><td colspan="3" class="text-right">TOTAL</td><td class="num">{{ rp($t->total) }}</td></tr></tfoot> </table>
</div>
@if($t->taxes->isNotEmpty())
<div class="card"> <h3 class="font-semibold text-slate-700 mb-3">Pajak Diterima</h3> <table class="tbl"> <thead><tr><th>Pajak</th><th>Kode Billing</th><th>NTPN</th><th class="num">Nilai</th><th>Status</th></tr></thead> <tbody>@foreach($t->taxes as $p)<tr><td>{{ $p->rekeningPajak->nama }}</td><td class="font-mono">{{ $p->kode_billing ?? '—' }}</td><td class="font-mono">{{ $p->ntpn ?? '—' }}</td><td class="num">{{ rp($p->nilai) }}</td> <td><span class="badge {{ $p->status==='sudah'?'bg-emerald-100 text-emerald-700':'bg-amber-100 text-amber-700' }}">{{ $p->status==='sudah'?'Sudah disetor':'Belum disetor' }}</span></td></tr>@endforeach</tbody> </table>
</div>
@endif
<div class="card"> <h3 class="font-semibold text-slate-700 mb-3"> Lampiran Bukti</h3> <table class="tbl"> <tbody> @forelse($t->attachments as $a)
      <tr><td> {{ $a->nama }}</td><td class="text-xs text-slate-400">{{ $a->ukuranLabel() }} · {{ $a->created_at->format('d/m/Y') }}</td> <td class="text-right"><a href="{{ route('lampiran.unduh', $a) }}" class="badge bdan"> unduh</a> <form method="post" action="{{ route('lampiran.destroy', $a) }}" class="inline" data-konfirmasi="Hapus lampiran?">@csrf @method('DELETE')<button class="badge bdel"></button></form></td></tr> @empty
      <tr><td class="text-slate-400 text-sm py-2">Belum ada lampiran.</td></tr> @endforelse
    </tbody> </table> <form method="post" action="{{ route('lampiran.store', $t) }}" enctype="multipart/form-data" class="flex gap-2 mt-3 items-center"> @csrf
    <input type="file" name="lampiran[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="inp" required> <button class="btn whitespace-nowrap"> Unggah</button> </form>
</div>
<a href="{{ route('belanja.index') }}" class="btn2 btn">← Kembali</a>
@endsection
