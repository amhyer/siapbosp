@extends('layouts.app', ['title' => 'Pusat Keamanan'])
@section('content')
<p class="text-sm text-slate-500">
  Riwayat percobaan masuk ke SiapBOSP. Akun otomatis <b>diblokir 5 menit</b> setelah <b>5 kali</b> kata sandi salah beruntun (per email + alamat IP).
  Periksa bila ada pola gagal yang mencurigakan.
</p>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
  <div class="card"><div class="text-xs text-slate-500">Login Berhasil (24 j)</div><div class="text-2xl font-bold text-emerald-600">{{ $ok24 }}</div></div>
  <div class="card"><div class="text-xs text-slate-500">Percobaan Gagal/Blokir (24 j)</div><div class="text-2xl font-bold {{ $gagal24 > 10 ? 'text-red-600' : 'text-amber-600' }}">{{ $gagal24 }}</div></div>
  <div class="card"><div class="text-xs text-slate-500">Email Unik Aktif (24 j)</div><div class="text-2xl font-bold text-indigo-600">{{ $unik24 }}</div></div>
  <div class="card">
    <form method="get" class="text-xs space-y-1">
      <label class="font-semibold text-slate-500">Saring email</label>
      <input name="email" value="{{ $email }}" class="inp" placeholder="nama@…">
      <button class="btn text-xs">🔍 Saring</button>
    </form>
  </div>
</div>

<div class="card overflow-x-auto">
  <table class="tbl">
    <thead><tr><th>Waktu</th><th>Akun / Email</th><th>Peristiwa</th><th>Keterangan</th><th>Sekolah</th></tr></thead>
    <tbody>
      @forelse($riwayat as $i)
      <tr>
        <td class="whitespace-nowrap text-xs text-slate-500">{{ $i->created_at->format('d/m/Y H:i:s') }}</td>
        <td class="font-medium">{{ $i->user_name }}</td>
        <td>
          @if($i->aksi === 'auth-ok')
            <span class="badge" style="background:#dcfce7;color:#166534">✅ MASUK</span>
          @elseif($i->aksi === 'auth-block')
            <span class="badge" style="background:#fee2e2;color:#991b1b">🚫 BLOKIR</span>
          @else
            <span class="badge" style="background:#fef3c7;color:#92400e">⚠️ GAGAL</span>
          @endif
        </td>
        <td class="text-xs text-slate-600">{{ $i->keterangan }}</td>
        <td class="text-xs">{{ $i->school?->nama ?? '—' }}</td>
      </tr>
      @empty
      <tr><td colspan="5" class="text-center text-slate-400 py-6">Belum ada catatan autentikasi.</td></tr>
      @endforelse
    </tbody>
  </table>
  <div class="mt-3">{{ $riwayat->links() }}</div>
</div>
@endsection
