@extends('layouts.app', ['title' => 'LPJ ' . ($cakupan ?? $stage->nama) . ' ' . $tahun])
@section('content')
<div class="dokumen">
@php
  $kepsek = $penandatangan->firstWhere('jabatan', 'Kepala Sekolah') ?? $penandatangan->first(fn($p) => stripos($p->jabatan, 'kepala') !== false);
  $bendahara = $penandatangan->first(fn($p) => stripos($p->jabatan, 'bendahara') !== false) ?? $penandatangan->skip(1)->first();
  $namaTahap = $cakupan ?? $stage->nama;
  $ketSaldo = $cakupan ? 'Saldo awal tahun (SILPA)' : 'Saldo awal tahap (tunai + bank)';
  $ketTerima = $cakupan ? 'Penerimaan kumulatif (seluruh jenis penerimaan)' : 'Penerimaan selama tahap (seluruh jenis penerimaan)';
  $ketKeluar = $cakupan ? 'Pengeluaran kumulatif (belanja, setor pajak, mutasi kas)' : 'Pengeluaran selama tahap (belanja, setor pajak, mutasi kas)';
@endphp

@if(!empty($review))
<div class="toolbar nonprint" style="background:#fffbeb;border:1px solid #fcd34d;border-radius:.75rem;padding:.75rem">
  <span style="font-size:13px;font-weight:700;color:#92400e">🔍 MODE TINJAUAN DINAS — LPJ {{ $review->school->nama }} · {{ $stage->nama }} {{ $tahun }} (status: {{ strtoupper($review->status) }})</span>
  <button type="button" data-cetak-halaman>🖨️ Cetak</button>
  @if($review->status !== 'diterima')
  <form method="post" action="{{ route('dinas.lpj.terima', $review) }}" data-konfirmasi="Terima LPJ ini?" style="display:inline">@csrf
    <button style="background:#059669;color:#fff;font-weight:700;border-color:#059669">✅ Terima</button>
  </form>
  @endif
  @if($review->status === 'dikirim')
  <details style="position:relative;display:inline-block">
    <summary style="border:1px solid #fca5a5;border-radius:.5rem;padding:.4rem .8rem;font-size:.82rem;background:#dc2626;color:#fff;font-weight:600;cursor:pointer;list-style:none">❌ Tolak</summary>
    <form method="post" action="{{ route('dinas.lpj.tolak', $review) }}" style="position:absolute;left:0;margin-top:.5rem;width:18rem;background:#fff;border:1px solid #e2e8f0;border-radius:.75rem;box-shadow:0 10px 25px rgb(0 0 0/.15);padding:.75rem;z-index:50">
      @csrf
      <label style="font-size:11px;font-weight:600;color:#475569">Alasan penolakan (wajib)</label>
      <textarea name="catatan_dinas" rows="3" style="border:1px solid #cbd5e1;border-radius:.4rem;width:100%;padding:.4rem;font-size:12px;margin-top:.25rem" required minlength="5"></textarea>
      <button style="border-radius:.4rem;padding:.4rem;font-size:12px;background:#dc2626;color:#fff;font-weight:600;margin-top:.5rem;width:100%">Kirim Penolakan</button>
    </form>
  </details>
  @endif
  <a href="{{ route('dinas.lpj.pdf', $review) }}" style="background:#fef2f2;border-color:#fca5a5">📕 PDF</a>
  <a href="{{ route('dinas.lpj.xlsx', $review) }}" style="background:#f0fdf4;border-color:#86efac">📗 Excel</a>
  <a href="{{ route('dinas.lpj') }}">← Kembali ke LPJ Masuk</a>
