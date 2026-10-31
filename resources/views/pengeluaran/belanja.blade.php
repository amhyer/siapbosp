@extends('layouts.app', ['title' => 'Belanja'])
@section('content')
{{-- Tab sumber dana ala ARKAS 4: menyaring daftar belanja per jenis BOS. --}}
@include('partials._tab-sumber-dana', [
  'rute' => route('belanja.index'),
  'aktif' => $tabAktif,
  'tabs' => $tabsSumber,
  'query' => array_filter(['q' => $q ?? '']),
  'semua' => 'Semua sumber dana',
])
<div class="flex flex-wrap justify-between items-center gap-2">
  <p class="text-sm text-slate-500">Pencatatan belanja Dana BOSP (tahun {{ $tahun }}).</p>
  <a href="{{ route('belanja.create') }}" class="btn"><x-icon name="plus" class="h-4 w-4"/> Input Belanja</a>
</div>
<div class="card !py-3 flex flex-wrap gap-2 items-center">
  <form method="get" class="flex gap-2 items-center">
    <input type="text" name="q" value="{{ $q }}" placeholder="Cari uraian / no bukti…" class="inp !py-1.5 text-sm w-72">
    <button class="btn2 btn-sm">Cari</button>
    @if($q !== '')<a href="{{ route('belanja.index') }}" class="text-xs text-slate-400 hover:text-rose-500">Hapus filter ({{ $items->total() }} hasil)</a>@endif
  </form>
</div>
<div class="card overflow-x-auto">
  <table class="tbl">
    <thead><tr><th>BKU</th><th>No Bukti</th><th>Tanggal</th><th>Uraian</th><th>Metode</th><th>Pajak</th><th class="num">Total</th><th></th></tr></thead>
    <tbody>
      @forelse($items as $t)
      <tr>
        <td>#{{ $t->no_bku }}</td><td>{{ $t->no_bukti ?? '—' }}</td><td>{{ $t->tanggal->format('d/m/Y') }}</td>
        <td>{{ Str::limit($t->keterangan, 50) }}</td>
        <td><span class="badge bg-slate-100 text-slate-600">{{ $t->metode_bayar }}</span></td>
        <td class="text-xs space-y-0.5">
          @php
            $pakaiSiplah = $t->details->contains(fn ($d) => $d->sumber === 'siplah');
            $wajibPajak = $t->total > 2000000 && ! $pakaiSiplah; // aturan ARKAS: belanja > Rp2 jt wajib pajaknya
          @endphp
          @forelse($t->taxes as $p)
            @php $lbl = ($p->rekeningPajak->kode ?? 'Pajak').' '.rp($p->nilai); @endphp
            @if ($p->status === 'sudah')
              <span class="badge bg-emerald-100 text-emerald-700" title="Sudah disetor & lapor (NTPN sebagai bukti pelaporan)">{{ $lbl }} — sudah lapor</span>
            @else
              <span class="badge bg-rose-100 text-rose-700 ring-1 ring-rose-300" title="Pajak Wajib Lapor — setor & lapor via menu Belanja → Setor Pajak, isi NTPN">{{ $lbl }} — wajib lapor</span>
            @endif
          @empty
            @if ($wajibPajak)
              <span class="badge bg-amber-100 text-amber-800" title="Di ARKAS: belanja di atas Rp2 jt (bukan SIPLah) wajib memperhitungkan pajak (PPN 12% sejak 2026)">≥ Rp2 jt — perlu pajak?</span>
            @elseif ($pakaiSiplah)
              <span class="text-emerald-600" title="Belanja SIPLah — pajak otomatis ditangani vendor">SIPLah</span>
            @else
              <span class="text-slate-300">—</span>
            @endif
          @endforelse
        </td>
        <td class="num font-semibold text-rose-700">{{ rp($t->total) }}</td>
        <td class="text-right whitespace-nowrap">
          <a href="{{ route('belanja.show', $t) }}" class="badge bdan">lihat</a>
          {{-- Hapus wajib beralasan: alasannya masuk Log Sistem (jejak audit). --}}
          <details class="relative inline-block text-left">
            <summary class="badge bdel cursor-pointer list-none" title="Hapus &amp; catat alasan"><x-icon name="trash" class="h-3.5 w-3.5"/></summary>
            <form method="post" action="{{ route('pengeluaran.destroy', $t) }}"
                  class="absolute right-0 z-40 mt-1 w-60 space-y-1.5 rounded border border-[#d9dbdd] bg-white p-2.5 text-xs shadow-[0_8px_20px_rgba(37,40,43,0.2)]">
              @csrf @method('DELETE')
              <div class="font-bold text-slate-700">Hapus belanja {{ rp($t->total) }}</div>
              <label class="block font-semibold text-slate-600">Alasan (wajib, tercatat di Log Sistem)</label>
              <input name="alasan" class="inp mt-1 text-xs" required minlength="5" maxlength="200"
                     placeholder="mis. salah input nominal">
              <button class="btn-danger btn btn-sm w-full justify-center">Hapus &amp; catat</button>
            </form>
          </details>
        </td>
      </tr>
      @empty
      <tr><td colspan="8" class="text-center text-slate-400 py-6">Belum ada belanja.</td></tr>
      @endforelse
    </tbody>
  </table>
  {{ $items->links() }}
</div>
@endsection
