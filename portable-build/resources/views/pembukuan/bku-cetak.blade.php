@extends('layouts.print', ['title' => 'BKU ' . $labelPeriode])
@section('content')
{{-- Watermark DRAFT dipasang oleh layouts.print; $isDraft/$draftPesan dari
     controller dihitung per rentang Format × Periode. --}}
@php
  $sekolah = auth()->user()->school;
  $tglAwal = $awal->format('d-m-Y');
  $tglAkhir = $akhir->format('d-m-Y');
  $gayaPilih = 'border:1px solid #cbd5e1;border-radius:.5rem;padding:.35rem .5rem;font-size:.82rem;background:#fff;font-family:inherit';
  // ARKAS 4 mencetak angka kolom tanpa awalan "Rp" (hanya pemisah ribuan titik),
  // sedangkan blok penutup memakai awalan "Rp.". Kedua bentuk disediakan di sini.
  $angka = fn ($v) => number_format((int) $v, 0, ',', '.');
  $rpTitik = fn ($v) => 'Rp. '.number_format((int) $v, 0, ',', '.');
@endphp
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

@php
  // Judul & blok identitas mengikuti cetakan BKU ARKAS 4.3.1: judul berjarak
  // lebar di tengah, baris periode di bawahnya, lalu label : nilai rata kiri
  // (NPSN … Sumber Dana) dengan tanda "BKU" di kanan.
  $BULAN_ID = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
  $bulanSebelum = $awal->copy()->subMonth();
  $labelBankAwal = $format === 'bulanan'
    ? 'Saldo Bank Bulan '.$BULAN_ID[(int) $bulanSebelum->format('n')].' '.$bulanSebelum->format('Y')
    : 'Saldo Bank ('.$labelSaldoAwal.')';
  $labelTunaiAwal = $format === 'bulanan'
    ? 'Saldo Tunai Bulan '.$BULAN_ID[(int) $bulanSebelum->format('n')].' '.$bulanSebelum->format('Y')
    : 'Saldo Tunai ('.$labelSaldoAwal.')';
  // SILPA tahun lalu tidak terbagi bank/tunai di KasService, jadi sisanya
  // dicetak sebagai baris sendiri supaya jumlah kolom Saldo tetap utuh.
  $silpaAwal = (int) $saldoAwal - ((int) $bankAwal + (int) $tunaiAwal);
@endphp

@include('partials._kop-dokumen-cetak', [
  'judul' => 'B U K U   K A S   U M U M',
  'periode' => $format === 'bulanan'
    ? 'BULAN : '.strtoupper($BULAN_ID[(int) $awal->format('n')]).' TAHUN : '.$tahun
    : 'PERIODE : '.$labelPeriode,
  'penanda' => 'BKU',
  'sekolah' => $sekolah,
  'sumberDana' => $kopSumber,
  'revisiKe' => $revisiKe,
  'catatan' => 'Format : <b>'.($formatPilihan[$format] ?? $format).'</b> · '.$catatan,
])

<table class="tbl">
  {{-- Susunan cetak ARKAS 4: baris JUDUL kolom lebih dulu, lalu baris nomor 1–8. --}}
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
    <tr style="font-weight:600;background:#f8fafc"><td>{{ $tglAwal }}</td><td></td><td></td><td></td><td>{{ $labelBankAwal }}</td><td class="num">0</td><td class="num">0</td><td class="num">{{ $angka($bankAwal) }}</td></tr>
    <tr style="font-weight:600;background:#f8fafc"><td>{{ $tglAwal }}</td><td></td><td></td><td></td><td>{{ $labelTunaiAwal }}</td><td class="num">0</td><td class="num">0</td><td class="num">{{ $angka($tunaiAwal) }}</td></tr>
    @if($silpaAwal !== 0)
      <tr style="font-weight:600;background:#f8fafc"><td>{{ $tglAwal }}</td><td></td><td></td><td></td><td>Saldo Awal Tahun (SILPA)</td><td class="num">0</td><td class="num">0</td><td class="num">{{ $angka($silpaAwal) }}</td></tr>
    @endif
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
      <td class="num">{{ $angka($row['masuk']) }}</td><td class="num">{{ $angka($row['keluar']) }}</td>
      <td class="num">{{ $angka($row['saldo']) }}</td></tr>
    @endforeach
  </tbody>
  <tfoot>
    <tr style="font-weight:700"><td colspan="5" style="text-align:right">Jumlah</td><td class="num">{{ $angka(collect($rows)->sum('masuk')) }}</td><td class="num">{{ $angka(collect($rows)->sum('keluar')) }}</td><td class="num">{{ $angka($saldoAkhir) }}</td></tr>
  </tfoot>
