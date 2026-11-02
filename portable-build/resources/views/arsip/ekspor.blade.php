@extends('layouts.app', ['title' => 'Arsip — Riwayat Ekspor'])
@section('subtitle', 'Jejak sinkronisasi ke pusat & pencadangan data')
@section('content')
<div class="card">
  <h3 class="card-title mb-3 flex items-center gap-2"><x-icon name="download" class="h-4 w-4 text-slate-500"/> Riwayat Ekspor &amp; Sinkronisasi</h3>
  @if($sync->isNotEmpty())
    <div class="mb-3 text-[12px] text-slate-600">
      @foreach($sync as $s)
        <div>• <b>{{ $s->kunci }}</b>: {{ $s->nilai }} <span class="text-slate-400">(perbarui {{ $s->updated_at ?? '—' }})</span></div>
      @endforeach
    </div>
  @endif
  <div class="overflow-x-auto">
    <table class="tbl">
      <thead><tr><th>Waktu</th><th>Oleh</th><th>Keterangan</th></tr></thead>
      <tbody>
        @forelse($rows as $r)
          <tr>
            <td class="text-[11px] text-slate-500">{{ $r->created_at?->format('d/m/Y H:i') }}</td>
            <td>{{ $r->user_name }}</td>
            <td>{{ $r->keterangan }}</td>
          </tr>
        @empty
          <tr><td colspan="3" class="text-center text-slate-400 py-6">Belum ada riwayat ekspor/sinkronisasi.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3 flex gap-2">
    <a href="{{ route('pengaturan.sinkronisasi') }}" class="btn2 btn-sm"><x-icon name="refresh" class="h-4 w-4"/> Status Sinkronisasi</a>
    <a href="{{ route('pengaturan.backup') }}" class="btn2 btn-sm"><x-icon name="download" class="h-4 w-4"/> Unduh Backup</a>
  </div>
</div>
@endsection
