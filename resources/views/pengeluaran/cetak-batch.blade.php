@extends('layouts.print', ['title' => 'Berkas Kuitansi Belanja — Bulan ' . \Carbon\Carbon::parse($bulan.'-01')->translatedFormat('F Y')])

@section('content')
<style>
  @media print {
    .halaman-kuitansi {
      page-break-after: always;
      break-after: page;
      margin-bottom: 0 !important;
      padding-bottom: 0 !important;
    }
  }
  .halaman-kuitansi {
    background: #fff;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  }
  .card {
    text-align: center;
    padding: 3rem;
    background: white;
    border-radius: 0.5rem;
    border: 1px dashed #cbd5e1;
  }
  @media print {
    .halaman-kuitansi {
      box-shadow: none !important;
      padding: 0 !important;
      border: none !important;
    }
  }
</style>

<div class="toolbar nonprint" style="margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; background: #f8fafc; padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid #e2e8f0;">
  <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
    <button type="button" data-cetak-halaman style="background: #0b5fef; color: white; border: none; font-weight: 600; padding: 0.45rem 1rem; border-radius: 0.4rem; cursor: pointer;">
      🖨️ Cetak / Simpan PDF ({{ $items->count() }} Kuitansi)
    </button>
    <a href="{{ route('belanja.index') }}" style="color: #475569; text-decoration: none; padding: 0.45rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 0.4rem; background: white;">
      ← Kembali ke Daftar Belanja
    </a>
  </div>

  <form method="GET" action="{{ route('belanja.cetak-batch') }}" style="display: flex; align-items: center; gap: 0.5rem;">
    <label for="pilih-bulan" style="font-size: 13px; font-weight: 600; color: #475569;">Bulan:</label>
    <select id="pilih-bulan" name="bulan" data-kirim-otomatis style="padding: 0.35rem 0.6rem; border: 1px solid #cbd5e1; border-radius: 0.4rem; font-size: 13px;">
      @forelse($daftarBulan as $b)
        <option value="{{ $b }}" @selected($b === $bulan)>{{ \Carbon\Carbon::parse($b.'-01')->translatedFormat('F Y') }}</option>
      @empty
        <option value="{{ $bulan }}">{{ \Carbon\Carbon::parse($bulan.'-01')->translatedFormat('F Y') }}</option>
      @endforelse
    </select>
  </form>
</div>

@if($items->isEmpty())
  <div class="card" style="text-align: center; padding: 3rem; background: white; border-radius: 0.5rem; border: 1px dashed #cbd5e1;">
    <p style="font-size: 16px; color: #64748b; font-weight: 500;">Tidak ada transaksi belanja pada bulan {{ \Carbon\Carbon::parse($bulan.'-01')->translatedFormat('F Y') }}.</p>
    <a href="{{ route('belanja.index') }}" style="display: inline-block; margin-top: 1rem; color: #0b5fef; text-decoration: underline;">Kembali ke transaksi belanja</a>
  </div>
@else
  @foreach($items as $index => $t)
    @php
      $totalPajak = (int) $t->taxes->sum('nilai');
      $netto = (int) $t->total - $totalPajak;
    @endphp

    <div class="halaman-kuitansi {{ $loop->last ? '' : 'pg' }}">
      {{-- Kop Surat Sekolah --}}
      <div class="kop" style="border-bottom: 2px solid #0f172a; padding-bottom: 0.5rem; margin-bottom: 1rem; text-align: center;">
        <div style="font-size: 11px; text-transform: uppercase; color: #475569; letter-spacing: 0.05em;">{{ $sekolah->kab_kota }} — {{ $sekolah->provinsi }}</div>
        <div style="font-size: 18px; font-weight: 800; text-transform: uppercase; color: #0f172a; margin: 0.15rem 0;">{{ $sekolah->nama }}</div>
        <div style="font-size: 11px; color: #64748b;">NPSN: {{ $sekolah->npsn }} · {{ $sekolah->alamatLengkap() }}</div>
      </div>

      {{-- Judul Kwitansi & Nomor --}}
      <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem;">
        <h1 style="font-size: 18px; font-weight: 800; letter-spacing: 0.15em; border: 2px solid #1e293b; border-radius: 0.4rem; padding: 0.2rem 0.8rem; margin: 0;">
          KWITANSI PEMBAYARAN
        </h1>
        <div style="font-size: 12px; text-align: right; line-height: 1.4;">
          <div>Nomor Bukti: <b>{{ $t->no_bukti ?: 'BKU/' . str_pad($t->no_bku, 3, '0', STR_PAD_LEFT) . '/' . $t->tanggal->format('m/Y') }}</b></div>
          <div>Tanggal: <b>{{ $t->tanggal->translatedFormat('d F Y') }}</b> · No. Urut BKU #<b>{{ $t->no_bku }}</b></div>
        </div>
      </div>

      {{-- Identitas Penerimaan --}}
      <div style="margin-bottom: 1.25rem; font-size: 13px; display: grid; gap: 0.5rem;">
        <div style="display: grid; grid-template-columns: 11rem 1fr; gap: 0.25rem;">
          <div style="color: #64748b;">Telah diterima dari</div>
          <div style="font-weight: 600;">: Bendahara BOSP {{ $sekolah->nama }}</div>
        </div>
        <div style="display: grid; grid-template-columns: 11rem 1fr; gap: 0.25rem;">
          <div style="color: #64748b;">Uang sejumlah</div>
          <div style="font-weight: 700; font-style: italic; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 0.3rem; padding: 0.3rem 0.6rem;">
            : {{ ucfirst(terbilang($t->total)) }} rupiah
          </div>
        </div>
        <div style="display: grid; grid-template-columns: 11rem 1fr; gap: 0.25rem;">
          <div style="color: #64748b;">Untuk keperluan pembayaran</div>
          <div>: {{ $t->keterangan }}</div>
        </div>
      </div>

      {{-- Rincian Rekening & Pajak --}}
      <table class="tbl" style="width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; font-size: 12px;">
        <thead>
          <tr style="background: #f1f5f9;">
            <th style="border: 1px solid #94a3b8; padding: 0.35rem 0.5rem; text-align: left;">Uraian Belanja / Transaksi</th>
            <th style="border: 1px solid #94a3b8; padding: 0.35rem 0.5rem; width: 22%; text-align: left;">Kode Rekening</th>
            <th style="border: 1px solid #94a3b8; padding: 0.35rem 0.5rem; width: 22%; text-align: right;">Jumlah (Rp)</th>
          </tr>
        </thead>
        <tbody>
          @foreach($t->details as $d)
            <tr>
              <td style="border: 1px solid #cbd5e1; padding: 0.35rem 0.5rem;">{{ $d->uraian ?? $t->keterangan }}</td>
              <td style="border: 1px solid #cbd5e1; padding: 0.35rem 0.5rem; font-family: monospace;">{{ $d->rekening->kode ?? '-' }}</td>
              <td style="border: 1px solid #cbd5e1; padding: 0.35rem 0.5rem; text-align: right;">{{ rp($d->nilai) }}</td>
            </tr>
          @endforeach
          <tr style="font-weight: 700; background: #f8fafc;">
            <td colspan="2" style="border: 1px solid #cbd5e1; padding: 0.35rem 0.5rem; text-align: right;">TOTAL BELANJA</td>
            <td style="border: 1px solid #cbd5e1; padding: 0.35rem 0.5rem; text-align: right;">{{ rp($t->total) }}</td>
          </tr>
          @if($t->taxes->isNotEmpty())
            @foreach($t->taxes as $p)
              <tr style="color: #b91c1c; font-size: 11px;">
                <td colspan="2" style="border: 1px solid #cbd5e1; padding: 0.25rem 0.5rem;">
                  Potongan {{ $p->rekeningPajak->nama ?? 'Pajak' }} {{ $p->kode_billing ? '(Billing: '.$p->kode_billing.')' : '' }}
                </td>
                <td style="border: 1px solid #cbd5e1; padding: 0.25rem 0.5rem; text-align: right;">- {{ rp($p->nilai) }}</td>
              </tr>
            @endforeach
            <tr style="font-weight: 700; background: #ecfdf5; color: #065f46;">
              <td colspan="2" style="border: 1px solid #cbd5e1; padding: 0.35rem 0.5rem; text-align: right;">JUMLAH DIBAYARKAN (NETTO)</td>
              <td style="border: 1px solid #cbd5e1; padding: 0.35rem 0.5rem; text-align: right;">{{ rp($netto) }}</td>
            </tr>
          @endif
        </tbody>
      </table>

      {{-- Tanda Tangan Resmi --}}
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; text-align: center; font-size: 12px; margin-top: 1.5rem;">
        <div>
          <div style="margin-bottom: 4rem;">
            Penerima Pembayaran,<br>
            <span style="font-size: 10px; color: #64748b;">(Tanda tangan &amp; stempel rekanan/penyedia)</span>
          </div>
          <div style="border-top: 1px solid #94a3b8; display: inline-block; min-width: 12rem; padding-top: 0.25rem; font-weight: 600;">
            ( ……………………………………… )
          </div>
        </div>

        <div>
          <div style="margin-bottom: 4rem;">
            Setuju dibayar,<br>
            {{ $sekolah->kab_kota ?? '' }}, {{ $t->tanggal->translatedFormat('d F Y') }}<br>
            <b>Bendahara BOSP</b>
          </div>
          <div style="border-top: 1px solid #94a3b8; display: inline-block; min-width: 12rem; padding-top: 0.25rem; font-weight: 700;">
            {{ $bendahara->nama ?? '( …………………………………… )' }}
          </div>
          <div style="font-size: 11px; color: #475569;">NIP. {{ $bendahara->nip ?? '…………………………………' }}</div>
        </div>
      </div>
    </div>
  @endforeach
@endif
@endsection
