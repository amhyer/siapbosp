@extends('layouts.app', ['title' => 'Kontrol Tahap Penyaluran'])
@section('content')
<div class="flex items-center justify-between flex-wrap gap-2">
  <p class="text-sm text-slate-500">Buka/tutup tahap penyaluran tahun <b>{{ $tahun }}</b>; tahap yang ditutup <b>tidak bisa dipilih</b> sekolah saat mencatat pendapatan/belanja.</p>
  <form method="post" action="{{ route('dinas.tahap.buat') }}" class="flex items-end gap-2" onsubmit="return confirm('Buat paket Tahap 1 s/d 5 untuk tahun yang dipilih?')">
    @csrf
    <div>
      <label class="text-xs font-semibold text-slate-500">Buat paket tahap baru</label>
      <select name="tahun" class="inp mt-1 !w-auto">
        @for($y = now()->year - 1; $y <= now()->year + 2; $y++) <option value="{{ $y }}" @selected((int) session('tahun', now()->year) === $y)>{{ $y }}</option> @endfor
      </select>
    </div>
    <button class="btn text-xs">🗓️ Buat Paket Tahun</button>
  </form>
</div>
@if($stages->isEmpty())
  <div class="card border border-amber-200 bg-amber-50 text-sm text-amber-700 mb-4">
    ⚠️ Tahun <b>{{ $tahun }}</b> belum memiliki tahap. Gunakan formulir di atas untuk membuat paket Tahap 1 s/d 5 — setelah itu sekolah bisa mulai mencatat pada tahun tersebut.
  </div>
@endif
<div class="card overflow-x-auto max-w-2xl">
  <table class="tbl">
    <thead><tr><th>Tahun</th><th>Tahap</th><th>Urutan</th><th>Status</th><th></th></tr></thead>
    <tbody>
      @foreach($stages as $s)
      <tr>
        <td>{{ $s->tahun }}</td><td class="font-semibold">{{ $s->nama }}</td><td>#{{ $s->urutan }}</td>
        <td>{!! $s->is_open ? '<span class="badge bg-emerald-100 text-emerald-700">🟢 BUKA</span>' : '<span class="badge bg-red-100 text-red-700">🔴 TUTUP</span>' !!}</td>
        <td class="text-right">
          <form method="post" action="{{ route('dinas.tahap.toggle', $s->id) }}">@csrf
            <button class="btn2 btn text-xs">{{ $s->is_open ? '🔒 Tutup Tahap' : '🔓 Buka Tahap' }}</button>
          </form>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
