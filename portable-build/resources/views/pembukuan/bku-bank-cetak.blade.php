@extends('layouts.print', ['title' => 'BKU Pembantu Bank ' . $tahun])
@section('content')
@php
  // Angka kolom tanpa awalan Rp (pemisah ribuan titik), seperti cetakan ARKAS.
  $angka = fn ($v) => number_format((int) $v, 0, ',', '.');
  $rpTitik = fn ($v) => 'Rp. '.number_format((int) $v, 0, ',', '.');
  // Kaki halaman ARKAS: "BKU Pembantu Bank Tahun 2025 - NPSN : …, Nama Sekolah : …".
  // Nomor "Halaman X dari Y" dipasang layouts.print. Isi margin box tidak boleh
  // memuat kutip ganda, jadi nama sekolah dibersihkan ke karakter aman.
  $kakiKiri = trim(preg_replace('/[^\p{L}\p{N} .,\-\/:()]/u', ' ',
    'BKU Pembantu Bank Tahun '.$tahun.' - NPSN : '.$sekolah->npsn.', Nama Sekolah : '.$sekolah->nama));
@endphp

<div class="toolbar nonprint">
  <button type="button" data-cetak-halaman>🖨️ Cetak / Simpan PDF</button>
  <a href="{{ route('bku') }}">← Kembali ke BKU</a>
</div>

@include('partials._kop-dokumen-cetak', [
  'judul' => 'B U K U   K A S   P E M B A N T U   B A N K',
  'periode' => $labelPeriode,
  'penanda' => 'BKU - BANK',
  'sekolah' => $sekolah,
  'sumberDana' => $kopSumber,
  'revisiKe' => $revisiKe,
  'catatan' => 'Hanya mutasi rekening bank. Kolom Saldo = posisi bank berjalan; '
    .'baris Bunga Bank / Pajak Bunga berasal dari Tutup BKU tiap bulan.',
])

<table class="tbl">
  {{-- Susunan cetak ARKAS: baris JUDUL kolom lebih dulu, lalu baris nomor 1–8. --}}
  <thead>
    <tr>
      <th>TANGGAL</th>
      <th>KODE<br>KEGIATAN</th>
      <th>KODE<br>REKENING</th>
      <th>NO. BUKTI</th>
      <th>URAIAN</th>
      <th class="num">PENERIMAAN</th>
      <th class="num">PENGELUARAN</th>
      <th class="num">SALDO</th>
    </tr>
    <tr class="bku-nomor"><th>1</th><th>2</th><th>3</th><th>4</th><th>5</th><th class="num">6</th><th class="num">7</th><th class="num">8</th></tr>
  </thead>
  <tbody>
    {{-- Baris pembuka ARKAS: "Saldo Bank Bulan Desember <tahun lalu>". --}}
    <tr style="font-weight:600;background:#f8fafc">
      <td style="white-space:nowrap">01-01-{{ $tahun }}</td><td></td><td></td><td></td>
      <td>Saldo Bank Bulan Desember {{ $tahun - 1 }}</td>
      <td class="num">0</td><td class="num">0</td><td class="num">{{ $angka($saldoAwal) }}</td>
    </tr>
    @forelse($rows as $row)
      <tr>
        <td style="white-space:nowrap">{{ \Illuminate\Support\Carbon::parse($row['tanggal'])->format('d-m-Y') }}</td>
        <td>{{ $row['kegiatan'] }}</td>
        <td style="font-family:monospace;font-size:11px">{{ $row['rekening'] }}</td>
        <td>{{ $row['no_bukti'] }}</td>
        <td>{{ $row['uraian'] }}</td>
        <td class="num">{{ $angka($row['masuk']) }}</td>
        <td class="num">{{ $angka($row['keluar']) }}</td>
        <td class="num">{{ $angka($row['saldo']) }}</td>
      </tr>
    @empty
      <tr>
        <td colspan="8" class="text-center text-slate-400">
          Belum ada mutasi rekening bank pada tahun {{ $tahun }}.
        </td>
      </tr>
    @endforelse
  </tbody>
  <tfoot>
    <tr style="font-weight:700">
      <td colspan="5" style="text-align:right">Jumlah</td>
      <td class="num">{{ $angka($totalMasuk) }}</td>
      <td class="num">{{ $angka($totalKeluar) }}</td>
      <td class="num">{{ $angka($saldoAkhir) }}</td>
    </tr>
  </tfoot>
</table>

<div class="ttd">
  <div class="ttd-grid" style="grid-template-columns:1fr 1fr">
    <div>
      <div style="margin-bottom:4rem">Menyetujui,<br>Kepala Sekolah</div>
      <div style="font-weight:700;text-decoration:underline">{{ $kepsek->nama ?? '( ……………………… )' }}</div>
      <div>NIP. {{ $kepsek->nip ?? '…' }}</div>
    </div>
    <div>
      <div style="margin-bottom:4rem">{{ $tempatTtd }}, {{ $tglTutupTeks }}<br>Bendahara</div>
      <div style="font-weight:700;text-decoration:underline">{{ $bendahara->nama ?? '( ……………………… )' }}</div>
      <div>NIP. {{ $bendahara->nip ?? '…' }}</div>
    </div>
  </div>
</div>

<div style="font-size:12px;margin-top:1rem">
  Pada hari ini {{ $hariTutupTeks }} Buku Kas Umum Ditutup dengan keadaan/posisi buku sebagai berikut :
</div>
<div style="font-size:13px;margin-top:.3rem">Saldo Bank : <b>{{ $rpTitik($saldoAkhir) }}</b></div>

<style>
  @media print {
    @page {
      @bottom-left { content: "{!! $kakiKiri !!}"; font-size: 8pt; color: #334155; }
    }
  }
</style>
@endsection
