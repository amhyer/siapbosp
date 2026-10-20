@extends('layouts.app', ['title' => 'Log Aktivitas Sekolah'])
@section('content')
@php
  $map = [
    'transaksi'  => ['💸', 'bg-indigo-100 text-indigo-700'],
    'rkas'       => ['📝', 'bg-violet-100 text-violet-700'],
    'tutup_buku' => ['🔒', 'bg-rose-100 text-rose-700'],
    'buka_buku'  => ['🔓', 'bg-amber-100 text-amber-700'],
    'pengumuman' => ['📣', 'bg-sky-100 text-sky-700'],
    'tahap'      => ['🗓️', 'bg-teal-100 text-teal-700'],
    'lpj'        => ['📨', 'bg-indigo-100 text-indigo-700'],
    'backup'     => ['💾', 'bg-cyan-100 text-cyan-700'],
  ];
@endphp
<div class="card">
  <form class="flex gap-2 items-center text-sm mb-4">
    <span>Sekolah:</span>
    <select name="school" class="inp !w-auto" onchange="this.form.submit()">
      <option value="">— Semua —</option>
      @foreach($schools as $s)<option value="{{ $s->id }}" @selected((string)$s->id === request('school'))>{{ $s->nama }}</option>@endforeach
    </select>
    <span class="text-xs text-slate-400">{{ $logs->total() }} catatan</span>
  </form>
  <div class="overflow-x-auto">
  <table class="tbl">
    <thead><tr><th>Waktu</th><th>Sekolah</th><th>Pengguna</th><th>Aksi</th><th>Keterangan</th></tr></thead>
    <tbody>
      @forelse($logs as $l)
      @php [$ikon, $kelas] = $map[$l->aksi] ?? ['📌', 'bg-slate-100 text-slate-600']; @endphp
      <tr>
        <td class="whitespace-nowrap text-xs">{{ $l->created_at->format('d/m/Y H:i') }}</td>
        <td class="text-xs">{{ $l->school->nama ?? '— Dinas —' }}</td>
        <td class="text-xs">{{ $l->user_name }}</td>
        <td><span class="badge {{ $kelas }}">{{ $ikon }} {{ str_replace('_', ' ', $l->aksi) }}</span></td>
        <td class="text-xs">{{ $l->keterangan }}</td>
      </tr>
      @empty
      <tr><td colspan="5" class="text-center text-slate-400 py-6">Belum ada aktivitas tercatat.</td></tr>
      @endforelse
    </tbody>
  </table>
  </div>
  {{ $logs->links() }}
</div>
@endsection
