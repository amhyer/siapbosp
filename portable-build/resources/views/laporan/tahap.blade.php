@extends('layouts.app', ['title' => 'LPJ & Tutup Buku per Tahap'])
@section('content')
<div class="dokumen">
<div class="toolbar nonprint"> <form style="display:flex;gap:.5rem;align-items:center;font-size:.82rem" method="get"> <span>Tahap:</span> <select name="stage" style="border:1px solid #cbd5e1;border-radius:.5rem;padding:.35rem .6rem" data-kirim-otomatis> @foreach($stages as $s)<option value="{{ $s->id }}" @selected($stageId===$s->id)>{{ $s->nama }}{{ in_array($s->id, $terkunciMap) ? ' ' : '' }}</option>@endforeach
    </select> </form> <button type="button" data-cetak-halaman> Cetak / Simpan PDF</button> <a href="{{ route('laporan.lpj', ['stage' => $stageId]) }}" data-cetak="LPJ Lengkap" style="background:#eff6ff;border-color:#bfdbfe"> LPJ Lengkap</a> <a href="{{ route('laporan.lpj.zip', ['stage' => $stageId]) }}" style="background:#ecfdf5;border-color:#6ee7b7"> Paket ZIP</a> <a href="{{ route('laporan.lpj.pdf', ['stage' => $stageId]) }}" style="background:#fef2f2;border-color:#fca5a5;font-weight:700"> PDF Asli</a> <a href="{{ route('laporan.lpj.xlsx', ['stage' => $stageId]) }}" style="background:#f0fdf4;border-color:#86efac;font-weight:700"> Excel 4 Sheet</a> <a href="{{ route('dashboard') }}">← Kembali</a> @if($terkunci)
    <span style="background:#fee2e2;color:#b91c1c;font-size:11px;padding:.25rem .6rem;border-radius:.5rem"> Tahap ini DITUTUP bukunya</span> @if(auth()->user()->role === 'kepsek')
    <form method="post" action="{{ route('laporan.tahap.buka', $stageId) }}" data-konfirmasi="Buka kembali buku tahap ini?">@csrf
      <button style="border-color:#fca5a5;background:#fef2f2;color:#b91c1c"> Buka Kembali (Kepsek)</button> </form> @endif
  @else
    <form method="post" action="{{ route('laporan.tahap.tutup', $stageId) }}" data-konfirmasi="Tutup buku tahap ini? Transaksi di tahap ini akan terkunci.">@csrf
      <button> Tutup Buku Tahap Ini</button> </form> @endif

  @if($lpj)
    @if($lpj->status === 'dikirim')
      <span style="background:#fef3c7;color:#92400e;font-size:11px;padding:.25rem .6rem;border-radius:.5rem;font-weight:600"> LPJ dikirim {{ $lpj->created_at->format('d/m') }} — menunggu tinjauan Dinas</span> @elseif($lpj->status === 'diterima')
      <span style="background:#d1fae5;color:#065f46;font-size:11px;padding:.25rem .6rem;border-radius:.5rem;font-weight:600"> LPJ DITERIMA Dinas{{ $lpj->ditinjau_at ? ' (' . $lpj->ditinjau_at->format('d/m/Y') . ')' : '' }}</span> @else
      <span style="background:#fee2e2;color:#991b1b;font-size:11px;padding:.25rem .6rem;border-radius:.5rem;font-weight:600" title="{{ $lpj->catatan_dinas }}"> LPJ DITOLAK — {{ Str::limit($lpj->catatan_dinas, 60) }}</span> @endif
  @endif
  @if($terkunci && (!$lpj || $lpj->status === 'ditolak'))
    <form method="post" action="{{ route('laporan.lpj.kumpulkan', $stageId) }}" data-konfirmasi="Kirim LPJ tahap ini ke Dinas untuk ditinjau?">@csrf
      <button style="background:#0b5fef;color:#fff;font-weight:700;border-color:#0b5fef">{{ $lpj ? '🔄 Kirim Ulang LPJ ke Dinas' : '📨 Kirim LPJ ke Dinas' }}</button>
    </form>
  @endif
