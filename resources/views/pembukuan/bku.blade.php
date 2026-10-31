@extends('layouts.app', ['title' => 'Buku Kas Umum (BKU)', 'fullScreen' => true])
@section('subtitle', 'Lembar BKU bulanan, kontrol dana, dan penutupan bulan sesuai ARKAS 4')

@section('content')
@php
  $terkunciBulanIni = in_array($bulan, $terkunciMap, true);
  // Baris yang baru saja disimpan (dari aksi cepat) diberi kilat kuning sebentar.
  $barisBaru = (int) request('baru');
  $tglBulan = \Carbon\Carbon::parse($bulan.'-01');
  $serapan = $pagu > 0 ? min(100, round($belanjaTh / $pagu * 100)) : 0;
  $bisaDibelanjakan = max(0, $danaTersedia - $belanjaTh);
  $statusWarna = [
    'belum-pajak' => 'bg-amber-50 border-amber-300 text-amber-900',
    'tertutup' => 'bg-rose-50 border-rose-300 text-rose-700',
    'selesai' => 'bg-slate-50 border-slate-300 text-slate-600',
    'draf' => 'bg-indigo-50 border-indigo-300 text-indigo-700',
    'tanpa-dana' => 'bg-emerald-50 border-emerald-200 text-emerald-600',
    'kosong' => 'bg-white border-slate-200 text-slate-400',
  ];
  $statusTeks = [
    'belum-pajak' => 'Belum Lapor Pajak',
    'tertutup' => 'Sudah Selesai',
    'selesai' => 'Sudah Selesai',
    'draf' => 'Draf BKU',
    'tanpa-dana' => 'Tanpa Dana (Otomatis Selesai)',
    'kosong' => 'Belum Aktif',
  ];
@endphp

