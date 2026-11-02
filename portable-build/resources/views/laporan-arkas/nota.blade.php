@extends('layouts.app', ['title' => 'Daftar Nota'])
@section('content')
@include('laporan-arkas._header', ['judul' => 'DAFTAR NOTA — ' . ($namaBulan[$bulan] ?? '') . ' ' . $kop['tahun']])
<div class="card overflow-x-auto">
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr>
      <th>no</th><th>no_bukti</th><th>tanggal_nota</th><th>no_nota</th><th>pembayaran</th>
      <th>nama_toko/ penerima</th><th>alamat</th><th class="num">total</th><th class="num">lunas_bayar</th><th>tgl_transaksi</th>
    </tr></thead>
    <tbody>
      @forelse($rows as $row)
      <tr>
        <td>{{ $row['no'] }}</td>
        <td class="whitespace-nowrap">{{ $row['no_bukti'] }}</td>
        <td class="whitespace-nowrap">{{ $row['tanggal_nota'] }}</td>
        <td>{{ $row['no_nota'] ?: '—' }}</td>
        <td>{{ $row['pembayaran'] }}</td>
        <td>
          {{ $row['nama'] ?: '—' }}
          @if($row['alamat'] || $row['no_telp'] || $row['npwp'])
            <div class="text-[11px] text-slate-500">
              {{ $row['alamat'] }}{{ $row['no_telp'] ? ' — Telp. '.$row['no_telp'] : '' }}{{ $row['npwp'] ? ' — NPWP '.$row['npwp'] : '' }}
            </div>
          @endif
        </td>
        <td class="num font-semibold">{{ rp($row['total']) }}</td>
        <td class="num">{{ rp($row['lunas']) }}</td>
        <td class="whitespace-nowrap">{{ $row['tgl_transaksi'] }}</td>
      </tr>
      @empty
      <tr><td colspan="10" class="text-center text-slate-400">Tidak ada nota di bulan ini.</td></tr>
      @endforelse
    </tbody>
  </table></div>
</div>
@endsection
