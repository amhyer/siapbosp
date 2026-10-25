@extends('layouts.app', ['title' => 'Buku Kas Umum (BKU)'])
@section('content')
@php
  $terkunciBulanIni = in_array($bulan, $terkunciMap, true);
  $tglBulan = \Carbon\Carbon::parse($bulan.'-01');
  $serapan = $pagu > 0 ? min(100, round($belanjaTh / $pagu * 100)) : 0;
  $statusWarna = [
    'belum-pajak' => 'bg-amber-100 border-amber-400 text-amber-900',
    'tertutup' => 'bg-rose-100 border-rose-300 text-rose-700',
    'selesai' => 'bg-slate-100 border-slate-300 text-slate-600',
    'draf' => 'bg-indigo-50 border-indigo-300 text-indigo-700',
    'tanpa-dana' => 'bg-emerald-50 border-emerald-200 text-emerald-600',
    'kosong' => 'bg-white border-slate-200 text-slate-400',
  ];
  $statusTeks = [
    'belum-pajak' => '⚠️ Belum Lapor Pajak',
    'tertutup' => '🔒 Sudah Selesai',
    'selesai' => 'Sudah Selesai',
    'draf' => 'Draf BKU',
    'tanpa-dana' => 'Tanpa Dana (Otomatis Selesai)',
    'kosong' => 'Belum Aktif',
  ];
@endphp
<div class="flex flex-wrap justify-between items-center gap-2">
  <form class="flex gap-2 items-center text-sm">
    <span class="text-slate-500">Bulan:</span>
    <input type="month" name="bulan" value="{{ $bulan }}" class="inp !w-auto" data-kirim-otomatis>
  </form>
  <div class="flex flex-wrap gap-2 items-center">
    @unless ($terkunciBulanIni)
    <details class="relative">
      <summary class="btn bg-slate-800 cursor-pointer list-none">🔒 Tutup BKU Bulan Ini</summary>
      <form method="post" action="{{ route('bku.tutup') }}"
            class="absolute right-0 mt-2 w-80 bg-white border border-slate-200 rounded-xl shadow-xl p-3.5 z-40 space-y-2.5 text-xs"
            data-konfirmasi="Tutup BKU {{ $tglBulan->translatedFormat('F Y') }} secara berurutan sesuai ARKAS 4?">
        @csrf
        <input type="hidden" name="bulan" value="{{ $bulan }}">
        <div class="font-bold text-slate-800 text-sm">🔒 Tutup BKU {{ $tglBulan->translatedFormat('F Y') }}</div>
        <p class="text-slate-500 leading-snug">Sesuai ARKAS 4 Bab XVII, BKU ditutup berurutan dari bulan penerimaan dana pertama. Masukkan Bunga Bank &amp; Pajak/Admin Bank akhir bulan (isi 0 bila nihil):</p>
        <div>
          <label class="font-semibold text-slate-600">Bunga Bank / Jasa Giro (Rp)</label>
          <input type="number" min="0" value="0" name="bunga_bank" class="inp mt-1">
        </div>
        <div>
          <label class="font-semibold text-slate-600">Pajak Bunga / Biaya Admin Bank (Rp)</label>
          <input type="number" min="0" value="0" name="pajak_bunga" class="inp mt-1">
        </div>
        <button class="btn w-full justify-center">Simpan &amp; Tutup BKU</button>
      </form>
    </details>
    @endunless
    <a href="{{ route('bku.csv') }}?bulan={{ $bulan }}" class="btn2 btn">⬇ Ekspor CSV</a>
    <a href="{{ route('bku.cetak') }}?bulan={{ $bulan }}" data-cetak="Cetak BKU" class="btn2 btn">🖨️ Cetak BKU</a>
    @php $semSekarang = (int) $tglBulan->format('n') <= 6 ? 1 : 2 @endphp
    <a href="{{ route('pembukuan.bukuPembantu', ['semester' => $semSekarang]) }}" data-cetak="Buku Pembantu" class="btn2 btn">📚 Buku Pembantu (Sem {{ $semSekarang }})</a>
    <a href="{{ route('pembukuan.rekapRealisasi', ['semester' => $semSekarang]) }}" data-cetak="Rekap Realisasi" class="btn2 btn">📊 Rekap Realisasi (Sem {{ $semSekarang }})</a>
    <a href="{{ route('pembukuan.sptjm', ['semester' => $semSekarang]) }}" data-cetak="SPTJM" class="btn2 btn">📄 SPTJM (Sem {{ $semSekarang }})</a>
    <a href="{{ route('laporan-arkas.kas-umum', ['bulan' => (int) $tglBulan->format('n')]) }}" target="_blank" class="btn2 btn">🔎 Detail Nota: Daftar Kas Umum</a>
  </div>
