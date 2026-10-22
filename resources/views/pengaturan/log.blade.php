@extends('layouts.app', ['title' => 'Pengaturan — Log Sistem'])
@section('subtitle', 'Jejak audit aktivitas di aplikasi ini')
@section('content')
<div class="card">
  <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
    <h3 class="card-title mb-0">📋 Log Sistem</h3>
    <form method="get" class="flex gap-2 items-end">
      <div><label class="lbl">Jenis aksi</label>
        <select name="aksi" class="inp !w-auto" onchange="this.form.submit()">
          <option value="">Semua aksi</option>
          @foreach($daftarAksi as $a)<option value="{{ $a }}" @selected($aksi === $a)>{{ $a }}</option>@endforeach
        </select>
      </div>
    </form>
  </div>
  <div class="overflow-x-auto">
    <table class="tbl">
      <thead><tr><th>Waktu</th><th>Aksi</th><th>Oleh</th><th>Keterangan</th></tr></thead>
      <tbody>
        @forelse($rows as $r)
          <tr>
            <td class="text-[11px] text-slate-500">{{ $r->created_at?->format('d/m/Y H:i') }}</td>
            <td><span class="badge bg-slate-100 text-slate-700">{{ $r->aksi }}</span></td>
            <td>{{ $r->user_name }}</td>
            <td class="text-[12px]">{{ $r->keterangan }}</td>
          </tr>
        @empty
          <tr><td colspan="4" class="text-center text-slate-400 py-6">Belum ada aktivitas tercatat.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <p class="text-[11px] text-slate-400 mt-3">Maks 300 baris terakhir. Untuk audit seluruh sekolah, Dinas memiliki <b>Log Aktivitas</b> lintas sekolah.</p>
</div>
@endsection
