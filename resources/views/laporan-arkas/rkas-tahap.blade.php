@extends('layouts.app', ['title' => 'Rincian RKAS per Tahap'])
@section('content')
@include('laporan-arkas._header', ['judul' => 'Rincian Rencana Kegiatan Dan Anggaran Sekolah (RKAS) Per Tahap'])
<div class="flex flex-wrap items-center gap-2 mb-3">
  <a href="{{ route('laporan-arkas.rapbs-cetak') }}" class="btn2 btn" data-cetak="Kertas Kerja RAPBS per Tahap">🖨️ Cetak Kertas Kerja RAPBS (per Tahap)</a>
  <a href="{{ route('laporan-arkas.rka-unit', ['format' => 'triwulan']) }}" class="btn2 btn" data-cetak="RKA Unit Kerja per Triwulan">🖨️ Cetak RKA Unit Kerja (per Triwulan)</a>
  <a href="{{ route('laporan-arkas.rka-unit', ['varian' => 'dpa', 'format' => 'tahap']) }}" class="btn2 btn" data-cetak="DPA Unit Kerja per Tahap">🖨️ Cetak DPA (RKAS) Unit Kerja (per Tahap)</a>
  <a href="{{ route('laporan-arkas.rpd-k7') }}" class="btn2 btn" data-cetak="RPD K7">🖨️ Cetak RPD K7 (Realisasi Penggunaan Dana)</a>
  <a href="{{ route('laporan-arkas.rka-unit', ['varian' => 'rka22']) }}" class="btn2 btn" data-cetak="RKA Unit Kerja 2.2">🖨️ Cetak RKA 2.2 (Belanja Langsung)</a>
  <a href="{{ route('laporan-arkas.apbs') }}" class="btn2 btn" data-cetak="APBS (RKAS) Semua Sumber Dana">🖨️ Cetak APBS (RKAS) Semua Sumber Dana</a>
  <span class="text-[12px] text-slate-500">Format cetak ARKAS: penerimaan per sumber dana + pohon belanja per tahap.</span>
</div>
<div class="card overflow-x-auto">
  <div class="tbl-wrap"><table class="tbl text-[12px]">
    <thead><tr>
      <th>No</th><th>Kode Rekening</th><th>Kode Program</th><th>Uraian</th>
      <th class="num">Volume</th><th>Satuan</th><th class="num">Tarif</th><th class="num">Jumlah</th>
      @for($i = 1; $i <= $nTahap; $i++) <th class="num">Tahap {{ $i }}</th> @endfor
    </tr></thead>
    <tbody>
      @php $tj = 0; @endphp
      @forelse($rows as $row)
      @php $tj += $row['jumlah']; @endphp
      <tr>
        <td>{{ $row['no'] }}</td>
        <td class="font-mono text-xs whitespace-nowrap">{{ $row['rek'] }}</td>
        <td class="text-xs">{{ $row['program'] }}</td>
        <td>{{ \Illuminate\Support\Str::limit($row['uraian'], 55) }}</td>
        <td class="num">{{ $row['volume'] }}</td>
        <td>{{ $row['satuan'] }}</td>
        <td class="num">{{ rp($row['tarif']) }}</td>
        <td class="num font-semibold">{{ rp($row['jumlah']) }}</td>
        @for($i = 1; $i <= $nTahap; $i++)
          <td class="num {{ $row['tahap'][$i] > 0 ? '' : 'text-slate-300' }}">{{ $row['tahap'][$i] > 0 ? rp($row['tahap'][$i]) : '—' }}</td>
        @endfor
      </tr>
      @empty
      <tr><td colspan="9" class="text-center text-slate-400">Belum ada RKAS untuk tahun ini.</td></tr>
      @endforelse
    </tbody>
    <tfoot>
      <tr class="font-bold bg-slate-50"><td colspan="7" class="text-right">Jumlah</td>
        <td class="num">{{ rp($tj) }}</td>
        @for($i = 1; $i <= $nTahap; $i++) <td class="num">{{ rp($totalTahap[$i] ?? 0) }}</td> @endfor
      </tr>
    </tfoot>
  </table></div>
  @if($tahaps->isEmpty())<p class="text-xs text-amber-700 mt-2">Tahap/periode belum dibuat Dinas untuk tahun ini — pembagian tahap memakai rata-rata ke {{ $nTahap }} tahap.</p>@endif
</div>
@endsection
