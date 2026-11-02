@extends('layouts.app', ['title' => 'Realisasi Penerimaan Dan Belanja'])
@section('content')
@php
  $bulanAcuan = $bulan ?: (int) now()->format('n');
  $semesterAcuan = $semester ?? ((int) now()->format('n') <= 6 ? 1 : 2);
  $labelLalu = $periode === 'semester' ? 'Realisasi s/d Semester Sebelumnya' : 'Realisasi s/d Bulan Sebelumnya';
  $labelIni = $periode === 'semester' ? 'Realisasi Semester Ini' : 'Realisasi Bulan Ini';
  $labelSd = $periode === 'semester' ? 'Jumlah Realisasi s/d Semester Ini' : 'Jumlah Realisasi s/d Bulan Ini';
@endphp
<div class="flex flex-wrap gap-2 mb-2 text-sm nonprint"> <a class="btn2 {{ $periode === 'bulanan' ? 'btn' : '' }}"
     href="{{ route('laporan-arkas.penerimaan-belanja', ['periode' => 'bulanan', 'bulan' => $bulanAcuan]) }}">Bulanan</a> <a class="btn2 {{ $periode === 'semester' ? 'btn' : '' }}"
     href="{{ route('laporan-arkas.penerimaan-belanja', ['periode' => 'semester', 'semester' => $semesterAcuan]) }}">Semester</a> <a class="btn2" href="{{ route('laporan.format', ['bulan' => $bulanAcuan, 'semester' => $semesterAcuan]) }}"> Pusat Format Laporan</a>
</div>
@include('laporan-arkas._header', ['judul' => 'Realisasi Penerimaan Dan Belanja — ' . $labelPeriode])
<div class="card"> <div class="tbl-wrap"><table class="tbl"> <thead><tr> <th>Uraian</th><th class="num">Jumlah Anggaran</th> <th class="num">{{ $labelLalu }}</th><th class="num">{{ $labelIni }}</th> <th class="num">{{ $labelSd }}</th><th class="num">Selisih/ Kurang</th> </tr></thead> <tbody> @foreach($rows as $row)
      <tr> <td>{{ $row['uraian'] }}</td> <td class="num">{{ rp($row['ang']) }}</td> <td class="num">{{ rp($row['lalu']) }}</td> <td class="num {{ $row['ini'] > 0 ? 'text-emerald-700 font-semibold' : '' }}">{{ rp($row['ini']) }}</td> <td class="num font-semibold">{{ rp($row['sd']) }}</td> <td class="num {{ $row['selisih'] < 0 ? 'text-rose-700' : '' }}">{{ rp($row['selisih']) }}</td> </tr> @endforeach
    </tbody> <tfoot> <tr class="font-bold bg-slate-50"><td class="text-right">Jumlah</td> <td class="num">{{ rp($total['ang']) }}</td><td class="num">{{ rp($total['lalu']) }}</td> <td class="num">{{ rp($total['ini']) }}</td><td class="num">{{ rp($total['sd']) }}</td> <td class="num">{{ rp($total['selisih']) }}</td></tr> </tfoot> </table></div>
</div>
@endsection