</div>
@else
<div class="toolbar nonprint">
  <a href="{{ route('laporan.lpj.pdf', ['stage' => $stage->id, 'kumulatif' => !empty($cakupan) ? 1 : null]) }}" style="background:#fef2f2;border-color:#fca5a5">📕 Unduh PDF Asli</a>
  <a href="{{ route('laporan.lpj.xlsx', ['stage' => $stage->id, 'kumulatif' => !empty($cakupan) ? 1 : null]) }}" style="background:#f0fdf4;border-color:#86efac">📗 Unduh Excel (4 Sheet)</a>
  <form style="display:flex;gap:.5rem;align-items:center;font-size:.82rem" method="get">
    <span>Tahap:</span>
    @if(!empty($cakupan))<input type="hidden" name="kumulatif" value="1">@endif
    <select name="stage" style="border:1px solid #cbd5e1;border-radius:.5rem;padding:.35rem .6rem" data-kirim-otomatis>
      @foreach($stages as $s)<option value="{{ $s->id }}" @selected($stage->id===$s->id)>{{ $s->nama }}</option>@endforeach
    </select>
  </form>
  @if(empty($cakupan))
  <a href="{{ route('laporan.lpj', ['stage' => $stage->id, 'kumulatif' => 1]) }}" title="Akumulasi dari Tahap 1 sampai tahap ini (laporan triwulan/semester)" style="background:#f5f3ff;border-color:#c4b5fd;font-weight:700">🔁 Mode Kumulatif</a>
  @else
  <span style="font-size:12px;font-weight:700;color:#6d28d9;background:#ede9fe;border-radius:999px;padding:.2rem .7rem">🔁 KUMULATIF · Tahap 1 – {{ $stage->nama }}</span>
  <a href="{{ route('laporan.lpj', ['stage' => $stage->id]) }}" style="border:1px solid #cbd5e1;border-radius:.5rem;padding:.4rem .8rem;font-size:.82rem">↩️ Tahap Ini Saja</a>
  @endif
  <button type="button" data-cetak-halaman>🖨️ Cetak / Simpan sebagai PDF</button>
  <a href="{{ route('laporan.lpj.zip', ['stage' => $stage->id]) }}" style="background:#ecfdf5;border-color:#6ee7b7">⬇️ Unduh Paket LPJ (ZIP·CSV)</a>
  <a href="{{ route('laporan.tahap', ['stage' => $stage->id]) }}">← Laporan per Tahap</a>
</div>
@endif

{{-- ======== 1. SAMPUL ======== --}}
<section class="text-center pt-10">
  <div class="text-xs uppercase tracking-wide">Pemerintah {{ $sekolah->kab_kota }} — {{ $sekolah->provinsi }}</div>
  <h1 class="text-2xl font-extrabold uppercase mt-16">Laporan Pertanggungjawaban<br>Penggunaan Dana BOSP</h1>
  <div class="text-xl font-bold mt-6">{{ $namaTahap }} — Tahun Anggaran {{ $tahun }}</div>
  <div class="mt-24 text-lg font-bold uppercase">{{ $sekolah->nama }}</div>
  <div class="text-sm mt-1">NPSN {{ $sekolah->npsn }}</div>
  <div class="text-sm">{{ $sekolah->alamatLengkap() }}</div>
</section>