</div>

@if ($terkunciBulanIni)
<div class="card !border-2 !border-rose-300 bg-rose-50 flex flex-wrap items-center justify-between gap-2">
  <div>
    <div class="font-bold text-rose-800">🔒 BKU {{ $tglBulan->translatedFormat('F Y') }} — TERTUTUP</div>
    <div class="text-xs text-rose-700">
      Semua pencatatan, pengubahan, dan penghapusan transaksi bulan ini terkunci — sama seperti BKU bulanan ARKAS 4.
      @if(!empty($infoTutup) && ($infoTutup->bunga_bank > 0 || $infoTutup->pajak_bunga > 0))
        (Bunga Bank: <b>{{ rp($infoTutup->bunga_bank) }}</b> · Pajak/Admin Bank: <b>{{ rp($infoTutup->pajak_bunga) }}</b>)
      @endif
    </div>
  </div>
  @if ($bisaBuka)
  <form method="post" action="{{ route('bku.buka') }}">
    @csrf <input type="hidden" name="bulan" value="{{ $bulan }}">
    <button class="btn bg-slate-600 !bg-slate-600">🔓 Buka Kembali</button>
  </form>
  @endif
</div>
@endif

{{-- Panel kontrol dana — mengikuti panel BKU di ARKAS 4 --}}
<div class="card">
  <div class="flex items-center justify-between mb-3 flex-wrap gap-1">
    <div class="font-semibold text-slate-700">📊 Kontrol Dana Tahun {{ $tahun }}</div>
    <div class="text-xs text-slate-500">Sebagaimana panel BKU di ARKAS 4</div>
  </div>
  <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 text-center">
    <div class="rounded-lg bg-amber-50 border border-amber-200 p-2">
      <div class="text-[11px] text-amber-700">🪙 Saldo Tunai</div>
      <div class="font-bold text-sm">{{ rp($saldoSekarang['tunai']) }}</div>
    </div>
    <div class="rounded-lg bg-blue-50 border border-blue-200 p-2">
      <div class="text-[11px] text-blue-700">🏦 Saldo Bank</div>
      <div class="font-bold text-sm">{{ rp($saldoSekarang['bank']) }}</div>
    </div>
    <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-2">
      <div class="text-[11px] text-emerald-700">💵 Dana Tersedia</div>
      <div class="font-bold text-sm">{{ rp($danaTersedia) }}</div>
      <div class="text-[10px] text-emerald-600">SiLPA {{ rp($silpaTahunLalu) }} + salur baru {{ rp($pendapatanTh) }}</div>
    </div>
    <div class="rounded-lg bg-rose-50 border border-rose-200 p-2">
      <div class="text-[11px] text-rose-700">🛒 Sudah Dibelanjakan</div>
      <div class="font-bold text-sm">{{ rp($belanjaTh) }}</div>
    </div>
    <div class="rounded-lg bg-indigo-50 border border-indigo-200 p-2">
      <div class="text-[11px] text-indigo-700">♻️ Bisa Dianggarkan Ulang</div>
      <div class="font-bold text-sm">{{ rp($bisaDianggarkanUlang) }}</div>
      <div class="text-[10px] text-indigo-600">sisa pagu {{ rp($pagu) }}</div>
    </div>
    <div class="rounded-lg bg-slate-50 border border-slate-200 p-2 col-span-2 lg:col-span-1">
      <div class="text-[11px] text-slate-600">Total Kas Sekarang</div>
      <div class="font-bold text-sm">{{ rp($saldoSekarang['total']) }}</div>
    </div>
  </div>
  <div class="mt-3">
    <div class="flex justify-between text-[11px] text-slate-500 mb-1">
      <span>Serapan anggaran (pagu vs belanja)</span><span>{{ $serapan }}%</span>
    </div>
    <div class="h-2 rounded-full bg-slate-200 overflow-hidden">
      <div class="h-full bg-gradient-to-r from-indigo-500 to-emerald-500" style="width: {{ $serapan }}%"></div>
    </div>
  </div>
