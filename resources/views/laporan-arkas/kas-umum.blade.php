@extends('layouts.app', ['title' => 'Daftar Kas Umum'])
@section('content')
@include('laporan-arkas._header', ['judul' => 'DAFTAR KAS UMUM — ' . ($namaBulan[$bulan] ?? '') . ' ' . $kop['tahun']])
<div class="mb-2 nonprint">
  <a href="{{ route('bku', ['bulan' => $kop['tahun'].'-'.str_pad((string) $bulan, 2, '0', STR_PAD_LEFT)]) }}" class="btn2 btn text-sm">← Buku resmi BKU bulan ini (dengan saldo)</a>
</div>
<div class="card overflow-x-auto">
  <div class="tbl-wrap"><table class="tbl text-[12px]">
    <thead><tr>
      <th>no</th><th>tgl_transaksi</th><th>no_bukti</th><th>tanggal_nota</th><th>no_nota</th>
      <th>uraian</th><th>kode_rekening</th><th>uraian_rekening</th><th class="num">volume</th>
      <th class="num">nominal</th><th>kegiatan</th><th>penerima</th>
    </tr></thead>
    <tbody>
      @forelse($rows as $row)
      <tr>
        <td>{{ $row['no'] }}</td>
        <td class="whitespace-nowrap">{{ $row['tgl'] }}</td>
        <td class="whitespace-nowrap">{{ $row['no_bukti'] }}</td>
        <td class="whitespace-nowrap">{{ $row['tanggal_nota'] ?? '—' }}</td>
        <td>{{ $row['no_nota'] ?: '—' }}</td>
        <td>{{ \Illuminate\Support\Str::limit($row['uraian'], 45) }}</td>
        <td class="font-mono text-xs whitespace-nowrap">{{ $row['kode'] ?: '—' }}</td>
        <td class="text-xs">{{ \Illuminate\Support\Str::limit($row['rekNama'], 28) }}</td>
        <td class="num">{{ $row['volume'] ?? '—' }}</td>
        <td class="num font-semibold">{{ rp($row['nominal']) }}</td>
        <td class="text-xs">{{ $row['kegiatan'] !== '' ? ($row['kegiatan'] . ($row['kegiatanNama'] ? ' ' . $row['kegiatanNama'] : '')) : '—' }}</td>
        <td class="text-xs">{{ $row['penerima'] ?: '—' }}</td>
      </tr>
      @empty
      <tr><td colspan="12" class="text-center text-slate-400">Tidak ada transaksi di bulan ini.</td></tr>
      @endforelse
    </tbody>
  </table></div>
</div>
@endsection