{{-- ======== 2. LEMBAR PENGESAHAN ======== --}}
<section class="pg">
  <h2 class="text-center font-bold uppercase">Lembar Pengesahan</h2>
  <div class="mt-8 text-sm leading-7 text-justify">
    Pada hari ini, kami yang bertanda tangan di bawah ini menyatakan bahwa Laporan Pertanggungjawaban
    penggunaan dana Bantuan Operasional Satuan Pendidikan (BOSP) <b>{{ $namaTahap }} Tahun Anggaran {{ $tahun }}</b>
    pada <b>{{ $sekolah->nama }}</b> telah disusun berdasarkan buku kas umum dan bukti-bukti transaksi yang sah,
    dengan perincian sebagai berikut:
    </div>
  <table class="tbl mt-4">
    <tbody>
      <tr><td class="w-2/3">{{ $ketSaldo }}</td><td class="num font-bold">{{ rp($saldoAwal) }}</td></tr>
      <tr><td>{{ $ketTerima }}</td><td class="num font-bold">{{ rp($totalMasuk) }}</td></tr>
      <tr><td>{{ $ketKeluar }}</td><td class="num font-bold">{{ rp($totalKeluar) }}</td></tr>
      <tr><td class="font-bold">{{ $cakupan ? 'Saldo akhir (kumulatif)' : 'Saldo kas akhir tahap' }}</td><td class="num font-bold">{{ rp($saldoAwal + $totalMasuk - $totalKeluar) }}</td></tr>
      <tr><td>Total belanja barang/jasa &amp; modal tercatat di BKU {{ $cakupan ? '(kumulatif)' : 'tahap ini' }}</td><td class="num font-bold">{{ rp($totalBelanja) }}</td></tr>
    </tbody>
  </table>
  <div class="mt-4 text-sm leading-7 text-justify">
    Demikian laporan pertanggungjawaban ini dibuat dengan sebenar-benarnya untuk digunakan sebagaimana mestinya.
  </div>
  @include('laporan.ttd')
</section>

{{-- ======== 3. BUKU KAS UMUM TAHAP ======== --}}
<section class="pg">
  <div class="kop">
    <div class="text-[11px] uppercase">{{ $sekolah->nama }} — NPSN {{ $sekolah->npsn }}</div>
    <div class="font-bold">BUKU KAS UMUM — {{ strtoupper($namaTahap) }} TAHUN {{ $tahun }}</div>
  </div>
  <table class="tbl">
    <thead><tr><th>No BKU</th><th>Tanggal</th><th>Uraian</th><th>Jenis</th><th class="num">Masuk (Rp)</th><th class="num">Keluar (Rp)</th><th class="num">Saldo (Rp)</th></tr></thead>
    <tbody>
      <tr class="font-semibold bg-slate-50"><td></td><td></td><td>{{ strtoupper($ketSaldo) }}</td><td></td><td></td><td></td><td class="num">{{ rp($saldoAwal) }}</td></tr>
      @foreach($bkuRows as $row)
      <tr>
        <td>#{{ $row['trx']->no_bku }}</td><td class="whitespace-nowrap">{{ $row['trx']->tanggal->format('d/m/Y') }}</td>
        <td>{{ $row['trx']->keterangan }}</td><td>{{ $row['trx']->labelType() }}</td>
        <td class="num">{{ $row['masuk'] ? rp($row['masuk']) : '' }}</td><td class="num">{{ $row['keluar'] ? rp($row['keluar']) : '' }}</td>
        <td class="num">{{ rp($row['saldo']) }}</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot><tr class="font-bold"><td colspan="4" class="text-right">{{ $cakupan ? 'JUMLAH (KUMULATIF)' : 'JUMLAH' }}</td><td class="num">{{ rp($totalMasuk) }}</td><td class="num">{{ rp($totalKeluar) }}</td><td class="num">{{ rp($saldoAwal + $totalMasuk - $totalKeluar) }}</td></tr></tfoot>
  </table>
</section>

{{-- ======== 4. RINCIAN BELANJA ======== --}}
<section class="pg">
  <div class="kop">
    <div class="text-[11px] uppercase">{{ $sekolah->nama }} — NPSN {{ $sekolah->npsn }}</div>
    <div class="font-bold">RINCIAN BELANJA — {{ strtoupper($namaTahap) }} TAHUN {{ $tahun }}</div>
  </div>
  <table class="tbl">
    <thead><tr><th>Tanggal</th><th>Uraian Belanja</th><th>Kode Rekening</th><th class="num">Nilai (Rp)</th></tr></thead>
    <tbody>
      @php $totRinci = 0; @endphp
      @forelse($items->where('type', 'belanja') as $t)
        @foreach($t->details as $d)
        <tr>
          <td class="whitespace-nowrap">{{ $t->tanggal->format('d/m/Y') }}</td>
          <td>{{ $d->uraian ?? $t->keterangan }}</td>
          <td class="font-mono">{{ $d->rekening->kode ?? '' }}</td>
          <td class="num">{{ rp($d->nilai) }}</td>
        </tr>
        @php $totRinci += $d->nilai; @endphp
        @endforeach
      @empty
        <tr><td colspan="4" class="text-center text-slate-400 italic">Belum ada belanja pada tahap ini.</td></tr>
      @endforelse
    </tbody>
    <tfoot><tr class="font-bold"><td colspan="3" class="text-right">TOTAL BELANJA {{ strtoupper($namaTahap) }}</td><td class="num">{{ rp($totRinci) }}</td></tr></tfoot>
  </table>
