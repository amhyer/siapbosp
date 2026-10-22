@extends('layouts.app', ['title' => 'Arsip — Riwayat Impor'])
@section('subtitle', 'Jejak setiap impor data ARKAS ke aplikasi')
@section('content')
<div class="card">
  <h3 class="card-title mb-3">📥 Riwayat Impor Data ARKAS</h3>
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
          <tr><td colspan="3" class="text-center text-slate-400 py-6">Belum ada riwayat impor.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3 flex gap-2">
    <a href="{{ route('impor-arkas.form') }}" class="btn2 btn-sm">⬆️ Impor ARKAS</a>
    <a href="{{ route('impor-arkas.cek-integritas') }}" class="btn2 btn-sm">🔍 Cek Integritas</a>
  </div>
</div>
@endsection