</div>
@php $sekolah = auth()->user()->school; $stg = $stages->firstWhere('id', $stageId); @endphp
<div class="kop"> <div style="font-size:11px;text-transform:uppercase">{{ $sekolah->kab_kota }} — {{ $sekolah->provinsi }}</div> <div style="font-size:18px;font-weight:800;text-transform:uppercase">{{ $sekolah->nama }}</div> <div style="font-size:11px">NPSN {{ $sekolah->npsn }} · {{ $sekolah->alamatLengkap() }}</div> <div style="margin-top:6px;font-weight:700">LAPORAN PEMANFAATAN DANA BOSP — {{ strtoupper($stg->nama ?? '') }} TAHUN ANGGARAN {{ $tahun }}</div>
</div> <table class="tbl"> <thead><tr><th>No BKU</th><th>Tanggal</th><th>Uraian</th><th>Kode Rekening</th><th class="num">Penerimaan (Rp)</th><th class="num">Pengeluaran (Rp)</th></tr></thead> <tbody> @php $tm=0; $tk=0; @endphp
    @foreach($items as $t)
      @php
        $isPend = $t->type==='pendapatan';
        $det = $t->details->pluck('rekening.kode')->filter()->implode(', ');
        @endphp
      @if($t->details->isEmpty())
        <tr><td>#{{ $t->no_bku }}</td><td>{{ $t->tanggal->format('d/m/Y') }}</td><td>{{ $t->keterangan }} ({{ $t->labelType() }})</td><td>—</td> <td class="num">{{ $isPend ? rp($t->total) : '' }}</td><td class="num">{{ $isPend ? '' : rp($t->total) }}</td></tr> @php $isPend ? $tm+=$t->total : $tk+=$t->total; @endphp
      @else
        @foreach($t->details as $d)
        <tr><td>#{{ $t->no_bku }}</td><td>{{ $t->tanggal->format('d/m/Y') }}</td><td>{{ $d->uraian ?? $t->keterangan }}</td><td style="font-family:monospace">{{ $d->rekening->kode ?? '' }}</td> <td class="num">{{ $isPend ? rp($d->nilai) : '' }}</td><td class="num">{{ $isPend ? '' : rp($d->nilai) }}</td></tr> @php $isPend ? $tm+=$d->nilai : $tk+=$d->nilai; @endphp
        @endforeach
      @endif
    @endforeach
  </tbody> <tfoot> <tr style="font-weight:700"><td colspan="4" style="text-align:right">JUMLAH</td><td class="num">{{ rp($tm) }}</td><td class="num">{{ rp($tk) }}</td></tr> </tfoot>
</table> @if($items->pluck('taxes')->flatten()->isNotEmpty())
<h4 style="font-size:14px;font-weight:700;margin:1.25rem 0 .4rem">Rincian Pajak</h4>
<table class="tbl"> <thead><tr><th>Tanggal</th><th>Jenis Pajak</th><th>Kode Billing</th><th>NTPN</th><th class="num">Nilai (Rp)</th></tr></thead> <tbody>@foreach($items as $t)@foreach($t->taxes as $p)
    <tr><td>{{ $t->tanggal->format('d/m/Y') }}</td><td>{{ $p->rekeningPajak->nama ?? '' }}</td><td style="font-family:monospace">{{ $p->kode_billing ?? '-' }}</td><td style="font-family:monospace">{{ $p->ntpn ?? '-' }}</td><td class="num">{{ rp($p->nilai) }}</td></tr> @endforeach @endforeach</tbody>
</table>
@endif

@php
  $kepsek = $penandatangan->firstWhere('jabatan', 'Kepala Sekolah') ?? $penandatangan->first(fn($p)=>stripos($p->jabatan,'kepala')!==false);
  $bendahara = $penandatangan->first(fn($p)=>stripos($p->jabatan,'bendahara')!==false) ?? $penandatangan->skip(1)->first();
@endphp
<div class="ttd"> <div class="ttd-grid"> <div><div style="margin-bottom:4rem">Mengetahui,<br>Kepala Sekolah</div><div style="font-weight:700;text-decoration:underline">{{ $kepsek->nama ?? '( ……………………… )' }}</div><div>NIP. {{ $kepsek->nip ?? '…' }}</div></div> <div><div style="margin-bottom:4rem">{{ $sekolah->kab_kota ?? '' }}, {{ now()->translatedFormat('d F Y') }}<br>Bendahara</div><div style="font-weight:700;text-decoration:underline">{{ $bendahara->nama ?? '( ……………………… )' }}</div><div>NIP. {{ $bendahara->nip ?? '…' }}</div></div> </div>
</div>
</div>
@endsection
