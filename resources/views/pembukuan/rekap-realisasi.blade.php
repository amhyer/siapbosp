@extends('layouts.print', ['title' => 'Rekapitulasi Realisasi Belanja'])
@section('content')
@php
  $bendahara = $penandatangan->first(fn ($p) => str_contains(strtolower($p->jabatan), 'bendahara'));
  $kepsek = $penandatangan->first(fn ($p) => str_contains(strtolower($p->jabatan), 'kepala')) ?: $penandatangan->last();
  $labelKolom = match ($periode) {
      'bulanan' => 'Realisasi Bulan Ini',
      'tahunan' => 'Realisasi Tahun Ini',
      'tahapan' => 'Realisasi Tahapan',
      default => 'Realisasi Semester',
  };
  $labelTotal = match ($periode) {
      'bulanan' => 'TOTAL BULAN',
      'tahunan' => 'TOTAL TAHUN',
      'tahapan' => 'TOTAL TAHAPAN',
      default => 'TOTAL SEMESTER',
  };
  $bulanParam = $bulan ?? sprintf('%d-%02d', $tahun, (int) now()->format('n'));
@endphp

<div class="toolbar nonprint"> <form style="display:flex;gap:.4rem;align-items:center;font-size:.82rem" method="get"> <span>Periode:</span> <select name="periode" style="border:1px solid #cbd5e1;border-radius:.5rem;padding:.35rem .6rem" data-kirim-otomatis> <option value="bulanan" @selected($periode === 'bulanan')>Bulanan</option> <option value="semester" @selected($periode === 'semester')>Semester</option> <option value="tahunan" @selected($periode === 'tahunan')>Tahunan</option> <option value="tahapan" @selected($periode === 'tahapan')>Tahapan</option> </select> @if ($periode === 'bulanan')
      <input type="month" name="bulan" value="{{ $bulanParam }}" style="border:1px solid #cbd5e1;border-radius:.5rem;padding:.35rem .6rem" data-kirim-otomatis> @elseif ($periode === 'semester')
      <select name="semester" style="border:1px solid #cbd5e1;border-radius:.5rem;padding:.35rem .6rem" data-kirim-otomatis> <option value="1" @selected($semester === 1)>Semester 1</option> <option value="2" @selected($semester === 2)>Semester 2</option> </select> @elseif ($periode === 'tahapan')
      <select name="tahap" style="border:1px solid #cbd5e1;border-radius:.5rem;padding:.35rem .6rem" data-kirim-otomatis> @forelse ($stages as $s)
          <option value="{{ $s->id }}" @selected($tahap && $s->id === $tahap->id)>{{ $s->nama }}</option> @empty
          <option value="">Belum ada tahap</option> @endforelse
      </select> @endif
  </form> <button type="button" data-cetak-halaman> Cetak / Simpan PDF</button> <a href="{{ route('laporan.format', ['bulan' => (int) substr($bulanParam, 5, 2), 'semester' => $semester, 'tahap' => $tahap?->id]) }}"> Pusat Format Laporan</a> <a href="{{ route('bku') }}">← Kembali BKU</a>
</div> <div class="kop"> <h1 style="font-size:1.15rem;margin:0">{{ $sekolah->nama }}</h1> <div style="font-size:.85rem"><b>REKAPITULASI REALISASI BELANJA</b></div> <div style="font-size:.8rem">{{ $labelPeriode }}</div> <div style="font-size:.78rem;color:#64748b"> @if ($periode === 'tahapan')
      Realisasi dihitung dari transaksi yang tercatat pada tahap ini.
    @else
      Periode {{ \Carbon\Carbon::parse($awal)->translatedFormat('d F Y') }} s.d. {{ \Carbon\Carbon::parse($akhir)->translatedFormat('d F Y') }}.
    @endif
    Sisa pagu menjadi bahan usulan SiLPA periode berikutnya.
  </div>
</div> <table class="tbl"> <thead> <tr> <th style="width:2rem">No.</th> <th style="width:9.5rem">Kode Rekening</th> <th>Uraian Rekening</th> <th class="num" style="width:7rem">Anggaran</th> <th class="num" style="width:7.5rem">{{ $labelKolom }}</th> <th class="num" style="width:6.5rem">Sisa Pagu</th> <th class="num" style="width:3.5rem">%</th> </tr> </thead> <tbody> @forelse ($rows as $i => $x)
    <tr> <td>{{ $i + 1 }}</td> <td>{{ $x['kode'] }}</td> <td>{{ $x['nama'] ?? '—' }}</td> <td class="num">{{ $x['punyaAnggaran'] ? rp($x['anggaran']) : '—' }}</td> <td class="num">{{ rp($x['realisasi']) }}</td> <td class="num">{{ $x['sisa'] === null ? '—' : rp($x['sisa']) }}</td> <td class="num">{{ $x['punyaAnggaran'] && $x['anggaran'] > 0 ? round($x['realisasi'] / $x['anggaran'] * 100, 1).'%' : '—' }}</td> </tr> @empty
    <tr><td colspan="7" style="text-align:center;color:#64748b">Tidak ada rekening dengan anggaran maupun realisasi pada periode ini.</td></tr> @endforelse
  </tbody> <tfoot> <tr> <td colspan="3" style="background:#0f172a;color:#fff;font-weight:700">{{ $labelTotal }}</td> <td class="num" style="background:#0f172a;color:#fff;font-weight:700">{{ rp($sumAnggaran) }}</td> <td class="num" style="background:#0f172a;color:#fff;font-weight:700">{{ rp($sumRealisasi) }}</td> <td class="num" style="background:#0f172a;color:#fff;font-weight:700">{{ $sumAnggaran > 0 ? rp($sumAnggaran - $sumRealisasi) : '—' }}</td> <td class="num" style="background:#0f172a;color:#fff;font-weight:700">{{ $sumAnggaran > 0 ? round($sumRealisasi / $sumAnggaran * 100, 1).'%' : '—' }}</td> </tr> </tfoot>
</table> <div class="ttd-grid mt-8"> <div> Mengetahui/Menyetujui,<br>Bendahara Sekolah<br><br><br><br> <b>{{ $bendahara?->nama ?? '..................' }}</b><br> {{ $bendahara?->nip ? 'NIP. '.$bendahara->nip : '' }}
  </div> <div> Mengetahui/Menyetujui,<br>Kepala Sekolah<br><br><br><br> <b>{{ $kepsek?->nama ?? '..................' }}</b><br> {{ $kepsek?->nip ? 'NIP. '.$kepsek->nip : '' }}
  </div>
</div>
@endsection
