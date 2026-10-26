@extends('layouts.print', ['title' => 'BKU ' . $labelPeriode])
@section('content')
@php
  $sekolah = auth()->user()->school;
  $tglAwal = $awal->format('d-m-Y');
  $tglAkhir = $akhir->format('d-m-Y');
  $gayaPilih = 'border:1px solid #cbd5e1;border-radius:.5rem;padding:.35rem .5rem;font-size:.82rem;background:#fff;font-family:inherit';
@endphp
@include('partials._watermark-draft')

{{-- Pemilih Format + Periode ala ARKAS 4 (tampil di layar, tidak ikut tercetak). --}}
<div class="toolbar nonprint">
  <form method="get" action="{{ route('bku.cetak') }}" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
    <label for="pilih-format" style="font-size:.82rem;font-weight:600">Format</label>
    <select id="pilih-format" name="format" data-kirim-otomatis style="{{ $gayaPilih }}">
      @foreach($formatPilihan as $kode => $label)
        <option value="{{ $kode }}" @selected($format === $kode)>{{ $label }}</option>
      @endforeach
    </select>
    <label for="pilih-periode" style="font-size:.82rem;font-weight:600">Periode</label>
    <input type="month" id="pilih-periode" name="bulan" value="{{ $bulan }}" data-kirim-otomatis style="{{ $gayaPilih }}">
    @if($format === 'tahapan' && $stages->isNotEmpty())
      <label for="pilih-tahap" style="font-size:.82rem;font-weight:600">Tahap</label>
      <select id="pilih-tahap" name="tahap" data-kirim-otomatis style="{{ $gayaPilih }}">
        @foreach($stages as $s)
          <option value="{{ $s->id }}" @selected($tahap && (int) $tahap->id === (int) $s->id)>{{ $s->nama }}</option>
        @endforeach
      </select>
    @endif
    <button type="submit">Terapkan</button>
  </form>
  <button type="button" data-cetak-halaman>🖨️ Cetak / Simpan PDF</button>
  <a href="{{ route('bku', ['bulan' => $bulan]) }}">← Kembali ke BKU</a>
</div>

<div class="kop">
  <div style="font-weight:800;letter-spacing:.35em">BUKU KAS UMUM</div>
  <div style="font-size:11px">Nama Sekolah : {{ $sekolah->nama }}</div>
  <div style="font-size:11px">Desa/Kecamatan : {{ trim(($sekolah->desa ?? '' ? $sekolah->desa . ', ' : '') . ($sekolah->kecamatan ?? ''), ', ') ?: '—' }}</div>
  <div style="font-size:11px">Kabupaten / Kota : {{ $sekolah->kab_kota }} &nbsp; Provinsi : {{ $sekolah->provinsi }}</div>
</div>
<div style="font-size:11px;margin-bottom:.4rem">NPSN : {{ $sekolah->npsn }}</div>
<div style="display:flex;justify-content:space-between;font-size:12px;font-weight:700;margin-bottom:.4rem;flex-wrap:wrap;gap:.4rem">
  <span>PERIODE : {{ $labelPeriode }}</span>
  <span>Sumber Dana : {{ $kopSumber }}{{ $revisiKe > 1 ? ' Perubahan Revisi Ke ' . ($revisiKe - 1) : '' }}</span>
</div>
<div style="font-size:10.5px;color:#475569;margin-bottom:.5rem">
  Format : <b>{{ $formatPilihan[$format] ?? $format }}</b> · {{ $catatan }}
