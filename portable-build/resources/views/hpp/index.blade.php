@extends('layouts.app', ['title' => 'HPP — Pemetaan Pembelanjaan'])
@section('subtitle', 'Petakan belanja (khususnya dari SIPLah) ke rekening, kegiatan, dan rincian RKAS')
@section('content')
<div class="page-head"> <p class="page-desc">Tahun {{ $tahun }} · Tanpa pemetaan, belanja SIPLah tidak bisa dieksekusi ke pergeseran/perubahan.</p>
</div>
<div class="toolbar"> <form method="get" class="flex flex-wrap gap-2 items-center text-sm"> <select name="sumber" class="inp inp-sm !w-auto" data-kirim-otomatis> <option value="">Semua sumber</option> <option value="siplah" @selected($sumber==='siplah')>SIPLah</option> <option value="langsung" @selected($sumber==='langsung')>Langsung</option> </select> <select name="status" class="inp inp-sm !w-auto" data-kirim-otomatis> <option value="">Semua status</option> <option value="belum" @selected($status==='belum')>Belum terpetakan</option> <option value="terpetakan" @selected($status==='terpetakan')>Terpetakan</option> </select> @if($sumber || $status)<a href="{{ route('hpp.index') }}" class="text-xs text-slate-400 hover:text-rose-500"> hapus filter</a>@endif
  </form>
</div>
<form method="post" action="{{ route('hpp.store') }}">
@csrf
<div class="card"> <div class="tbl-wrap"> <table class="tbl"> <thead><tr><th>Tanggal</th><th>Belanja / Uraian</th><th>Kode Rekening</th><th>Sumber</th><th class="num">Nilai</th><th style="min-width:260px">Petakan ke RKAS (kegiatan)</th></tr></thead> <tbody> @forelse($items as $d)
      <tr> <td class="whitespace-nowrap">{{ $d->transaksi->tanggal->format('d/m/Y') }}</td> <td>{{ $d->uraian }}<div class="text-[11px] text-slate-400">{{ Str::limit($d->transaksi->keterangan, 50) }}</div></td> <td class="font-mono text-xs">{{ $d->rekening->kode ?? '—' }}</td> <td> <span class="badge {{ $d->sumber==='siplah'?'bg-sky-100 text-sky-700':'bg-slate-100 text-slate-600' }}">{{ $d->sumber }}</span> <select name="peta[{{ $d->id }}][sumber]" class="inp inp-sm !w-auto mt-1"> <option value="langsung" @selected($d->sumber==='langsung')>langsung</option> <option value="siplah" @selected($d->sumber==='siplah')>siplah</option> </select> </td> <td class="num">{{ rp($d->nilai) }}</td> <td> <select name="peta[{{ $d->id }}][rkas_detail_id]" class="inp inp-sm"> <option value="">— belum dipetakan —</option> @foreach($rincian as $rc)
            <option value="{{ $rc->id }}" @selected($d->rkas_detail_id===$rc->id)>{{ $rc->rekening->kode ?? '' }} · {{ Str::limit($rc->uraian, 50) }} ({{ rp($rc->jumlah) }})</option> @endforeach
          </select> @if($d->rkasDetail)<div class="text-[11px] text-emerald-600 mt-0.5"> {{ Str::limit($d->rkasDetail->uraian, 45) }}</div>@endif
        </td> </tr> @empty
      <tr><td colspan="6"><div class="empty">Tidak ada rincian belanja. Input belanja atau impor ARKAS dulu.</div></td></tr> @endforelse
    </tbody> </table> </div> {{ $items->links() }}
  <div class="mt-3"><button class="btn"> Simpan Pemetaan</button></div>
</div>
</form>
@endsection