</div>

{{-- Grid 12 bulan ala ARKAS: status BKU tiap bulan --}}
<div class="card">
  <div class="font-semibold text-slate-700 mb-2 text-sm">🗓️ Status BKU per Bulan {{ $tahun }} <span class="text-xs text-slate-400 font-normal">— wajib ditutup berurutan dari bulan penerimaan dana pertama</span></div>
  <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-2">
    @foreach ($bulanGrid as $b)
    <a href="{{ route('bku', ['bulan' => $b['ym']]) }}"
       class="border-2 rounded-lg p-2 text-center block hover:shadow transition {{ $statusWarna[$b['status']] ?? 'bg-white' }} @if($b['now']) ring-2 ring-indigo-400 @endif">
      <div class="text-xs font-semibold">{{ $b['nama'] }}{{ $b['now'] ? ' • bulan ini' : '' }}</div>
      <div class="text-[10px] mt-0.5">{{ $statusTeks[$b['status']] ?? $b['status'] }}</div>
      @if ($b['n'])
      <div class="text-[10px] mt-0.5 opacity-80">↑{{ rp($b['masuk']) }} ↓{{ rp($b['keluar']) }}</div>
      @endif
    </a>
    @endforeach
  </div>
</div>

<div class="card overflow-x-auto">
  <div class="text-center mb-4">
    <div class="font-bold">Buku Kas Umum</div>
    <div class="text-sm">{{ auth()->user()->school->nama }} — {{ \Carbon\Carbon::parse($bulan)->translatedFormat('F Y') }}</div>
  </div>
  <table class="tbl">
    <thead><tr><th>Tanggal</th><th>Kegiatan</th><th>Rekening</th><th>No. Bukti</th><th>Uraian</th><th class="num">Penerimaan</th><th class="num">Pengeluaran</th><th class="num">Saldo</th></tr></thead>
    <tbody>
      <tr class="font-semibold bg-slate-50"><td colspan="6">Saldo Awal Bulan</td><td class="num"></td><td class="num">{{ rp($saldoAwal) }}</td></tr>
      @php $saldo = $saldoAwal; $tm=0; $tk=0; @endphp
      @foreach($rows as $row)
      @php
        $saldo += $row['masuk'] - $row['keluar']; $tm += $row['masuk']; $tk += $row['keluar']; $t=$row['trx'];
        $keg = ($t->details->first(fn($d) => $d->kode_kegiatan) ?? $t->details->first())?->kode_kegiatan ?? '';
        $kegLabel = $keg !== '' ? trim($keg . ' ' . ($kodeNama[$keg] ?? '')) : '—';
        $rek = $t->details->map(fn($d) => $d->rekening->kode ?? '')->filter()->unique()->implode(', ');
      @endphp
      <tr>
        <td class="whitespace-nowrap">{{ $t->tanggal->format('d-m-Y') }}</td>
        <td class="text-xs">{{ $kegLabel }}</td>
        <td class="font-mono text-xs whitespace-nowrap">{{ $rek ?: '—' }}</td>
        <td class="whitespace-nowrap">{{ $t->arkas_bku ?: ($t->no_bku ? 'BKU#'.$t->no_bku : '—') }}</td>
        <td>{{ $t->keterangan }}</td>
        <td class="num text-emerald-700">{{ $row['masuk'] ? rp($row['masuk']) : '' }}</td>
        <td class="num text-rose-700">{{ $row['keluar'] ? rp($row['keluar']) : '' }}</td>
        <td class="num font-semibold">{{ rp($saldo) }}</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr class="font-bold"><td colspan="5" class="text-right">Jumlah</td><td class="num">{{ rp($tm) }}</td><td class="num">{{ rp($tk) }}</td><td class="num"></td></tr>
      <tr class="font-bold bg-slate-50"><td colspan="6" class="text-right">Saldo Buku Kas Umum</td><td class="num"></td><td class="num">{{ rp($saldo) }}</td></tr>
    </tfoot>
  </table>
</div>
@endsection
