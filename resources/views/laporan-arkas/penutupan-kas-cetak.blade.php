@extends('layouts.print', ['title' => 'Register Penutupan Kas'])
@section('content')
@php
  $nm = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  $kota = $sekolah->kab_kota ?? 'Makassar';
@endphp
<div class="toolbar nonprint"><button onclick="window.print()">🖨️ Cetak / Simpan PDF</button><a href="{{ route('laporan-arkas.penutupan-kas') }}?bulan={{ $tahun.'-'.str_pad($bulan,2,'0',STR_PAD_LEFT) }}">← Kembali</a></div>

<div style="text-align:center">
  <div style="font-size:13px;font-weight:800">{{strtoupper($sekolah->nama)}}</div>
  <div style="font-size:13px;font-weight:800;letter-spacing:.12em;margin-bottom:8px">REGISTER PENUTUPAN KAS</div>
</div>

<table style="width:100%;font-size:12px;border-collapse:collapse">
  <tbody style="vertical-align:top">
    <tr><td style="width:46%;padding:3px 0">Tanggal Penutupan Kas</td><td style="width:2%">:</td><td>{{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y') }}</td></tr>
    <tr><td>Nama Penutupan Kas/ Pemegang Kas</td><td>:</td><td>{{ $pemegang->nama ?? '( ……………………… )' }}</td></tr>
    <tr><td>Tanggal Penutupan Kas Bulan lalu</td><td>:</td><td>{{ \Carbon\Carbon::parse($tanggalLalu)->translatedFormat('d F Y') }}</td></tr>
    <tr><td>Jumlah Penerimaan (D)</td><td>:</td><td>{{ rp($D) }}</td></tr>
    <tr><td>Jumlah Pengeluaran (K)</td><td>:</td><td>{{ rp($K) }}</td></tr>
    <tr><td>Saldo Buku Kas Umum ( A = D - K )</td><td>:</td><td>{{ rp($A) }}</td></tr>
  </tbody>
</table>

<div style="font-size:12px;margin:10px 0 2px">Terdiri dari :</div>
<table class="tbl" style="width:100%;font-size:11.5px;border-collapse:collapse;margin-bottom:8px">
  <thead><tr><th style="width:22px">1.</th><th style="text-align:left">Lembar Uang Kertas</th><th class="num">Nilai</th><th class="num">Jumlah</th><th>Satuan</th><th class="num">Kali Nilai</th></tr></thead>
  <tbody>
    @foreach($denLembar as $d)
    <tr><td></td><td>Lembar Uang Kertas</td><td class="num">{{ rp($d['nilai']) }}</td><td class="num">{{ $d['jumlah'] }}</td><td style="text-align:center">lembar</td><td class="num">{{ rp($d['total']) }}</td></tr>
    @endforeach
    <tr><td colspan="4"></td><td style="text-align:right;font-weight:700">Jumlah sub (1)</td><td class="num" style="font-weight:700">{{ rp(collect($denLembar)->sum('total')) }}</td></tr>
  </tbody>
  <thead><tr><th>2.</th><th style="text-align:left">Keping Uang Logam</th><th class="num">Nilai</th><th class="num">Jumlah</th><th>Satuan</th><th class="num">Kali Nilai</th></tr></thead>
  <tbody>
    @foreach($denKeping as $d)
    <tr><td></td><td>Keping Uang Logam</td><td class="num">{{ rp($d['nilai']) }}</td><td class="num">{{ $d['jumlah'] }}</td><td style="text-align:center">keping</td><td class="num">{{ rp($d['total']) }}</td></tr>
    @endforeach
    <tr><td colspan="4"></td><td style="text-align:right;font-weight:700">Jumlah sub (2)</td><td class="num" style="font-weight:700">{{ rp(collect($denKeping)->sum('total')) }}</td></tr>
  </tbody>
  <thead><tr><th>3.</th><th style="text-align:left">Saldo Bank</th><th colspan="4"></th></tr></thead>
  <tbody>
    <tr><td></td><td>Saldo Bank</td><td colspan="3"></td><td class="num">{{ rp($bank) }}</td></tr>
    <tr><td></td><td>Pendapatan Jasa Bank</td><td colspan="3"></td><td class="num">{{ rp(0) }}</td></tr>
    <tr><td></td><td style="font-weight:700">( B ) Jumlah 1+2+3</td><td colspan="3"></td><td class="num" style="font-weight:700">{{ rp($tunai + $bank) }}</td></tr>
  </tbody>
</table>

<div style="font-size:12px">Perbedaan Kas ( A - B ) : <b>{{ rp($perbedaan) }}</b></div>
<div style="font-size:11px;color:#444;margin-top:2px">Penjelasan perbedaan Kas {{ $perbedaan != 0 ? '(isi bila ada selisih)' : '' }}</div>

<div style="margin-top:28px;font-size:12px">
  <div style="text-align:right;margin-bottom:14px">{{ $kota }}, {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y') }}</div>
  <table style="width:100%;border-collapse:collapse">
    <tr>
      <td style="width:50%;text-align:center">Yang diperiksa,<br>Bendahara Pemegang kas</td>
      <td style="width:50%;text-align:center">Yang Memeriksa,<br>Kepala Sekolah</td>
    </tr>
    <tr style="height:70px"><td></td><td></td></tr>
    <tr>
      <td style="text-align:center;font-weight:700;text-decoration:underline">{{ $pemegang->nama ?? '( ……………………… )' }}</td>
      <td style="text-align:center;font-weight:700;text-decoration:underline">{{ $kepsek->nama ?? '( ……………………… )' }}</td>
    </tr>
    <tr>
      <td style="text-align:center">NIP. {{ $pemegang->nip ?? '…' }}</td>
      <td style="text-align:center">NIP. {{ $kepsek->nip ?? '…' }}</td>
    </tr>
  </table>
</div>

<div style="page-break-before:always;margin-top:10px">
  <div style="text-align:center;font-weight:800;font-size:13px;letter-spacing:.08em">BERITA ACARA PEMERIKSAAN KAS</div>
  <div style="font-size:12px;margin-top:10px;line-height:1.7">
    Pada hari ini {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l d F Y') }} jam waktu sekolah setempat, kami yang bertanda tangan di bawah ini :<br>
    Bendahara Pemegang Kas : <b>{{ $pemegang->nama ?? '( ……………………… )' }}</b> — NIP {{ $pemegang->nip ?? '…' }}<br>
    Kepala Sekolah : <b>{{ $kepsek->nama ?? '( ……………………… )' }}</b> — NIP {{ $kepsek->nip ?? '…' }}<br><br>
    Setelah melakukan penghitungan bersama atas dana yang ditempatkan pada kas sekolah, di gelas/uang kertas sebanyak
    <b>{{ rp($tunai) }}</b> {{ $tunai > 0 ? '( ' . number_format($tunai, 0, ',', '.') . ' )' : '' }} dan saldo bank sebesar
    <b>{{ rp($bank) }}</b> {{ $bank > 0 ? '( ' . number_format($bank, 0, ',', '.') . ' )' : '' }}, sehingga total Buku Kas Umum pada
    {{ strtoupper($nm[$bulan]) }} {{ $tahun }} adalah sebesar <b>{{ rp($A) }}</b> {{ '(' . number_format($A, 0, ',', '.') . ')' }}.<br><br>
    Adapun hasil penghitungan tersebut sudah sesuai atau benar — kas ditutup pada tanggal
    {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y') }}.
  </div>
  <table style="width:100%;border-collapse:collapse;margin-top:36px">
    <tr>
      <td style="width:50%;text-align:center">Yang diperiksa,<br>Bendahara Pemegang kas</td>
      <td style="width:50%;text-align:center">Yang Memeriksa,<br>Kepala Sekolah</td>
    </tr>
    <tr style="height:70px"><td></td><td></td></tr>
    <tr>
      <td style="text-align:center;font-weight:700;text-decoration:underline">{{ $pemegang->nama ?? '( ……………………… )' }}</td>
      <td style="text-align:center;font-weight:700;text-decoration:underline">{{ $kepsek->nama ?? '( ……………………… )' }}</td>
    </tr>
    <tr>
      <td style="text-align:center">NIP. {{ $pemegang->nip ?? '…' }}</td>
      <td style="text-align:center">NIP. {{ $kepsek->nip ?? '…' }}</td>
    </tr>
  </table>
</div>
@endsection