</table>

@php
  // Teks kaki kiri halaman ARKAS: "BKU <Bulan> <Tahun> - NPSN : …, Nama Sekolah : …".
  // Nomor "Halaman X dari Y" di kanan sudah dipasang layouts.print untuk semua
  // dokumen cetak. Isi margin box tidak boleh memuat kutip ganda (string CSS).
  // Nama sekolah dibersihkan ke karakter aman agar tetap valid di dalam string CSS.
  $kakiKiri = 'BKU '.$BULAN_ID[(int) $akhir->format('n')].' '.$akhir->format('Y')
    .' - NPSN : '.$sekolah->npsn.', Nama Sekolah : '.$sekolah->nama;
  $kakiKiri = trim(preg_replace('/[^\p{L}\p{N} .,\-\/:()]/u', ' ', $kakiKiri));
@endphp
<style>
  @media print {
    @page {
      @bottom-left { content: "{!! $kakiKiri !!}"; font-size: 8pt; color: #334155; }
    }
  }
</style>

<div style="font-size:12px;margin-top:1rem">Pada hari ini {{ $hariIniTeks }} Buku Kas Umum Ditutup dengan keadaan/posisi buku sebagai berikut :</div>
<div style="font-size:13px;margin-top:.3rem">Saldo Buku Kas Umum : <b>{{ $rpTitik($saldoAkhir) }}</b><br>Terdiri Dari :<br>- Saldo Bank : <b>{{ $rpTitik($bankAkhir) }}</b><br>- Saldo Kas Tunai : <b>{{ $rpTitik($tunaiAkhir) }}</b><br>Jumlah : <b>{{ $rpTitik($bankAkhir + $tunaiAkhir) }}</b></div>

@php
  $kepsek = $penandatangan->first(fn($p) => stripos($p->jabatan ?? '', 'kepala') !== false);
  $bendahara = $penandatangan->first(fn($p) => stripos($p->jabatan ?? '', 'bendahara') !== false);
  // ARKAS 4 mencetak dua kolom tanda tangan: Menyetujui/Kepala Sekolah dan
  // Bendahara, dengan tempat-tanggal memakai KECAMATAN (bukan kabupaten/kota).
  $tempatTtd = trim((string) ($sekolah->kecamatan ?? '')) !== ''
    ? 'Kec. '.$sekolah->kecamatan
    : (string) ($sekolah->kab_kota ?? '');
@endphp
<div class="ttd">
  <div class="ttd-grid" style="grid-template-columns:1fr 1fr">
    <div><div style="margin-bottom:4rem">Menyetujui,<br>Kepala Sekolah</div><div style="font-weight:700;text-decoration:underline">{{ $kepsek->nama ?? '( ……………………… )' }}</div><div>NIP. {{ $kepsek->nip ?? '…' }}</div></div>
    <div><div style="margin-bottom:4rem">{{ $tempatTtd }}, {{ $tglAkhirTeks }}<br>Bendahara</div><div style="font-weight:700;text-decoration:underline">{{ $bendahara->nama ?? '( ……………………… )' }}</div><div>NIP. {{ $bendahara->nip ?? '…' }}</div></div>
  </div>
</div>
@endsection