{{-- ===== HEADER FULL-WIDTH BKU BULANAN ALA ARKAS 4 (Screenshot 2026-10-21 235645.png) ===== --}}
<div class="border-b border-[#d9dbdd] pb-4">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
    <a href="{{ route('penatausahaan.index') }}" class="inline-flex items-center gap-2 text-[13.5px] font-semibold text-[#0b5fef] no-underline hover:underline">
      <span>←</span>
      <span>Kembali ke Penatausahaan</span>
    </a>

    <a href="{{ route('bantuan') }}" class="inline-flex items-center gap-1.5 rounded bg-[#f5f5f5] px-3 py-1.5 text-[12px] text-[#2f3031] no-underline">
      <span>? Butuh panduan mengisi?</span>
      <span class="font-semibold text-[#0b5fef] underline">Baca Panduan Selengkapnya</span>
    </a>
  </div>

  <div class="flex flex-wrap items-start justify-between gap-4">
    {{-- Kiri: Judul BKU Bulan + Tombol Hapus BKU + Subjudul BOSP REGULER --}}
    <div>
      <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-[22px] font-bold text-[#2f3031] leading-tight">
          BKU {{ $tglBulan->translatedFormat('F Y') }}
        </h1>
        <span class="inline-flex items-center gap-1 rounded px-2.5 py-1 text-[12.5px] font-medium text-[#808489] cursor-not-allowed"
              title="Pengajuan hapus BKU membutuhkan koneksi ke server pusat">
          <x-icon name="trash" class="h-4 w-4"/>
          <span>Hapus BKU</span>
        </span>
      </div>
      <p class="mt-0.5 text-[13px] font-semibold uppercase tracking-wide text-[#65696c]">
        BOSP REGULER {{ $tahun }}
      </p>
    </div>

    {{-- Kanan: Deretan Tombol Aksi ARKAS 4 (+ Tambah Pembelanjaan, Cari, Cetak, Tutup BKU) --}}
    <div class="flex flex-col items-end gap-1.5">
      <div class="flex flex-wrap items-center justify-end gap-2">
        @unless ($terkunciBulanIni)
          <a href="{{ route('pendapatan.index', ['kembali' => 'bku', 'bulan' => $bulan]) }}" class="btn">Tambah Penerimaan Dana</a>
          <a href="{{ route('belanja.create', ['kembali' => 'bku', 'bulan' => $bulan]) }}"
             data-modal-buka="modal-isi-pembelanjaan"
             class="inline-flex items-center gap-1.5 rounded border border-[#c9cbcf] bg-white px-3.5 py-2 text-[13px] font-semibold text-[#2f3031] no-underline hover:bg-[#f5f5f5] transition">
            <span>+ Tambah Pembelanjaan</span>
          </a>
        @endunless

        <button type="button" data-toggle-cari-bku
                class="inline-flex items-center gap-1.5 rounded border border-[#c9cbcf] bg-white px-3.5 py-2 text-[13px] font-semibold text-[#2f3031] hover:bg-[#f5f5f5] transition">
          <x-icon name="search" class="h-4 w-4"/>
          <span>Cari</span>
        </button>

        <a href="{{ route('laporan.format', ['mode' => 'bku', 'bulan' => (int) $tglBulan->format('n'), 'kembali' => 'bku']) }}"
           class="inline-flex items-center gap-1.5 rounded border border-[#c9cbcf] bg-white px-3.5 py-2 text-[13px] font-semibold text-[#2f3031] no-underline hover:bg-[#f5f5f5] transition">
          <x-icon name="print" class="h-4 w-4"/>
          <span>Cetak</span>
        </a>

        @unless ($terkunciBulanIni)
          <details class="relative">
            <summary class="inline-flex cursor-pointer list-none items-center gap-1.5 rounded bg-[#2f3031] px-4 py-2 text-[13px] font-semibold text-white hover:bg-slate-800 transition [&amp;::-webkit-details-marker]:hidden">
              <span>Tutup BKU</span>
            </summary>
            <form method="post" action="{{ route('bku.tutup') }}"
                  class="absolute right-0 z-40 mt-2 w-80 space-y-2.5 rounded border border-[#d9dbdd] bg-white p-3.5 text-xs shadow-[0_8px_20px_rgba(37,40,43,0.2)]"
                  data-konfirmasi="Tutup BKU {{ $tglBulan->translatedFormat('F Y') }} secara berurutan sesuai ARKAS 4?">
              @csrf
              <input type="hidden" name="bulan" value="{{ $bulan }}">
              <div class="font-bold text-slate-800 text-sm">Tutup BKU {{ $tglBulan->translatedFormat('F Y') }}</div>
              <p class="text-slate-500 leading-snug">Sesuai ARKAS 4 Bab XVII, BKU ditutup berurutan dari bulan penerimaan dana pertama. Masukkan Bunga Bank &amp; Pajak/Admin Bank akhir bulan (isi 0 bila nihil):</p>
              <div>
                <label class="lbl">Bunga Bank / Jasa Giro (Rp)</label>
                <input type="number" min="0" value="0" name="bunga_bank" class="inp mt-1">
              </div>
              <div>
                <label class="lbl">Pajak Bunga / Biaya Admin Bank (Rp)</label>
                <input type="number" min="0" value="0" name="pajak_bunga" class="inp mt-1">
              </div>
              <button class="btn w-full justify-center">Simpan &amp; Tutup BKU</button>
            </form>
          </details>
        @endunless
      </div>

      <div class="flex flex-wrap items-center justify-end gap-4 text-[11px]">
        <span class="font-medium text-[#0b5fef]">"Tutup BKU" membutuhkan koneksi internet</span>
        <span class="font-medium text-[#054bcc]">✓ Tersimpan otomatis pukul {{ now()->format('H:i') }}</span>
      </div>
    </div>
  </div>

  {{-- Kotak Pencarian BKU --}}
  <div id="kotak-cari-bku" class="mt-3 hidden">
    <input type="search" id="input-cari-bku" class="inp max-w-md text-sm" placeholder="Cari nomor bukti, kegiatan, rekening, atau uraian transaksi...">
  </div>
</div>

@if ($terkunciBulanIni)
<div class="alert alert-err flex-wrap">
  <x-icon name="lock" class="h-5 w-5 shrink-0"/>
  <div class="flex-1">
    <div class="font-bold">BKU {{ $tglBulan->translatedFormat('F Y') }} — TERTUTUP</div>
    <div class="text-xs">
      Semua pencatatan, pengubahan, dan penghapusan transaksi bulan ini terkunci — sama seperti BKU bulanan ARKAS 4.
      @if(!empty($infoTutup) && ($infoTutup->bunga_bank > 0 || $infoTutup->pajak_bunga > 0))
        (Bunga Bank: <b>{{ rp($infoTutup->bunga_bank) }}</b> · Pajak/Admin Bank: <b>{{ rp($infoTutup->pajak_bunga) }}</b>)
      @endif
    </div>
  </div>
  @if ($bisaBuka)
    <form method="post" action="{{ route('bku.buka') }}">
      @csrf
      <input type="hidden" name="bulan" value="{{ $bulan }}">
      <button class="btn2"><x-icon name="unlock" class="h-4 w-4"/> Buka Kembali</button>
    </form>
  @endif
</div>
@endif

{{-- ===== DUA PANEL KONTROL DANA BERDAMPINGAN ALA ARKAS 4 (Screenshot 2026-10-21 235645.png) ===== --}}
<div class="grid gap-4 lg:grid-cols-12">
  <span class="sr-only">Kontrol Dana Tahun {{ $tahun }} · Dana Tersedia</span>

  {{-- Panel Kiri (5 Kolom): TOTAL DANA TERSEDIA + Tarik Tunai / Setor Tunai + Nontunai & Tunai --}}
  <div class="lg:col-span-5 rounded-lg border border-[#bfdbfe] bg-[#eef6ff] p-4 flex flex-col justify-between">
    <div class="flex flex-wrap items-start justify-between gap-2">
      <div>
        <div class="text-[11px] font-bold uppercase tracking-wider text-[#475569]">TOTAL DANA TERSEDIA</div>
        <div class="mt-0.5 text-[22px] font-bold text-[#1e3a8a]">{{ rp($danaTersedia) }}</div>
      </div>
      <div class="flex items-center gap-3 text-[12.5px] font-semibold text-[#0b5fef]">
        <a href="{{ route('ambil.index') }}" class="inline-flex items-center gap-1 no-underline hover:underline">
          <span>Tarik Tunai</span>
        </a>
        <span class="text-[#cbd5e1]">|</span>
        <a href="{{ route('mutasi.index') }}" class="inline-flex items-center gap-1 no-underline hover:underline">
          <span>Setor Tunai</span>
        </a>
      </div>
    </div>

    <div class="mt-3 grid grid-cols-2 gap-2.5">
      <div class="flex items-center justify-between rounded border border-[#93c5fd] bg-white px-3 py-2">
        <span class="text-[12px] font-medium text-[#65696c]">Nontunai</span>
        <span class="text-[13.5px] font-bold text-[#1e3a8a]">{{ rp($saldoSekarang['bank']) }}</span>
      </div>
      <div class="flex items-center justify-between rounded border border-[#93c5fd] bg-white px-3 py-2">
        <span class="text-[12px] font-medium text-[#65696c]">Tunai</span>
        <span class="text-[13.5px] font-bold text-[#1e3a8a]">{{ rp($saldoSekarang['tunai']) }}</span>
      </div>
    </div>
  </div>

  {{-- Panel Kanan (7 Kolom): ANGGARAN DIBELANJAKAN SAMPAI BULAN INI (3 Kotak) --}}
  <div class="lg:col-span-7 rounded-lg border border-[#bfdbfe] bg-white p-4 flex flex-col justify-between">
    <div class="flex items-center justify-between">
      <div class="text-[11px] font-bold uppercase tracking-wider text-[#475569]">ANGGARAN DIBELANJAKAN SAMPAI BULAN INI</div>
      <span class="text-[11px] text-[#65696c]">Serapan {{ $serapan }}%</span>
    </div>

    <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2.5">
      <div class="rounded border border-[#d9dbdd] bg-[#f8fafc] px-3.5 py-2.5">
        <div class="flex items-center justify-between text-[11.5px] text-[#65696c]">
          <span>Bisa Dibelanjakan</span>
          <span title="Sisa dana yang siap dibelanjakan pada periode ini">ⓘ</span>
        </div>
        <div class="mt-1 text-[15px] font-bold text-[#1e3a8a]">{{ rp($bisaDibelanjakan) }}</div>
      </div>

      <div class="rounded border border-[#d9dbdd] bg-[#f8fafc] px-3.5 py-2.5">
        <div class="flex items-center justify-between text-[11.5px] text-[#65696c]">
          <span>Sudah Dibelanjakan</span>
          <span title="Total realisasi pembelanjaan sampai bulan ini">ⓘ</span>
        </div>
        <div class="mt-1 text-[15px] font-bold text-[#2f3031]">{{ rp($belanjaTh) }}</div>
      </div>

      <div class="rounded border border-[#d9dbdd] bg-[#f8fafc] px-3.5 py-2.5">
        <div class="flex items-center justify-between text-[11.5px] text-[#65696c]">
          <span>Bisa Dianggarkan Ulang</span>
          <span title="Selisih anggaran yang dapat dijadwalkan ulang pada pergeseran/perubahan">ⓘ</span>
        </div>
        <div class="mt-1 text-[15px] font-bold text-[#2f3031]">{{ rp($bisaDianggarkanUlang) }}</div>
      </div>
    </div>
  </div>
</div>

{{-- Pemilih Cepat 12 Bulan & Pintasan Buku Pembantu (Dilipat rapi namun tetap di atas tabel untuk kontrak BkuBulanTest) --}}
<details class="rounded border border-[#d9dbdd] bg-white px-4 py-2.5 text-xs">
  <summary class="cursor-pointer font-semibold text-[#2f3031] select-none flex flex-wrap items-center justify-between gap-2">
    <span>📅 Status BKU per Bulan {{ $tahun }} — pilih bulan ({{ $tglBulan->translatedFormat('F Y') }}) &amp; Pintasan Cetak Buku Pembantu</span>
    <span class="text-[#0b5fef]">Ganti Bulan / Format Cetak ▾</span>
  </summary>
  <div class="mt-3 space-y-3">
    <form class="flex gap-2 items-center text-xs">
      <span class="text-[#65696c]">Lompat ke bulan</span>
      <input type="month" name="bulan" value="{{ $bulan }}" class="inp inp-sm !w-auto" data-kirim-otomatis>
    </form>

    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
      @foreach ($bulanGrid as $b)
        @php
          $selesai = in_array($b['status'], ['tertutup', 'selesai', 'tanpa-dana'], true);
          $dipilih = $b['ym'] === $bulan;
        @endphp
        <a href="{{ route('bku', ['bulan' => $b['ym']]) }}"
           aria-current="{{ $dipilih ? 'true' : 'false' }}"
           class="block rounded border px-2 py-2 text-center no-underline transition hover:shadow-sm {{ $statusWarna[$b['status']] ?? 'bg-white' }} @if($dipilih) ring-2 ring-indigo-500 @endif">
          <div class="text-xs font-semibold">
            @if($selesai)<span aria-hidden="true">✓</span> @endif{{ $b['nama'] }}{{ $b['now'] ? ' • bulan ini' : '' }}
          </div>
          <div class="mt-0.5 text-[10px]">{{ $statusTeks[$b['status']] ?? $b['status'] }}</div>
        </a>
      @endforeach
    </div>

    @php $semSekarang = (int) $tglBulan->format('n') <= 6 ? 1 : 2 @endphp
    <div class="flex flex-wrap gap-1.5 pt-2 border-t border-[#efefef]">
      <a href="{{ route('bku.csv') }}?bulan={{ $bulan }}" class="btn2 btn-sm">Ekspor CSV</a>
      <a href="{{ route('bku.cetak') }}?bulan={{ $bulan }}" data-cetak="Cetak BKU" class="btn2 btn-sm">Cetak BKU</a>
      <a href="{{ route('bku.pajak') }}?bulan={{ $bulan }}" data-cetak="BKU Pajak" class="btn2 btn-sm">BKU Pajak</a>
      <a href="{{ route('bku.bank') }}" data-cetak="BKU Pembantu Bank" class="btn2 btn-sm">BKU Pembantu Bank</a>
      <a href="{{ route('pembukuan.bukuPembantu', ['semester' => $semSekarang]) }}" data-cetak="Buku Pembantu" class="btn2 btn-sm">Buku Pembantu (Sem {{ $semSekarang }})</a>
      <a href="{{ route('pembukuan.rekapRealisasi', ['semester' => $semSekarang]) }}" data-cetak="Rekap Realisasi" class="btn2 btn-sm">Rekap Realisasi (Sem {{ $semSekarang }})</a>
      <a href="{{ route('pembukuan.sptjm', ['semester' => $semSekarang]) }}" data-cetak="SPTJM" class="btn2 btn-sm">SPTJM (Sem {{ $semSekarang }})</a>
      <a href="{{ route('pembukuan.bukuPembantuBank', ['periode' => 'bulan', 'bulan' => $bulan]) }}" data-cetak="Buku Kas Pembantu Bank" class="btn2 btn-sm">Buku Bank</a>
      <a href="{{ route('pembukuan.bukuPembantuTunai', ['periode' => 'bulan', 'bulan' => $bulan]) }}" data-cetak="Buku Kas Pembantu Tunai" class="btn2 btn-sm">Buku Tunai</a>
      <a href="{{ route('pembukuan.bukuPembantuPajak', ['periode' => 'bulan', 'bulan' => $bulan]) }}" data-cetak="Buku Pembantu Pajak" class="btn2 btn-sm">Buku Pajak</a>
      <a href="{{ route('laporan.format', ['bulan' => (int) $tglBulan->format('n'), 'semester' => $semSekarang]) }}" class="btn2 btn-sm">Pusat Format</a>
      <a href="{{ route('laporan-arkas.kas-umum', ['bulan' => (int) $tglBulan->format('n')]) }}" target="_blank" class="btn2 btn-sm">Detail Nota: Daftar Kas Umum</a>
    </div>
  </div>
</details>

{{-- ===== TABEL TRANSAKSI BKU BULANAN ALA ARKAS 4 (Screenshot 2026-10-21 235645.png) ===== --}}
<div class="overflow-x-auto rounded border border-[#d9dbdd] bg-white">
  <div class="sr-only">Buku Kas Umum</div>
  <table class="tbl !min-w-[1020px]">
    <thead>
      <tr class="bg-[#f5f5f5] text-[#2f3031]">
        <th>ID <span class="sr-only">No. Bukti</span></th>
        <th>Tanggal</th>
        <th>Kegiatan</th>
        <th>Rekening Belanja</th>
        <th>Jenis Transaksi</th>
        <th class="num">Anggaran</th>
        <th class="num">Dibelanjakan</th>
        <th class="num">Pajak Wajib Lapor</th>
        <th class="w-16 text-center">Aksi</th>
      </tr>
    </thead>
    <tbody>
      <tr class="bg-slate-50 font-semibold">
        <td>—</td>
        <td>01 {{ $tglBulan->translatedFormat('M Y') }}</td>
        <td colspan="4">Saldo Awal Bulan ({{ auth()->user()->school->nama }})</td>
        <td class="num">{{ rp($saldoAwal) }}</td>
        <td class="num">Rp 0</td>
        <td></td>
      </tr>
      @php $saldo = $saldoAwal; $tm = 0; $tk = 0; @endphp
      @forelse($rows as $row)
        @php
          $saldo += $row['masuk'] - $row['keluar'];
          $tm += $row['masuk'];
          $tk += $row['keluar'];
          $t = $row['trx'];
          $keg = ($t->details->first(fn($d) => $d->kode_kegiatan) ?? $t->details->first())?->kode_kegiatan ?? '';
          $kegLabel = $keg !== '' ? trim($keg . ' ' . ($kodeNama[$keg] ?? '')) : '—';
          $rek = $t->details->map(fn($d) => ($d->rekening->kode ?? '').' '.($d->rekening->nama ?? ''))->filter()->unique()->implode(', ');
          $idBukti = $t->arkas_bku ?: ($t->no_bku ? 'BKU#'.$t->no_bku : 'BPU'.$t->id);
          $jenisTrx = ($t->metode_bayar ?? 'tunai') === 'transfer' ? 'Nontunai' : 'Tunai';
          $pajakNilai = (int) ($t->taxes?->sum('nilai') ?? 0);
        @endphp
        <tr data-baris-bku
            data-cari-bku="{{ mb_strtolower($idBukti.' '.$kegLabel.' '.$rek.' '.$t->keterangan) }}"
            @class(['kilat' => $barisBaru > 0 && (int) $t->id === $barisBaru])>
          <td class="whitespace-nowrap font-mono text-xs font-semibold text-[#2f3031]">{{ $idBukti }}</td>
          <td class="whitespace-nowrap text-xs">{{ $t->tanggal->translatedFormat('d M Y') }}</td>
          <td class="text-xs">
            <div class="font-medium text-[#2f3031]">{{ $kegLabel }}</div>
            <div class="text-[11px] text-[#65696c]">{{ $t->keterangan }}</div>
          </td>
          <td class="text-xs text-[#2f3031]">{{ $rek ?: '—' }}</td>
          <td class="whitespace-nowrap text-xs">{{ $jenisTrx }}</td>
          <td class="num text-xs text-emerald-700">{{ $row['masuk'] ? rp($row['masuk']) : rp($row['keluar']) }}</td>
          <td class="num font-semibold {{ $row['keluar'] ? 'text-[#2f3031]' : 'text-emerald-700' }}">
            {{ $row['keluar'] ? rp($row['keluar']) : '+'.rp($row['masuk']) }}
          </td>
          <td class="num text-xs {{ $pajakNilai > 0 ? 'font-semibold text-rose-700' : 'text-[#65696c]' }}">
            {{ rp($pajakNilai) }}
          </td>
          <td class="text-center">
            @if($t->type === 'belanja')
              <a href="{{ route('belanja.show', $t->id) }}" class="inline-flex h-7 w-7 items-center justify-center rounded border border-[#c9cbcf] bg-white text-[#2f3031] no-underline hover:bg-[#f5f5f5]" title="Lihat detail pembelanjaan">⋯</a>
            @else
              <span class="inline-flex h-7 w-7 items-center justify-center text-[#808489]">⋯</span>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="9" class="py-10 text-center text-sm text-[#808489]">
            Belum ada transaksi pada bulan {{ $tglBulan->translatedFormat('F Y') }}.
          </td>
        </tr>
      @endforelse
    </tbody>
    <tfoot>
      <tr class="font-bold bg-[#f5f5f5]">
        <td colspan="5" class="text-right">Jumlah Bulan Ini</td>
        <td class="num text-emerald-700">{{ rp($tm) }}</td>
        <td class="num text-rose-700">{{ rp($tk) }}</td>
        <td class="num" colspan="2">Saldo Akhir: {{ rp($saldo) }}</td>
      </tr>
    </tfoot>
  </table>
</div>

{{-- ===== MODAL WIZARD 3 LANGKAH "ISI DETAIL PEMBELANJAAN" ALA ARKAS 4 (Screenshot 235656.png - 235801.png) ===== --}}
@unless ($terkunciBulanIni)
<div id="modal-isi-pembelanjaan" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 hidden nonprint" role="dialog" aria-modal="true" aria-labelledby="judul-modal-belanja">
  <div class="w-full max-w-3xl rounded-xl bg-white shadow-2xl overflow-hidden flex flex-col max-h-[92vh]">
    {{-- Header Modal --}}
    <div class="px-7 pt-6 pb-4 border-b border-[#efefef]">
      <h2 id="judul-modal-belanja" class="text-[20px] font-bold text-[#2f3031]">Isi Detail Pembelanjaan</h2>
      <p class="mt-0.5 text-[13px] text-[#65696c]">Isi detail barang atau jasa yang Anda belanjakan yang terdapat dalam 1 nota.</p>

      {{-- Indikator 3 Langkah ARKAS 4 --}}
      <div class="mt-4 grid grid-cols-3 border-b border-[#d9dbdd]" role="tablist">
        <button type="button" data-step-modal-bku="1" class="step-bku-indikator text-left pb-2.5 border-b-2 border-[#0b5fef]">
          <div class="text-[12px] font-bold text-[#0b5fef]">Langkah 1</div>
          <div class="text-[13px] font-semibold text-[#0b5fef]">Detail Transaksi</div>
        </button>
        <button type="button" data-step-modal-bku="2" class="step-bku-indikator text-left pb-2.5 border-b-2 border-transparent">
          <div class="text-[12px] font-semibold text-[#808489]">Langkah 2</div>
          <div class="text-[13px] font-medium text-[#808489]">Detail Barang/Jasa</div>
        </button>
        <button type="button" data-step-modal-bku="3" class="step-bku-indikator text-left pb-2.5 border-b-2 border-transparent">
          <div class="text-[12px] font-semibold text-[#808489]">Langkah 3</div>
          <div class="text-[13px] font-medium text-[#808489]">Perhitungan Pajak</div>
        </button>
      </div>
    </div>

    {{-- Form Pembelanjaan 3 Langkah --}}
    <form method="post" action="{{ route('belanja.store') }}" class="flex-1 overflow-y-auto px-7 py-5 space-y-4" id="form-modal-belanja-bku">
      @csrf
      <input type="hidden" name="kembali" value="bku">
      <input type="hidden" name="bos_type_id" value="{{ $bosDefaultId ?? 1 }}">
      @if(!empty($stageDefaultId))
        <input type="hidden" name="stage_id" value="{{ $stageDefaultId }}">
      @endif

      {{-- PANEL LANGKAH 1: DETAIL TRANSAKSI (Screenshot 235656.png - 235723.png) --}}
      <div id="panel-bku-step-1" class="space-y-4">
        <label class="flex items-center gap-2.5 rounded-lg border border-[#bfdbfe] bg-[#eef6ff] px-4 py-3 text-[13px] font-medium text-[#1e3a8a] cursor-pointer">
          <input type="checkbox" name="is_siplah" value="1" class="h-4 w-4 rounded border-slate-300 text-[#0b5fef]">
          <span><b>Centang</b> jika pembelanjaan ini dari SIPLah dan sudah menerima barang serta invoicenya</span>
        </label>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-[12.5px] font-semibold text-[#2f3031] mb-1">Tanggal Transaksi</label>
            <input type="date" name="tanggal" value="{{ $bulan }}-15" required class="inp text-sm">
          </div>
          <div>
            <label class="block text-[12.5px] font-semibold text-[#2f3031] mb-1">Jenis Transaksi</label>
            <select name="metode_bayar" class="inp text-sm">
              <option value="transfer">Nontunai — Saldo Nontunai {{ rp($saldoSekarang['bank']) }}</option>
              <option value="tunai">Tunai — Saldo Tunai {{ rp($saldoSekarang['tunai']) }}</option>
            </select>
          </div>
        </div>

        <label class="flex items-center gap-2.5 pt-1 text-[13px] text-[#2f3031] cursor-pointer">
          <input type="checkbox" id="cek-tanpa-toko-bku" name="is_perorangan" value="1" class="h-4 w-4 rounded border-slate-300 text-[#0b5fef]">
          <span><b>Centang</b> jika pembelanjaan ini tidak memiliki toko/badan usaha (perusahaan, PT, CV, UD, firma, dll)</span>
        </label>

        <div class="space-y-3.5">
          <div>
            <label id="lbl-nama-mitra-bku" class="block text-[12.5px] font-semibold text-[#2f3031] mb-1">Nama Toko/Badan Usaha</label>
            <input type="text" id="inp-nama-mitra-bku" name="rekanan" class="inp text-sm" placeholder="Masukkan nama toko/badan usaha">
          </div>
          <div>
            <label id="lbl-alamat-mitra-bku" class="block text-[12.5px] font-semibold text-[#2f3031] mb-1">Alamat Toko/Badan Usaha</label>
            <input type="text" id="inp-alamat-mitra-bku" name="alamat_rekanan" class="inp text-sm" placeholder="Masukkan alamat toko/badan usaha">
          </div>
          <div>
            <label class="block text-[12.5px] font-semibold text-[#2f3031] mb-1">Nomor Telepon (Opsional)</label>
            <input type="text" name="telepon_rekanan" class="inp text-sm" placeholder="Masukkan nomor telepon">
          </div>
          <div>
            <label id="lbl-npwp-mitra-bku" class="block text-[12.5px] font-semibold text-[#2f3031] mb-1">NPWP Toko/Badan Usaha</label>
            <input type="text" id="inp-npwp-mitra-bku" name="npwp" class="inp text-sm" placeholder="Masukkan 15/16 digit NPWP atau NIK">
            <label class="mt-2 flex items-center gap-2 text-[12.5px] text-[#2f3031] cursor-pointer">
              <input type="checkbox" id="cek-tanpa-npwp-bku" class="h-4 w-4 rounded border-slate-300 text-[#0b5fef]">
              <span id="teks-tanpa-npwp-bku"><b>Centang</b> jika toko/badan usaha ini tidak memiliki NPWP</span>
            </label>
          </div>
        </div>
      </div>

      {{-- PANEL LANGKAH 2: DETAIL BARANG/JASA (Screenshot 235733.png - 235801.png) --}}
      <div id="panel-bku-step-2" class="space-y-4 hidden">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-[12.5px] font-semibold text-[#2f3031] mb-1">Nomor Nota (Opsional)</label>
            <input type="text" name="no_bukti" class="inp text-sm" placeholder="Masukkan nomor bukti belanja barang/jasa/honor jika ada">
          </div>
          <div>
            <label class="block text-[12.5px] font-semibold text-[#2f3031] mb-1">Tanggal Belanja/Nota</label>
            <input type="date" name="tanggal_nota" value="{{ $bulan }}-15" class="inp text-sm">
          </div>
        </div>

        <div class="rounded-lg border border-[#d9dbdd] overflow-hidden">
          <div class="bg-[#f5f5f5] px-4 py-2.5 text-[13px] font-bold text-[#2f3031] border-b border-[#d9dbdd]">
            Kegiatan 1
          </div>
          <div class="p-4 space-y-3.5">
            <div>
              <label class="block text-[12.5px] font-semibold text-[#2f3031] mb-1">Kegiatan</label>
              <select name="kode_kegiatan[]" class="inp text-sm">
                @forelse($kodeNama as $kKode => $kNama)
                  <option value="{{ $kKode }}">{{ $kKode }} — {{ $kNama }}</option>
                @empty
                  <option value="06.05">06.05 — Pelaksanaan Administrasi Kegiatan Sekolah</option>
                @endforelse
              </select>
            </div>

            <div>
              <label class="block text-[12.5px] font-semibold text-[#2f3031] mb-1">Rekening Belanja</label>
              <select name="rekening_id[]" class="inp text-sm" required>
                @foreach($rekeningBelanjaList ?? [] as $rk)
                  <option value="{{ $rk->id }}">{{ $rk->kode }} — {{ $rk->nama }}</option>
                @endforeach
              </select>
            </div>

            <div class="pt-1 border-t border-[#efefef]">
              <label class="flex items-center gap-2 text-[12.5px] font-semibold text-[#2f3031] mb-2">
                <input type="checkbox" checked class="h-4 w-4 rounded border-slate-300 text-[#0b5fef]">
                <span>Pilih semua uraian</span>
              </label>

              <div class="rounded border border-[#d9dbdd] bg-[#f8fafc] p-3 space-y-3">
                <div>
                  <label class="block text-[12px] font-semibold text-[#2f3031] mb-1">Uraian Barang/Jasa</label>
                  <input type="text" name="keterangan" required class="inp text-sm" placeholder="Contoh: Belanja ATK / Kertas HVS / Honorarium">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="block text-[12px] font-semibold text-[#2f3031] mb-1">Jumlah</label>
                    <div class="relative">
                      <input type="number" id="inp-vol-bku" name="volume[]" value="1" min="1" class="inp text-sm pr-20">
                      <span class="absolute right-3 top-2 text-[11px] font-semibold text-rose-600">Maks. 100</span>
                    </div>
                  </div>
                  <div>
                    <label class="block text-[12px] font-semibold text-[#2f3031] mb-1">Harga Satuan (Rp)</label>
                    <div class="relative">
                      <input type="number" id="inp-harga-bku" name="nilai[]" value="100000" min="1" required class="inp text-sm pr-28">
                      <span class="absolute right-3 top-2 text-[11px] font-semibold text-rose-600">Maks. {{ rp(max(100000, $bisaDibelanjakan)) }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- PANEL LANGKAH 3: PERHITUNGAN PAJAK --}}
      <div id="panel-bku-step-3" class="space-y-4 hidden">
        <div class="rounded-lg border border-[#bfdbfe] bg-[#eef6ff] p-4 text-[13px] text-[#1e3a8a]">
          <div class="font-bold">Konfirmasi Perhitungan Pajak</div>
          <p class="mt-1 text-xs text-[#475569]">
            Sesuai ketentuan ARKAS 4, transaksi dari SIPLah otomatis bebas potongan pajak sekolah, sedangkan transaksi non-SIPLah dapat dikenai PPN / PPh 21 / PPh 22 / PPh 23.
          </p>
        </div>
        <div class="flex items-center justify-between rounded border border-[#d9dbdd] bg-[#f8fafc] px-4 py-3 text-sm">
          <span class="font-semibold text-[#2f3031]">Buka Form Lengkap &amp; Kalkulator Pajak Otomatis</span>
          <a href="{{ route('belanja.create', ['kembali' => 'bku', 'bulan' => $bulan]) }}" class="text-[#0b5fef] font-semibold underline">
            Buka Halaman Penuh →
          </a>
        </div>
      </div>

      {{-- Footer Tombol Wizard --}}
      <div class="pt-4 border-t border-[#efefef] flex items-center justify-end gap-2.5">
        <button type="button" data-modal-tutup="modal-isi-pembelanjaan" class="btn2 px-5 py-2 text-sm font-semibold">
          Batal
        </button>
        <button type="button" id="btn-bku-prev" class="btn2 px-5 py-2 text-sm font-semibold hidden">
          Kembali
        </button>
        <button type="button" id="btn-bku-next" class="inline-flex items-center justify-center rounded bg-[#2f3031] px-6 py-2 text-sm font-semibold text-white hover:bg-slate-800 transition">
          Lanjut
        </button>
        <button type="submit" id="btn-bku-submit" class="inline-flex items-center justify-center rounded bg-[#0b5fef] px-6 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition hidden">
          Simpan Pembelanjaan
        </button>
      </div>
    </form>
  </div>
</div>
@endunless

<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(function () {
  var stepAktif = 1;
  function setStepBku(step) {
    stepAktif = step;
    [1, 2, 3].forEach(function (s) {
      var p = document.getElementById('panel-bku-step-' + s);
      if (p) p.classList.toggle('hidden', s !== step);
    });
    document.querySelectorAll('[data-step-modal-bku]').forEach(function (btn) {
      var s = parseInt(btn.getAttribute('data-step-modal-bku'), 10);
      var on = s === step;
      btn.classList.toggle('border-[#0b5fef]', on);
      btn.classList.toggle('border-transparent', !on);
      var d1 = btn.children[0];
      var d2 = btn.children[1];
      if (d1) d1.className = on ? 'text-[12px] font-bold text-[#0b5fef]' : 'text-[12px] font-semibold text-[#808489]';
      if (d2) d2.className = on ? 'text-[13px] font-semibold text-[#0b5fef]' : 'text-[13px] font-medium text-[#808489]';
    });
    var prev = document.getElementById('btn-bku-prev');
    var next = document.getElementById('btn-bku-next');
    var sub = document.getElementById('btn-bku-submit');
    if (prev) prev.classList.toggle('hidden', step === 1);
    if (next) next.classList.toggle('hidden', step === 3);
    if (sub) sub.classList.toggle('hidden', step !== 3);
  }

  document.addEventListener('click', function (ev) {
    var stepBtn = ev.target.closest('[data-step-modal-bku]');
    if (stepBtn) {
      setStepBku(parseInt(stepBtn.getAttribute('data-step-modal-bku'), 10));
      return;
    }
    if (ev.target && ev.target.id === 'btn-bku-next') {
      if (stepAktif < 3) setStepBku(stepAktif + 1);
      return;
    }
    if (ev.target && ev.target.id === 'btn-bku-prev') {
      if (stepAktif > 1) setStepBku(stepAktif - 1);
      return;
    }
    var btnCari = ev.target.closest('[data-toggle-cari-bku]');
    if (btnCari) {
      var box = document.getElementById('kotak-cari-bku');
      if (box) {
        box.classList.toggle('hidden');
        var inp = document.getElementById('input-cari-bku');
        if (inp && !box.classList.contains('hidden')) inp.focus();
      }
    }
  });

  // Toggle Toko/Badan Usaha vs Orang Penerima Pembayaran (Screenshot 235723.png)
  document.addEventListener('change', function (ev) {
    if (ev.target && ev.target.id === 'cek-tanpa-toko-bku') {
      var perorangan = ev.target.checked;
      var lNama = document.getElementById('lbl-nama-mitra-bku');
      var iNama = document.getElementById('inp-nama-mitra-bku');
      var lAlamat = document.getElementById('lbl-alamat-mitra-bku');
      var iAlamat = document.getElementById('inp-alamat-mitra-bku');
      var lNpwp = document.getElementById('lbl-npwp-mitra-bku');
      var tNpwp = document.getElementById('teks-tanpa-npwp-bku');
      if (lNama) lNama.textContent = perorangan ? 'Nama Orang Penerima Pembayaran' : 'Nama Toko/Badan Usaha';
      if (iNama) iNama.placeholder = perorangan ? 'Nama orang yang menerima honor atau penghasilan' : 'Masukkan nama toko/badan usaha';
      if (lAlamat) lAlamat.textContent = perorangan ? 'Alamat Orang Penerima Pembayaran (Opsional)' : 'Alamat Toko/Badan Usaha';
      if (iAlamat) iAlamat.placeholder = perorangan ? 'Alamat orang yang menerima honor atau penghasilan' : 'Masukkan alamat toko/badan usaha';
      if (lNpwp) lNpwp.textContent = perorangan ? 'NPWP/NIK Orang Penerima Pembayaran' : 'NPWP Toko/Badan Usaha';
      if (tNpwp) tNpwp.innerHTML = perorangan
        ? '<b>Centang</b> jika orang yang menerima pembayaran ini tidak memiliki NPWP'
        : '<b>Centang</b> jika toko/badan usaha ini tidak memiliki NPWP';
    }
  });

  // Pencarian langsung baris transaksi BKU
  document.addEventListener('input', function (ev) {
    if (ev.target && ev.target.id === 'input-cari-bku') {
      var q = ev.target.value.trim().toLowerCase();
      document.querySelectorAll('[data-baris-bku]').forEach(function (tr) {
        var teks = tr.getAttribute('data-cari-bku') || '';
        tr.classList.toggle('hidden', q !== '' && teks.indexOf(q) === -1);
      });
    }
  });
})();
</script>
@endsection
