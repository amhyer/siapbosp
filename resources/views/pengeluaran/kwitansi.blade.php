@extends('layouts.print', ['title' => 'Kwitansi #' . $t->no_bku])
@section('content')
@php
  $totalPajak = (int) $t->taxes->sum('nilai');
  $netto = (int) $t->total - $totalPajak;
  $bendahara = $penandatangan->first(fn($p) => stripos($p->jabatan, 'bendahara') !== false);
@endphp

<div class="toolbar nonprint"> <button type="button" data-cetak-halaman> Cetak / Simpan sebagai PDF</button> <a href="{{ route('belanja.show', $t) }}">← Detail Belanja</a>
</div> <div class="kop"> <div style="font-size:11px;text-transform:uppercase">{{ $sekolah->kab_kota }} — {{ $sekolah->provinsi }}</div> <div style="font-size:18px;font-weight:800;text-transform:uppercase">{{ $sekolah->nama }}</div> <div style="font-size:11px">NPSN {{ $sekolah->npsn }} · {{ $sekolah->alamatLengkap() }}</div>
</div> <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap"> <h1 style="font-size:20px;font-weight:800;letter-spacing:.2em;border:2px solid #334155;border-radius:.6rem;padding:.25rem 1rem;margin:0">KWITANSI</h1> <div style="font-size:13px;text-align:right"> <div>Nomor: <b>{{ $t->no_bukti ?: 'BKU/' . str_pad($t->no_bku, 3, '0', STR_PAD_LEFT) . '/' . $t->tanggal->format('m/Y') }}</b></div> <div>Tanggal: <b>{{ $t->tanggal->translatedFormat('d F Y') }}</b> · BKU #{{ $t->no_bku }}</div> </div>
</div> <div style="margin-top:1.25rem;font-size:14px;display:grid;gap:.7rem"> <div style="display:grid;grid-template-columns:11rem 1fr;gap:.25rem"><div style="color:#64748b">Telah diterima dari</div><div style="font-weight:600">: Bendahara BOSP {{ $sekolah->nama }}</div></div> <div style="display:grid;grid-template-columns:11rem 1fr;gap:.25rem"><div style="color:#64748b">Uang sejumlah</div><div style="font-weight:700;font-style:italic;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:.4rem;padding:.3rem .6rem">: {{ ucfirst(terbilang($t->total)) }} rupiah</div></div> <div style="display:grid;grid-template-columns:11rem 1fr;gap:.25rem"><div style="color:#64748b">Untuk pembayaran</div><div>: {{ $t->keterangan }}</div></div>
</div> <table class="tbl" style="margin-top:1.25rem"> <thead><tr><th>Uraian</th><th>Kode Rekening</th><th class="num">Nilai (Rp)</th></tr></thead> <tbody> @foreach($t->details as $d)
    <tr><td>{{ $d->uraian ?? $t->keterangan }}</td><td style="font-family:monospace">{{ $d->rekening->kode ?? '' }}</td><td class="num">{{ rp($d->nilai) }}</td></tr> @endforeach
    <tr style="font-weight:700;background:#f8fafc"><td colspan="2" style="text-align:right">NILAI KUITANSI</td><td class="num">{{ rp($t->total) }}</td></tr> @foreach($t->taxes as $p)
    <tr style="color:#b91c1c"><td colspan="2">Dipotong {{ $p->rekeningPajak->nama ?? 'pajak' }}{{ $p->kode_billing ? ' (billing ' . $p->kode_billing . ')' : '' }}</td><td class="num">- {{ rp($p->nilai) }}</td></tr> @endforeach
    @if($totalPajak > 0)
    <tr style="font-weight:700;background:#ecfdf5"><td colspan="2" style="text-align:right">JUMLAH DIBAYARKAN KEPADA PENERIMA</td><td class="num">{{ rp($netto) }}</td></tr> @endif
  </tbody>
</table> <div class="ttd"> <div class="ttd-grid"> <div> <div style="margin-bottom:5rem">Penerima pembayaran,<br><span style="color:#94a3b8;font-size:11px">(nama &amp; stempel penyedia, materai bila &gt; Rp 5.000.000)</span></div> <div style="border-top:1px solid #94a3b8;padding-top:.25rem;color:#94a3b8">( …………………………………… )</div> </div> <div> <div style="margin-bottom:5rem">{{ $sekolah->kab_kota ?? '' }}, {{ $t->tanggal->translatedFormat('d F Y') }}<br>Bendahara BOSP</div> <div style="font-weight:700;text-decoration:underline">{{ $bendahara->nama ?? '( ……………………… )' }}</div> <div>NIP. {{ $bendahara->nip ?? '…' }}</div> </div> </div>
</div>
@endsection