</section>

{{-- ======== 5. REKAP PER REKENING ======== --}}
<section class="pg">
  <div class="kop">
    <div class="text-[11px] uppercase">{{ $sekolah->nama }} — NPSN {{ $sekolah->npsn }}</div>
    <div class="font-bold">REKAPITULASI BELANJA PER REKENING — {{ strtoupper($namaTahap) }} TAHUN {{ $tahun }}</div>
  </div>
  <table class="tbl">
    <thead><tr><th>Kode Rekening</th><th>Nama Rekening</th><th>Komponen</th><th class="num">Jumlah (Rp)</th></tr></thead>
    <tbody>
      @php $totRekap = 0; @endphp
      @forelse($rekapRekening as $r)
      <tr>
        <td class="font-mono">{{ $r['rekening']->kode ?? '' }}</td>
        <td>{{ $r['rekening']->nama ?? '(rekening dihapus)' }}</td>
        <td>{{ $r['rekening']?->komponen?->nama ?? '' }}</td>
        <td class="num">{{ rp($r['jumlah']) }}</td>
      </tr>
      @php $totRekap += $r['jumlah']; @endphp
      @empty
        <tr><td colspan="4" class="text-center text-slate-400 italic">—</td></tr>
      @endforelse
    </tbody>
    <tfoot><tr class="font-bold"><td colspan="3" class="text-right">TOTAL</td><td class="num">{{ rp($totRekap) }}</td></tr></tfoot>
  </table>
</section>

{{-- ======== 6. REKAP PAJAK (K7) ======== --}}
<section class="pg">
  <div class="kop">
    <div class="text-[11px] uppercase">{{ $sekolah->nama }} — NPSN {{ $sekolah->npsn }}</div>
    <div class="font-bold">REKAPITULASI PAJAK (K7) — {{ strtoupper($namaTahap) }} TAHUN {{ $tahun }}</div>
  </div>
  <table class="tbl">
    <thead><tr><th>Jenis Pajak</th><th class="num">Dipungut (Rp)</th><th class="num">Disetor (Rp)</th><th class="num">Belum Disetor (Rp)</th></tr></thead>
    <tbody>
      @php $td=0; $ts=0; $tb=0; @endphp
      @forelse($rekapPajak as $p)
      <tr>
        <td>{{ $p['nama'] }}</td>
        <td class="num">{{ rp($p['dipungut']) }}</td>
        <td class="num">{{ rp($p['disetor']) }}</td>
        <td class="num">{{ rp($p['belum']) }}</td>
      </tr>
      @php $td+=$p['dipungut']; $ts+=$p['disetor']; $tb+=$p['belum']; @endphp
      @empty
        <tr><td colspan="4" class="text-center text-slate-400 italic">Tidak ada pajak dipungut pada tahap ini.</td></tr>
      @endforelse
    </tbody>
    <tfoot><tr class="font-bold"><td class="text-right">TOTAL</td><td class="num">{{ rp($td) }}</td><td class="num">{{ rp($ts) }}</td><td class="num">{{ rp($tb) }}</td></tr></tfoot>
  </table>
  @include('laporan.ttd')
</section>
</div>
@endsection
