@extends('layouts.app', ['title' => 'Pagu / Alokasi Dana'])
@section('content')
<div class="grid lg:grid-cols-3 gap-5">
  <div class="card">
    <h3 class="font-semibold text-slate-700 mb-3">Set Pagu Sekolah</h3>
    <form method="post" action="{{ route('dinas.alokasi.store') }}" class="space-y-3">
      @csrf
      <div><label class="text-xs font-semibold text-slate-600">Sekolah</label>
        <select name="school_id" class="inp mt-1">@foreach($schools as $s)<option value="{{ $s->id }}">{{ $s->nama }}</option>@endforeach</select></div>
      <div><label class="text-xs font-semibold text-slate-600">Tahun</label><input type="number" name="tahun" class="inp mt-1" value="{{ $tahun }}"></div>
      <div><label class="text-xs font-semibold text-slate-600">Jenis BOS</label>
        <select name="bos_type_id" class="inp mt-1">@foreach($bosTypes as $b)<option value="{{ $b->id }}">{{ $b->nama }}</option>@endforeach</select></div>
      <div><label class="text-xs font-semibold text-slate-600">Tahap (opsional)</label>
        <select name="stage_id" class="inp mt-1"><option value="">— keseluruhan —</option>
          @foreach($stages as $st)<option value="{{ $st->id }}">{{ $st->nama }}</option>@endforeach</select></div>
      <div><label class="text-xs font-semibold text-slate-600">Nilai (Rp)</label><input type="number" name="nilai" class="inp mt-1" required></div>
      <button class="btn">💾 Simpan Pagu</button>
    </form>
  </div>
  <div class="card lg:col-span-2 overflow-x-auto">
    <h3 class="font-semibold text-slate-700 mb-3">Riwayat Pagu {{ $tahun }}</h3>
    <table class="tbl">
      <thead><tr><th>Sekolah</th><th>Jenis</th><th>Tahap</th><th class="num">Nilai</th><th>Tanggal Set</th></tr></thead>
      <tbody>@foreach($items as $a)<tr><td>{{ $a->school->nama }}</td><td>{{ $a->bosType->nama }}</td>
        <td>{{ $a->stage->nama ?? '—' }}</td><td class="num">{{ rp($a->nilai) }}</td><td class="text-xs">{{ $a->created_at->format('d/m/Y') }}</td></tr>@endforeach</tbody>
    </table>
    {{ $items->links() }}
  </div>
</div>
@endsection
