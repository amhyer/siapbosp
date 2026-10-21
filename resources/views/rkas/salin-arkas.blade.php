@extends('layouts.app', ['title' => 'Mode Salin Cepat ke Aplikasi ARKAS 4 Desktop'])
@section('subtitle', $h->labelJenis() . ' TA ' . $h->tahun . ' Revisi ke-' . $h->revisi_ke . ' · Buka berdampingan dengan jendela ARKAS 4 Desktop')
@section('content')
<div class="card border-l-4 border-emerald-600 flex flex-wrap items-center justify-between gap-3">
  <div>
    <div class="font-bold text-slate-800 text-sm">📋 Panduan Input Cepat ke ARKAS 4 Desktop ({{ $sudahInput }}/{{ $totalItem }} rincian selesai · {{ $persenInput }}%)</div>
    <p class="text-xs text-slate-500 mt-0.5">Urutan kolom persis mengikuti form <b>+ Tambah Kegiatan</b> di ARKAS 4: <b>Kegiatan SNP → Rekening Belanja → Uraian → Harga Satuan → Bulan &amp; Volume</b>. Klik tombol salin di tiap kolom lalu centang setelah masuk ke ARKAS.</p>
  </div>
  <a href="{{ route('rkas.show', $h) }}" class="btn2 btn">← Kembali ke Review RKAS</a>
</div>

<div class="h-2.5 rounded-full bg-slate-200 overflow-hidden">
  <div class="h-full bg-emerald-500 transition-all" style="width:{{ $persenInput }}%"></div>
</div>

@foreach($grouped as $grupJudul => $rows)
<div class="card space-y-3">
  <div class="flex flex-wrap justify-between items-center border-b border-slate-100 pb-2">
    <div class="font-bold text-slate-800 text-sm">🗂️ {{ $grupJudul }}</div>
    <span class="badge bg-indigo-100 text-indigo-700">{{ rp($rows->sum('jumlah')) }}</span>
  </div>
  <div class="overflow-x-auto">
    <table class="tbl">
      <thead>
        <tr>
          <th>Status Input</th>
          <th>1. Kode Kegiatan</th>
          <th>2. Kode Rekening</th>
          <th>3. Uraian Kegiatan</th>
          <th class="num">4. Harga Satuan</th>
          <th>5. Bulan &amp; Volume di ARKAS</th>
          <th class="num">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rows as $d)
        @php
          $blnAktif = collect($d->bulanMap())->filter(fn($v) => $v > 0);
        @endphp
        <tr class="{{ $d->sudah_diinput_arkas ? 'bg-emerald-50/60 opacity-75' : '' }}">
          <td>
            <form method="post" action="{{ route('rkas.toggleArkas', [$h, $d]) }}">
              @csrf
              <button class="badge {{ $d->sudah_diinput_arkas ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-700' }}">
                {{ $d->sudah_diinput_arkas ? '✓ Sudah Masuk ARKAS' : '⬜ Tandai Selesai' }}
              </button>
            </form>
          </td>
          <td class="font-mono text-xs whitespace-nowrap">
            {{ $d->kode_kegiatan ?: '06.05' }}
            <button type="button" class="text-indigo-600 ml-1" onclick="salinTeks('{{ $d->kode_kegiatan ?: '06.05' }}', this)">📋</button>
          </td>
          <td class="font-mono text-xs whitespace-nowrap">
            {{ $d->rekening->kode }}
            <button type="button" class="text-indigo-600 ml-1" onclick="salinTeks('{{ $d->rekening->kode }}', this)">📋</button>
          </td>
          <td>
            <span class="font-medium">{{ $d->uraian }}</span>
            <button type="button" class="text-indigo-600 ml-1 text-xs" onclick="salinTeks(@js($d->uraian), this)">📋 Salin</button>
          </td>
          <td class="num font-mono whitespace-nowrap">
            {{ (int) $d->harga_satuan }}
            <button type="button" class="text-indigo-600 ml-1 text-xs" onclick="salinTeks('{{ (int) $d->harga_satuan }}', this)">📋</button>
          </td>
          <td class="text-xs">
            @foreach($blnAktif as $m => $vol)
              <span class="inline-block rounded bg-sky-50 border border-sky-200 px-2 py-0.5 mr-1 mb-1">
                <b>{{ $namaBulan[$m] ?? ('Bln '.$m) }}</b>: {{ $vol }} {{ $d->satuan }}
              </span>
            @endforeach
          </td>
          <td class="num font-bold">{{ rp($d->jumlah) }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endforeach

<script>
function salinTeks(txt, el){
  navigator.clipboard?.writeText(String(txt));
  const asli = el.textContent;
  el.textContent = '✓';
  setTimeout(() => el.textContent = asli, 1000);
}
</script>
@endsection
