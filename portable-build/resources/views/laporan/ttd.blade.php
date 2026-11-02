{{-- Blok tanda tangan LPJ — pakai: @include('laporan.ttd') dengan $sekolah, $kepsek, $bendahara --}}
@php($gKep = \App\Services\TtdGambar::dataUri($kepsek?->ttd_path))
@php($gBen = \App\Services\TtdGambar::dataUri($bendahara?->ttd_path))
<div class="ttd">
  <div class="ttd-grid">
    <div>
      <div style="margin-bottom:4rem">Mengetahui,<br>Kepala Sekolah</div>
      @if($gKep)<div style="margin:-6rem 0 .5rem"><img src="{{ $gKep }}" style="max-height:55px"></div>@endif
      <div style="font-weight:700;text-decoration:underline">{{ $kepsek->nama ?? '( ……………………… )' }}</div>
      <div>NIP. {{ $kepsek->nip ?? '…' }}</div>
    </div>
    <div>
      <div style="margin-bottom:4rem">{{ $sekolah->kab_kota ?? '' }}, {{ now()->translatedFormat('d F Y') }}<br>Bendahara BOSP</div>
      @if($gBen)<div style="margin:-6rem 0 .5rem"><img src="{{ $gBen }}" style="max-height:55px"></div>@endif
      <div style="font-weight:700;text-decoration:underline">{{ $bendahara->nama ?? '( ……………………… )' }}</div>
      <div>NIP. {{ $bendahara->nip ?? '…' }}</div>
    </div>
  </div>
</div>
