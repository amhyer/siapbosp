@extends('layouts.app', ['title' => 'Detail RKAS'])
@section('content')
@if(!empty($review))
<div class="mb-4 flex gap-2 items-center flex-wrap bg-amber-50 border border-amber-300 rounded-lg p-3">
  <span class="text-sm font-semibold text-amber-800">🔍 MODE TINJAUAN DINAS — RKAS {{ $review->school->nama ?? '' }} · TA {{ $review->tahun }} revisi ke-{{ $review->revisi_ke }} (status: {{ strtoupper($review->status) }})</span>
  @if($review->status === 'dikirim_dinas')
  <form method="post" action="{{ route('dinas.rkas.sahkan', $review->id) }}" onsubmit="return confirm('Sahkan RKAS ini? Sekolah akan dinotifikasi.')">@csrf
    <button class="border rounded px-3 py-1 text-sm bg-emerald-600 text-white font-semibold">✅ Sahkan</button>
  </form>
  <details class="relative">
    <summary class="border rounded px-3 py-1 text-sm bg-rose-600 text-white font-semibold cursor-pointer list-none">🟠 Minta Revisi</summary>
    <form method="post" action="{{ route('dinas.rkas.revisi', $review->id) }}" class="absolute left-0 mt-2 w-72 bg-white border border-slate-200 rounded-xl shadow-xl p-3 z-50">
      @csrf
      <label class="text-xs font-semibold text-slate-600">Catatan revisi untuk sekolah (wajib)</label>
      <textarea name="catatan_dinas" rows="3" class="border rounded w-full px-2 py-1 mt-1 text-xs" required minlength="5"></textarea>
      <button class="border rounded px-3 py-1 text-xs bg-rose-600 text-white font-semibold mt-2 w-full">Kirim Permintaan Revisi</button>
    </form>
  </details>
  @endif
  <a href="{{ route('dinas.rkas.pdf', $review->id) }}" class="border rounded px-3 py-1 text-sm bg-rose-50 border-rose-300">📕 PDF</a>
  <a href="{{ route('dinas.rkas.xlsx', $review->id) }}" class="border rounded px-3 py-1 text-sm bg-green-50 border-green-300">📗 Excel</a>
  <a href="{{ route('dinas.rkas') }}" class="border rounded px-3 py-1 text-sm">← Kembali ke RKAS Masuk</a>
</div>
@endif
<div class="card flex justify-between items-start">
  <div>
    <div class="text-xs text-slate-500">RKAS Tahun {{ $h->tahun }} · Revisi ke-{{ $h->revisi_ke }}</div>
    <div class="text-lg font-bold">{{ $h->no_rkas ?? '(belum ada nomor)' }}</div>
    <div class="text-sm text-slate-500 mt-1">{{ $h->catatan }}</div>
    @php $mapB = ['draft'=>'bg-slate-100 text-slate-600','diajukan'=>'bg-amber-100 text-amber-700','disetujui'=>'bg-sky-100 text-sky-700','dikirim_dinas'=>'bg-indigo-100 text-indigo-700','revisi'=>'bg-rose-100 text-rose-700','disahkan'=>'bg-emerald-100 text-emerald-700','dikunci'=>'bg-slate-200 text-slate-600']; @endphp
    <span class="badge {{ $mapB[$h->status] ?? 'bg-slate-100' }} mt-2">{{ \App\Models\RkasHeader::LABEL_STATUS[$h->status] ?? $h->status }}</span>
    @if($h->status==='dikirim_dinas')<div class="text-[11px] text-indigo-500 mt-1">dikirim {{ $h->dikirim_dinas_at?->format('d/m/Y H:i') }} — menunggu tinjauan Dinas</div>@endif
    @if($h->status==='disahkan')<div class="text-[11px] text-emerald-600 mt-1">🎉 disahkan oleh {{ $h->ditinjau_oleh }} ({{ $h->ditinjau_at?->format('d/m/Y') }})</div>@endif
    @if($h->status==='revisi')<div class="mt-1 text-[11px] bg-rose-50 border border-rose-200 text-rose-700 rounded px-2 py-1">🟠 Catatan Dinas ({{ $h->ditinjau_oleh }}): <b>{{ $h->catatan_dinas }}</b> — susun RKAS revisi baru, lalu kirim ulang ke Dinas.</div>@endif
  </div>
  @if(empty($review))
  <form method="post" action="{{ route('rkas.aksi', $h) }}" class="flex gap-2">
    @csrf
    @if($h->status==='draft')<button name="aksi" value="ajukan" class="btn">📤 Ajukan ke Kepsek</button>@endif
    @if($h->status==='diajukan' && auth()->user()->role==='kepsek')<button name="aksi" value="setujui" class="btn">✅ Setujui (Kepsek)</button>@endif
    @if(in_array($h->status, ['disetujui', 'revisi']))<button name="aksi" value="kirim_dinas" class="btn bg-indigo-600" onclick="return confirm('Kirim RKAS ini ke Dinas untuk disahkan?')">{{ $h->status === 'revisi' ? '🔄 Kirim Ulang ke Dinas' : '📨 Kirim ke Dinas' }}</button>@endif
    @if($h->status==='disahkan')<button name="aksi" value="kunci" class="btn2 btn">🔒 Kunci RKAS</button>@endif
    <a href="{{ route('rkas.pdf', $h) }}" class="btn2 btn bg-rose-50 border-rose-300">📕 PDF</a>
    <a href="{{ route('rkas.xlsx', $h) }}" class="btn2 btn bg-green-50 border-green-300">📗 Excel</a>
    <a href="{{ route('rkas.index') }}" class="btn2 btn">← Kembali</a>
  </form>
  @endif
</div>
@include('rkas._juknis', ['juknis' => $juknis])
<div class="card overflow-x-auto">
  <table class="tbl">
    <thead><tr><th>Kode</th><th>Kegiatan</th><th>Rekening</th><th>Uraian</th><th>Satuan</th><th class="num">Banyak</th><th class="num">Harga</th><th class="num">Jumlah</th><th>Sumber</th></tr></thead>
    <tbody>
      @foreach($h->details as $d)
      <tr><td class="font-mono">{{ $d->rekening->kode }}</td>
        <td class="font-mono text-[11px] text-slate-500">{{ $d->kode_kegiatan ? \App\Services\JuknisService::normalisasi($d->kode_kegiatan) : '—' }}</td>
        <td>{{ $d->rekening->nama }}</td><td>{{ $d->uraian }}</td>
        <td>{{ $d->satuan }}</td><td class="num">{{ $d->banyak }}</td><td class="num">{{ rp($d->harga_satuan) }}</td>
        <td class="num font-semibold">{{ rp($d->jumlah) }}</td><td>{{ $d->sumber_dana }}</td></tr>
      @endforeach
    </tbody>
    <tfoot><tr class="font-bold"><td colspan="7" class="text-right">TOTAL</td><td class="num">{{ rp($h->details->sum('jumlah')) }}</td><td></td></tr></tfoot>
  </table>
</div>
@endsection
