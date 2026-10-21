@extends('layouts.print', ['title' => 'SPTJM Dana BOSP Semester ' . $semester . ' TA ' . $tahun])
@section('content')
<div class="toolbar nonprint">
  <button onclick="window.print()">🖨️ Cetak / Simpan PDF SPTJM</button>
  <a href="{{ route('pembukuan.sptjm', ['semester' => 1]) }}">Semester 1 (Jan–Jun)</a>
  <a href="{{ route('pembukuan.sptjm', ['semester' => 2]) }}">Semester 2 (Jul–Des)</a>
  <a href="{{ route('bku') }}">← Kembali ke BKU</a>
</div>

<div class="kop" style="border-bottom:3px double #0f172a;padding-bottom:10px;margin-bottom:18px;text-align:center">
  <div style="font-size:14px;font-weight:800;text-transform:uppercase">PEMERINTAH {{ strtoupper($sekolah->kab_kota ?: $sekolah->provinsi ?: 'DAERAH') }}</div>
  <div style="font-size:16px;font-weight:900;text-transform:uppercase">{{ $sekolah->nama }}</div>
  <div style="font-size:11px">NPSN: {{ $sekolah->npsn }} · {{ $sekolah->alamat }} {{ $sekolah->kecamatan ? 'Kec. ' . $sekolah->kecamatan : '' }} {{ $sekolah->kab_kota }}</div>
</div>

<div style="text-align:center;margin-bottom:18px">
  <div style="font-weight:800;font-size:14px;text-decoration:underline;letter-spacing:.05em">SURAT PERNYATAAN TANGGUNG JAWAB MUTLAK (SPTJM)</div>
  <div style="font-size:12px;margin-top:2px">Nomor : {{ $nomorSurat }}</div>
</div>

<div style="font-size:12.5px;line-height:1.75;text-align:justify">
  <p>Saya yang bertanda tangan di bawah ini menyatakan bahwa bertanggung jawab secara formal dan material atas kebenaran realisasi penerimaan dan pengeluaran Dana BOSP serta kebenaran perhitungan dan setoran pajak yang telah dipungut atas penggunaan Dana BOSP pada <b>Semester {{ $semester }} ({{ $semester === 1 ? 'Januari s.d. Juni' : 'Juli s.d. Desember' }}) Tahun Anggaran {{ $tahun }}</b> dengan rincian sebagai berikut:</p>

  <table class="tbl" style="margin:14px 0;font-size:12px">
    <tbody>
      <tr><td style="width:65%"><b>A. Saldo Awal Kas Dana BOSP</b></td><td class="num"><b>{{ rp($saldoAwal) }}</b></td></tr>
      <tr><td><b>B. Penerimaan Dana BOSP Semester {{ $semester }}</b></td><td class="num"><b>{{ rp($penerimaan) }}</b></td></tr>
      <tr><td><b>C. Jumlah Dana Tersedia (A + B)</b></td><td class="num"><b>{{ rp($saldoAwal + $penerimaan) }}</b></td></tr>
      <tr><td><b>D. Pengeluaran / Realisasi Belanja Semester {{ $semester }}</b></td><td class="num"><b>{{ rp($pengeluaran) }}</b></td></tr>
      <tr style="background:#f8fafc"><td><b>E. Sisa Dana / Saldo Akhir Kas BOSP (C − D)</b></td><td class="num"><b>{{ rp($sisaKas) }}</b></td></tr>
    </tbody>
  </table>

  <p>Bukti-bukti pengeluaran kas dan setoran pajak yang sah sebagaimana dimaksud di atas disimpan pada satuan pendidikan <b>{{ $sekolah->nama }}</b> sesuai ketentuan Peraturan Menteri Pendidikan Dasar dan Menengah Nomor 8 Tahun 2026 tentang Petunjuk Teknis Pengelolaan Dana BOSP untuk kelengkapan administrasi dan keperluan pemeriksaan aparat pengawas fungsional.</p>

  <p style="margin-top:10px">Demikian Surat Pernyataan Tanggung Jawab Mutlak ini dibuat dengan sebenarnya dalam keadaan sadar dan penuh tanggung jawab. Apabila di kemudian hari ditemukan ketidaksesuaian, saya bersedia bertanggung jawab sesuai peraturan perundang-undangan yang berlaku.</p>
</div>

<div class="ttd" style="margin-top:28px">
  <div class="ttd-grid" style="grid-template-columns:1fr 1fr">
    <div>
      <div style="margin-bottom:4.5rem">Mengetahui,<br>Bendahara Dana BOSP</div>
      <div style="font-weight:700;text-decoration:underline">{{ $bendahara->nama ?? '( ……………………… )' }}</div>
      <div>NIP. {{ $bendahara->nip ?? '…' }}</div>
    </div>
    <div>
      <div style="margin-bottom:4.5rem">{{ $sekolah->kab_kota ?? 'Makassar' }}, {{ \Carbon\Carbon::parse($akhir)->translatedFormat('d F Y') }}<br>Kepala {{ $sekolah->nama }}<br><span style="font-size:10px;color:#64748b">(Materai Rp10.000)</span></div>
      <div style="font-weight:700;text-decoration:underline">{{ $kepsek->nama ?? '( ……………………… )' }}</div>
      <div>NIP. {{ $kepsek->nip ?? '…' }}</div>
    </div>
  </div>
</div>
@endsection