</div>
<table class="tbl">
  <thead><tr><th>Tanggal</th><th>Kegiatan</th><th>Rekening</th><th>No. Bukti</th><th>Uraian</th><th class="num">Penerimaan (Rp)</th><th class="num">Pengeluaran (Rp)</th><th class="num">Saldo (Rp)</th></tr></thead>
  <tbody>
    <tr style="font-weight:600;background:#f8fafc"><td>{{ $tglAwal }}</td><td></td><td></td><td></td><td>{{ $labelSaldoAwal }}</td><td class="num"></td><td class="num"></td><td class="num">{{ rp($saldoAwal) }}</td></tr>
    @foreach($rows as $row)
    @php
      $t = $row['trx'];
      $keg = ($t->details->first(fn($d) => $d->kode_kegiatan) ?? $t->details->first())?->kode_kegiatan ?? '';
      $kegLabel = $keg !== '' ? trim($keg . ' ' . ($kodeNama[$keg] ?? '')) : '';
      $rek = $t->details->map(fn($d) => $d->rekening->kode ?? '')->filter()->unique()->implode(', ');
    @endphp
    <tr><td style="white-space:nowrap">{{ $t->tanggal->format('d-m-Y') }}</td><td>{{ $kegLabel }}</td>
      <td style="font-family:monospace;font-size:11px">{{ $rek }}</td>
      <td>{{ $t->arkas_bku ?? ($t->no_bku ? 'BKU#' . $t->no_bku : '') }}</td>
      <td>{{ $t->keterangan }}</td>
      <td class="num">{{ $row['masuk'] ? rp($row['masuk']) : '' }}</td><td class="num">{{ $row['keluar'] ? rp($row['keluar']) : '' }}</td>
      <td class="num">{{ rp($row['saldo']) }}</td></tr>
    @endforeach
    <tr style="font-weight:600;background:#f8fafc"><td>{{ $tglAkhir }}</td><td></td><td></td><td></td><td>Pajak Bunga</td><td class="num"></td><td class="num"></td><td class="num">{{ rp($saldoAkhir) }}</td></tr>
  </tbody>
  <tfoot>
    <tr style="font-weight:700"><td colspan="5" style="text-align:right">JUMLAH</td><td class="num">{{ rp(collect($rows)->sum('masuk')) }}</td><td class="num">{{ rp(collect($rows)->sum('keluar')) }}</td><td class="num">{{ rp($saldoAkhir) }}</td></tr>
  </tfoot>
</table>

<div style="font-size:12px;margin-top:1rem">Pada hari ini {{ $hariIniTeks }} Buku Kas Umum Ditutup dengan keadaan/posisi buku sebagai berikut :</div>
<div style="font-size:13px;margin-top:.3rem">Saldo Buku Kas Umum : <b>{{ rp($saldoAkhir) }}</b><br>Terdiri Dari :<br>— Saldo Bank : <b>{{ rp($bankAkhir) }}</b><br>— Saldo Kas Tunai : <b>{{ rp($tunaiAkhir) }}</b><br>Jumlah : <b>{{ rp($bankAkhir + $tunaiAkhir) }}</b></div>

@php
  $kepsek = $penandatangan->first(fn($p) => stripos($p->jabatan ?? '', 'kepala') !== false);
  $bendahara = $penandatangan->first(fn($p) => stripos($p->jabatan ?? '', 'bendahara') !== false);
  $namaKomite = $penandatangan->first(fn($p) => stripos($p->jabatan ?? '', 'komite') !== false)?->nama ?? $sekolah->nama_komite ?? '( ……………………… )';
@endphp
<div class="ttd">
  <div class="ttd-grid" style="grid-template-columns:1fr 1fr 1fr">
    <div><div style="margin-bottom:4rem">Menyetujui,<br>Kepala Sekolah</div><div style="font-weight:700;text-decoration:underline">{{ $kepsek->nama ?? '( ……………………… )' }}</div><div>NIP. {{ $kepsek->nip ?? '…' }}</div></div>
    <div><div style="margin-bottom:4rem">{{ $sekolah->kab_kota ?? '' }}, {{ $tglAkhirTeks }}<br>Bendahara</div><div style="font-weight:700;text-decoration:underline">{{ $bendahara->nama ?? '( ……………………… )' }}</div><div>NIP. {{ $bendahara->nip ?? '…' }}</div></div>
    <div><div style="margin-bottom:4rem"><br>Komite Sekolah</div><div style="font-weight:700;text-decoration:underline">{{ $namaKomite }}</div><div>&nbsp;</div></div>
  </div>
</div>
@endsection
