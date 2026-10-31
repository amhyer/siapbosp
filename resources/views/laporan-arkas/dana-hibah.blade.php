@extends('layouts.app', ['title' => 'Laporan Penggunaan Hibah Dana'])
@section('content')
@php
  $angka = fn ($v) => number_format((int) $v, 0, ',', '.');
  $rpTitik = fn ($v) => 'Rp. '.number_format((int) $v, 0, ',', '.');
  $komite = $penandatangan->first(fn ($p) => stripos((string) ($p->jabatan ?? ''), 'komite') !== false);
  $kepsek = $penandatangan->first(fn ($p) => stripos((string) ($p->jabatan ?? ''), 'kepala') !== false);
  $bendahara = $penandatangan->first(fn ($p) => stripos((string) ($p->jabatan ?? ''), 'bendahara') !== false);
@endphp

<div class="flex flex-wrap gap-2 mb-2 text-sm nonprint">
  <a class="btn2 {{ $periode === 'semester' && $semester === 1 ? 'btn' : '' }}"
     href="{{ route('laporan-arkas.dana-hibah', ['periode' => 'semester', 'semester' => 1]) }}">Semester 1</a>
  <a class="btn2 {{ $periode === 'semester' && $semester === 2 ? 'btn' : '' }}"
     href="{{ route('laporan-arkas.dana-hibah', ['periode' => 'semester', 'semester' => 2]) }}">Semester 2</a>
  <a class="btn2 {{ $periode === 'tahunan' ? 'btn' : '' }}"
     href="{{ route('laporan-arkas.dana-hibah', ['periode' => 'tahunan']) }}">Tahunan</a>
  <a class="btn2" href="{{ request()->fullUrl() }}" data-cetak="Laporan Penggunaan Hibah Dana">🖨️ Cetak</a>
  <a class="btn2" href="{{ route('laporan.format') }}">Pusat Format Laporan</a>
</div>

@include('laporan-arkas._header', ['judul' => 'Laporan Penggunaan Hibah Dana — '.$labelPeriode])

@if(! $adaSumber)
  <div class="card">
    <div class="flex items-start gap-3">
      <span class="text-2xl" aria-hidden="true">📭</span>
      <div class="min-w-0">
        <div class="text-sm font-bold text-[#2f3031]">Belum ada sumber dana hibah pada tahun {{ $tahun }}</div>
        <p class="mt-1 max-w-3xl text-[12.5px] leading-relaxed text-[#65696c]">
          SiapBOSP mengikuti master sumber dana ARKAS 4: BOS Reguler, BOS Kinerja, BOSP Daerah,
          SiLPA Tahun Sebelumnya, BOSP Afirmasi, dan Sumber Dana Lainnya. Sumber dana <b>Hibah</b>
          tidak termasuk master bawaan, sehingga laporan ini kosong sampai sekolah memiliki
          sumber dana hibah.
        </p>
        <p class="mt-2 max-w-3xl text-[12.5px] leading-relaxed text-[#65696c]">
          Bila sekolah menerima hibah, tambahkan sumber dananya lewat
          <a class="font-semibold text-[#0b5fef] hover:underline" href="{{ route('tarik-arkas.sinkron') }}#sinkron-arkas">Tarik/Impor ARKAS</a>
          atau catat rincian RKAS dengan nama sumber dana yang memuat kata “Hibah”. Laporan ini
          akan otomatis memuat pagu, realisasi, dan sisa dana hibah tersebut.
        </p>
      </div>
    </div>
  </div>
@else
  <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-3">
    <div class="card">
      <div class="text-xs text-slate-500">Sumber Hibah</div>
      <div class="text-[13px] font-bold text-slate-800">{{ $sumberHibah->implode(' + ') }}</div>
    </div>
    <div class="card">
      <div class="text-xs text-slate-500">Pagu Hibah</div>
      <div class="text-xl font-bold text-slate-800">{{ $rpTitik($total['anggaran']) }}</div>
    </div>
    <div class="card">
      <div class="text-xs text-slate-500">Realisasi</div>
      <div class="text-xl font-bold text-emerald-700">{{ $rpTitik($total['realisasi']) }}</div>
    </div>
    <div class="card">
      <div class="text-xs text-slate-500">Sisa Pagu</div>
      <div class="text-xl font-bold {{ $total['sisa'] < 0 ? 'text-rose-700' : 'text-slate-800' }}">{{ $rpTitik($total['sisa']) }}</div>
      <div class="mt-0.5 text-[11px] text-slate-500">Serapan {{ number_format((float) $total['persen'], 1, ',', '.') }}%</div>
    </div>
  </div>

  <div class="card overflow-x-auto">
    <div class="tbl-wrap">
      <table class="tbl text-[12.5px]">
        <thead>
          <tr>
            <th style="width:2.5rem">No.</th>
            <th style="width:8rem">Kode Rekening</th>
            <th>Uraian</th>
            <th>Sumber Dana</th>
            <th class="num" style="width:8rem">Pagu</th>
            <th class="num" style="width:8rem">Realisasi</th>
            <th class="num" style="width:8rem">Sisa</th>
            <th class="num" style="width:5rem">%</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $i => $row)
            <tr>
              <td>{{ $i + 1 }}.</td>
              <td style="font-family:monospace;font-size:11px">{{ $row['kode'] !== '' ? $row['kode'] : '—' }}</td>
              <td>{{ $row['uraian'] !== '' ? $row['uraian'] : '—' }}</td>
              <td>{{ $row['sumber'] }}</td>
              <td class="num">{{ $angka($row['anggaran']) }}</td>
              <td class="num">{{ $angka($row['realisasi']) }}</td>
              <td class="num {{ $row['sisa'] < 0 ? 'text-rose-700' : '' }}">{{ $angka($row['sisa']) }}</td>
              <td class="num">{{ number_format((float) $row['persen'], 1, ',', '.') }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="py-6 text-center text-slate-400">
                Belum ada rincian RKAS bersumber dana hibah pada tahun {{ $tahun }}.
              </td>
            </tr>
          @endforelse
        </tbody>
        <tfoot>
          <tr class="font-bold bg-slate-50">
            <td colspan="4" class="text-right">Jumlah</td>
            <td class="num">{{ $angka($total['anggaran']) }}</td>
            <td class="num">{{ $angka($total['realisasi']) }}</td>
            <td class="num">{{ $angka($total['sisa']) }}</td>
            <td class="num">{{ number_format((float) $total['persen'], 1, ',', '.') }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:2rem;margin-top:1.5rem;font-size:11.5px;text-align:center">
    <div>
      <div style="margin-bottom:3.5rem">Komite Sekolah</div>
      <div style="font-weight:700">{{ $komite->nama ?? '( ……………………… )' }}</div>
      <div>{{ ! empty($komite?->nip) ? 'NIP: '.$komite->nip : '' }}</div>
    </div>
    <div>
      <div style="margin-bottom:3.5rem">Kepala Sekolah</div>
      <div style="font-weight:700">{{ $kepsek->nama ?? '( ……………………… )' }}</div>
      <div>NIP: {{ $kepsek->nip ?? '…' }}</div>
    </div>
    <div>
      <div style="margin-bottom:.2rem">{{ $tempatTtd }}, {{ $tanggalTtd }}</div>
      <div style="margin-bottom:3.3rem">Bendahara Sekolah</div>
      <div style="font-weight:700">{{ $bendahara->nama ?? '( ……………………… )' }}</div>
      <div>NIP: {{ $bendahara->nip ?? '…' }}</div>
    </div>
  </div>
@endif
@endsection
