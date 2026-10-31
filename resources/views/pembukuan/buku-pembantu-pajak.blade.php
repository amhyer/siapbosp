@extends('layouts.print', ['title' => 'Buku Pembantu Pajak'])
@section('content')
@php
  $bendahara = $penandatangan->first(fn ($p) => str_contains(strtolower($p->jabatan), 'bendahara'));
  $kepsek = $penandatangan->first(fn ($p) => str_contains(strtolower($p->jabatan), 'kepala')) ?: $penandatangan->last();
  $bulanParam = $bulan ?? sprintf('%d-%02d', $tahun, (int) now()->format('n'));
@endphp

<div class="toolbar nonprint"> <form style="display:flex;gap:.5rem;align-items:center;font-size:.82rem" method="get"> <span>Periode:</span> <select name="periode" style="border:1px solid #cbd5e1;border-radius:.5rem;padding:.35rem .6rem" data-kirim-otomatis> <option value="bulan" @selected($periode === 'bulan')>Bulanan</option> <option value="tahun" @selected($periode === 'tahun')>Tahunan</option> </select> <input type="month" name="bulan" value="{{ $bulanParam }}" style="border:1px solid #cbd5e1;border-radius:.5rem;padding:.35rem .6rem" data-kirim-otomatis @disabled($periode === 'tahun')> </form> <a href="{{ route('laporan.format') }}"> Pusat Format Laporan</a> <button type="button" data-cetak-halaman> Cetak / Simpan PDF</button> <a href="{{ route('bku') }}">← Kembali BKU</a>
</div> <div class="kop"> <div style="font-size:11px;text-transform:uppercase">{{ $sekolah->nama }} — NPSN {{ $sekolah->npsn }}</div> <div style="font-weight:800">BUKU PEMBANTU PAJAK</div> <div style="font-size:.82rem">{{ $labelPeriode }} · Tahun Anggaran {{ $tahun }}</div> <div style="font-size:.75rem;color:#64748b"> Periode {{ \Carbon\Carbon::parse($awal)->translatedFormat('d F Y') }} s.d. {{ \Carbon\Carbon::parse($akhir)->translatedFormat('d F Y') }}
  </div>
</div> <table class="tbl"> <thead> <tr> <th style="width:2rem">No.</th> <th style="width:5.5rem">Tanggal</th> <th style="width:6rem">No. Bukti</th> <th>Uraian</th> <th style="width:8rem">Jenis Pajak</th> <th class="num" style="width:7rem">Nilai Potongan (Rp)</th> <th style="width:5rem">Status</th> <th style="width:5.5rem">Tgl. Setor</th> <th style="width:7rem">NTPN</th> </tr> </thead> <tbody> @forelse ($rows as $i => $row)
    <tr> <td>{{ $i + 1 }}</td> <td style="white-space:nowrap">{{ \Carbon\Carbon::parse($row->tanggal)->format('d/m/Y') }}</td> <td>{{ $row->arkas_bku ?: ($row->no_bukti ?: '—') }}</td> <td> {{ $row->keterangan ?: '—' }}
        @if ($row->kode_billing)
          <span style="color:#64748b;font-size:10px">Kode billing: {{ $row->kode_billing }}</span> @endif
      </td> <td>{{ trim($row->pajak_kode.' '.$row->pajak_nama) }}</td> <td class="num">{{ rp($row->nilai) }}</td> <td>{{ $row->status === 'sudah' ? 'Sudah setor' : 'Belum setor' }}</td> <td>{{ $row->tgl_setor ? \Carbon\Carbon::parse($row->tgl_setor)->format('d/m/Y') : '—' }}</td> <td>{{ $row->ntpn ?: '—' }}</td> </tr> @empty
      <tr><td colspan="9" style="text-align:center;color:#64748b;font-style:italic;padding:1rem">Tidak ada potongan pajak pada periode ini.</td></tr> @endforelse
  </tbody> <tfoot> <tr style="font-weight:700"> <td colspan="5" style="text-align:right">JUMLAH</td> <td class="num">{{ rp($total['nilai']) }}</td> <td colspan="3"></td> </tr> </tfoot>
</table> <div style="height:.8rem"></div> <table class="tbl"> <thead> <tr> <th style="width:2rem">No.</th> <th>Jenis Pajak</th> <th class="num" style="width:8rem">Total Potongan</th> <th class="num" style="width:8rem">Sudah Setor</th> <th class="num" style="width:8rem">Belum Setor</th> </tr> </thead> <tbody> @forelse ($ringkasan as $r)
    <tr> <td>{{ $loop->iteration }}</td> <td>{{ $r['nama'] }}</td> <td class="num">{{ rp($r['total']) }}</td> <td class="num">{{ rp($r['sudah']) }}</td> <td class="num">{{ rp($r['belum']) }}</td> </tr> @empty
      <tr><td colspan="5" style="text-align:center;color:#64748b">Belum ada data pajak.</td></tr> @endforelse
  </tbody> <tfoot> <tr style="font-weight:700"> <td colspan="2" style="text-align:right">TOTAL</td> <td class="num">{{ rp($total['nilai']) }}</td> <td class="num">{{ rp($total['sudah']) }}</td> <td class="num">{{ rp($total['belum']) }}</td> </tr> </tfoot>
</table> <div class="ttd"> <div class="ttd-grid"> <div> <div style="margin-bottom:4rem">Mengetahui,<br>Kepala Sekolah</div> <div style="font-weight:700;text-decoration:underline">{{ $kepsek->nama ?? '( ……………………… )' }}</div> <div>NIP. {{ $kepsek->nip ?? '…' }}</div> </div> <div> <div style="margin-bottom:4rem">{{ $sekolah->kab_kota ?? '' }}, {{ \Carbon\Carbon::parse($akhir)->translatedFormat('d F Y') }}<br>Bendahara BOSP</div> <div style="font-weight:700;text-decoration:underline">{{ $bendahara->nama ?? '( ……………………… )' }}</div> <div>NIP. {{ $bendahara->nip ?? '…' }}</div> </div> </div>
</div>
@endsection
