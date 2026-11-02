@extends('layouts.print', ['title' => 'Buku Kas Pembantu ' . ucfirst($jenis)])
@section('content')
@php
  $namaJenis = $jenis === 'bank' ? 'BANK' : 'TUNAI';
  $totalMasuk = collect($rows)->sum('masuk');
  $totalKeluar = collect($rows)->sum('keluar');
  $saldoAkhir = $saldoAwal + $totalMasuk - $totalKeluar;
  $bendahara = $penandatangan->first(fn ($p) => str_contains(strtolower($p->jabatan), 'bendahara'));
  $kepsek = $penandatangan->first(fn ($p) => str_contains(strtolower($p->jabatan), 'kepala')) ?: $penandatangan->last();
  $bulanParam = $bulan ?? sprintf('%d-%02d', $tahun, (int) now()->format('n'));
@endphp

<div class="toolbar nonprint"> <form style="display:flex;gap:.5rem;align-items:center;font-size:.82rem" method="get"> <span>Periode:</span> <select name="periode" style="border:1px solid #cbd5e1;border-radius:.5rem;padding:.35rem .6rem" data-kirim-otomatis> <option value="bulan" @selected($periode === 'bulan')>Bulanan</option> <option value="tahun" @selected($periode === 'tahun')>Tahunan</option> </select> <input type="month" name="bulan" value="{{ $bulanParam }}" style="border:1px solid #cbd5e1;border-radius:.5rem;padding:.35rem .6rem" data-kirim-otomatis @disabled($periode === 'tahun')> </form> <a href="{{ route($jenis === 'bank' ? 'pembukuan.bukuPembantuTunai' : 'pembukuan.bukuPembantuBank', ['periode' => $periode, 'bulan' => $bulanParam]) }}"> Buku Pembantu {{ $jenis === 'bank' ? 'Tunai' : 'Bank' }}
  </a> <a href="{{ route('laporan.format') }}"> Pusat Format Laporan</a> <button type="button" data-cetak-halaman> Cetak / Simpan PDF</button> <a href="{{ route('bku') }}">← Kembali BKU</a>
</div> <div class="kop"> <div style="font-size:11px;text-transform:uppercase">{{ $sekolah->nama }} — NPSN {{ $sekolah->npsn }}</div> <div style="font-weight:800">BUKU KAS PEMBANTU {{ $namaJenis }}</div> <div style="font-size:.82rem">{{ $labelPeriode }} · Tahun Anggaran {{ $tahun }}</div> <div style="font-size:.75rem;color:#64748b"> Periode {{ \Carbon\Carbon::parse($awal)->translatedFormat('d F Y') }} s.d. {{ \Carbon\Carbon::parse($akhir)->translatedFormat('d F Y') }}
  </div>
</div> <table class="tbl"> <thead> <tr> <th style="width:2rem">No.</th> <th style="width:5.5rem">Tanggal</th> <th style="width:6rem">No. Bukti</th> <th>Uraian</th> <th class="num" style="width:7rem">Penerimaan (Rp)</th> <th class="num" style="width:7rem">Pengeluaran (Rp)</th> <th class="num" style="width:7.5rem">Saldo (Rp)</th> </tr> </thead> <tbody> <tr style="font-weight:600;background:#f8fafc"> <td></td> <td></td> <td></td> <td>SALDO AWAL {{ $periode === 'bulan' ? 'BULAN' : 'TAHUN' }} (termasuk SILPA)</td> <td></td> <td></td> <td class="num">{{ rp($saldoAwal) }}</td> </tr> @forelse ($rows as $i => $row)
      @php $t = $row['trx']; @endphp
      <tr> <td>{{ $i + 1 }}</td> <td style="white-space:nowrap">{{ $t->tanggal->format('d/m/Y') }}</td> <td>{{ $t->arkas_bku ?: ($t->no_bku ? '#'.$t->no_bku : '—') }}</td> <td> {{ $t->keterangan }}
          <span style="color:#64748b;font-size:10px">({{ $t->labelType() }})</span> </td> <td class="num">{{ $row['masuk'] ? rp($row['masuk']) : '' }}</td> <td class="num">{{ $row['keluar'] ? rp($row['keluar']) : '' }}</td> <td class="num">{{ rp($row['saldo']) }}</td> </tr> @empty
      <tr><td colspan="7" style="text-align:center;color:#64748b;font-style:italic;padding:1rem">Tidak ada mutasi kas {{ $jenis }} pada periode ini.</td></tr> @endforelse
  </tbody> <tfoot> <tr style="font-weight:700"> <td colspan="4" style="text-align:right">JUMLAH MUTASI / SALDO AKHIR</td> <td class="num">{{ rp($totalMasuk) }}</td> <td class="num">{{ rp($totalKeluar) }}</td> <td class="num">{{ rp($saldoAkhir) }}</td> </tr> </tfoot>
</table> <div class="ttd"> <div class="ttd-grid"> <div> <div style="margin-bottom:4rem">Mengetahui,<br>Kepala Sekolah</div> <div style="font-weight:700;text-decoration:underline">{{ $kepsek->nama ?? '( ……………………… )' }}</div> <div>NIP. {{ $kepsek->nip ?? '…' }}</div> </div> <div> <div style="margin-bottom:4rem">{{ $sekolah->kab_kota ?? '' }}, {{ \Carbon\Carbon::parse($akhir)->translatedFormat('d F Y') }}<br>Bendahara BOSP</div> <div style="font-weight:700;text-decoration:underline">{{ $bendahara->nama ?? '( ……………………… )' }}</div> <div>NIP. {{ $bendahara->nip ?? '…' }}</div> </div> </div>
</div>
@endsection
