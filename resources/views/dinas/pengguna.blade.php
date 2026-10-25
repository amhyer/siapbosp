@extends('layouts.app', ['title' => 'Pengguna'])
@section('content')
<div class="grid lg:grid-cols-3 gap-5">
  <div class="card">
    <h3 class="font-semibold text-slate-700 mb-3">Buat Akun Pengguna</h3>
    <form method="post" action="{{ route('dinas.pengguna.store') }}" class="space-y-3">
      @csrf
      <div><label class="text-xs font-semibold text-slate-600">Nama</label><input name="name" class="inp mt-1" required></div>
      <div><label class="text-xs font-semibold text-slate-600">Email</label><input type="email" name="email" class="inp mt-1" required></div>
      <div><label class="text-xs font-semibold text-slate-600">Kata Sandi</label>
        <input name="password" class="inp mt-1" type="text" minlength="10" required autocomplete="off"
               placeholder="min. 10 karakter, ada huruf &amp; angka">
        <div class="text-[11px] text-slate-400 mt-1">Jangan pakai kata umum. Pemilik akun wajib menggantinya saat login pertama.</div>
      </div>
      <div><label class="text-xs font-semibold text-slate-600">Peran</label>
        <select name="role" class="inp mt-1"><option value="operator">Operator Sekolah</option><option value="kepsek">Kepala Sekolah</option>@if(auth()->user()->role === 'superadmin')<option value="dinas">Admin Dinas</option>@endif</select></div>
      <div><label class="text-xs font-semibold text-slate-600">Sekolah (untuk Operator/Kepsek)</label>
        <select name="school_id" class="inp mt-1"><option value="">— dinas —</option>
          @foreach($schools as $s)<option value="{{ $s->id }}">{{ $s->nama }}</option>@endforeach</select></div>
      <button class="btn">➕ Buat Akun</button>
    </form>
  </div>
  <div class="card">
    <h3 class="font-semibold text-slate-700 mb-1">📥 Impor Massal (CSV)</h3>
    <p class="text-xs text-slate-500 mb-2">Format baris: <code class="bg-slate-100 px-1 rounded">nama;email;npsn;role;password</code> — role &amp; password opsional.
      Password kosong/lemah akan diganti <b>sandi acak kuat</b> yang ditampilkan sekali setelah impor, dan semua akun hasil impor wajib ganti sandi saat login. NPSN wajib untuk operator/kepsek.
      @if(auth()->user()->role !== 'superadmin')Akun <b>Admin Dinas</b> hanya dapat dibuat superadmin.@endif</p>
    <form method="post" action="{{ route('dinas.pengguna.impor') }}" enctype="multipart/form-data" class="space-y-3">
      @csrf
      <input type="file" name="file" accept=".csv,.txt" class="inp mt-1 text-xs" required>
      <button class="btn">⬆️ Impor</button>
    </form>
  </div>
  <div class="card">
    <h3 class="font-semibold text-slate-700 mb-1">🔑 Token API Dinas</h3>
    <p class="text-xs text-slate-500 mb-2">Untuk integrasi eksternal membaca <code class="bg-slate-100 px-1 rounded">/api/v1/dinas/sekolah</code> &amp; <code class="bg-slate-100 px-1 rounded">/api/v1/dinas/konsolidasi</code>.</p>
    @if(session('token_baru'))
      <div class="bg-slate-900 text-emerald-300 font-mono text-xs rounded p-2 break-all select-all mb-2">{{ session('token_baru') }}</div>
      <div class="text-[11px] font-semibold text-amber-600 mb-2">⚠️ Salin sekarang — token hanya ditampilkan sekali ini dan disimpan ter-hash.</div>
    @elseif(auth()->user()->api_token)
      <div class="text-sm text-slate-600 mb-2">✅ Token aktif — nilai aslinya tersimpan ter-hash dan tidak dapat ditampilkan lagi.</div>
    @endif
    <form method="post" action="{{ route('api-token.store') }}">
      @csrf
      <button class="btn">♻️ {{ auth()->user()->api_token ? 'Buat Ulang' : 'Buat Token' }}</button>
    </form>
  </div>
  <div class="card lg:col-span-3 overflow-x-auto">
    <table class="tbl">
      <thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Sekolah</th><th>Status</th><th>Kelola</th></tr></thead>
      <tbody>
      @forelse($users as $usr)
        <tr class="{{ $usr->is_active ? '' : 'opacity-60' }}">
          <td class="font-medium">{{ $usr->name }}</td>
          <td>{{ $usr->email }}</td>
          <td><span class="badge bdan">{{ $usr->role }}</span></td>
          <td>{{ $usr->school->nama ?? 'Dinas' }}</td>
          <td>
            @if($usr->is_active)
              <span class="badge" style="background:#dcfce7;color:#166534">AKTIF</span>
            @else
              <span class="badge" style="background:#fee2e2;color:#991b1b">NONAKTIF</span>
            @endif
          </td>
          <td class="normal-case">
            @php($bolehKelola = \App\Support\Peran::bolehKelola(auth()->user(), $usr))
            <details class="text-xs">
              <summary class="cursor-pointer text-indigo-600 font-semibold select-none">✏️ Kelola</summary>
              <div class="mt-2 space-y-3 min-w-72 bg-slate-50 rounded p-3">
              @unless($bolehKelola)
                <div class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded p-2">
                  🔒 Akun <b>{{ $usr->role }}</b> hanya dapat dikelola <b>superadmin</b>.
                </div>
              @endunless
              @if($bolehKelola)
                <form method="post" action="{{ route('dinas.pengguna.update', $usr) }}" class="grid gap-1">
                  @csrf @method('PUT')
                  <div class="text-[10px] font-bold text-slate-500 uppercase">Ubah identitas</div>
                  <input name="name" class="inp" value="{{ $usr->name }}" placeholder="Nama" required>
                  <input type="email" name="email" class="inp" value="{{ $usr->email }}" placeholder="Email" required>
                  <select name="role" class="inp" @disabled($usr->id === auth()->id())>
                    @foreach(['operator' => 'Operator Sekolah', 'kepsek' => 'Kepala Sekolah'] + (auth()->user()->role === 'superadmin' ? ['dinas' => 'Admin Dinas'] : []) as $rv => $rl)
                      <option value="{{ $rv }}" @selected($usr->role === $rv)>{{ $rl }}</option>
                    @endforeach
                  </select>
                  <select name="school_id" class="inp">
                    <option value="">— dinas —</option>
                    @foreach($schools as $s)<option value="{{ $s->id }}" @selected($usr->school_id === $s->id)>{{ $s->nama }}</option>@endforeach
                  </select>
                  <button class="btn text-xs">💾 Simpan Perubahan</button>
                </form>
                <form method="post" action="{{ route('dinas.pengguna.reset', $usr) }}" class="grid gap-1">
                  @csrf
                  <div class="text-[10px] font-bold text-slate-500 uppercase">Reset sandi (kosongkan = acak)</div>
                  <div class="flex gap-1">
                    <input name="password" class="inp flex-1" placeholder="Sandi baru (min. 10)">
                    <button class="btn text-xs">🔁 Reset</button>
                  </div>
                </form>
                <div class="flex items-center justify-between gap-2">
                  @if($usr->id !== auth()->id())
                  <form method="post" action="{{ route('dinas.pengguna.toggle', $usr) }}">
                    @csrf
                    <button class="btn2 btn text-xs whitespace-nowrap">{{ $usr->is_active ? '🔇 Nonaktifkan' : '🔊 Aktifkan' }}</button>
                  </form>
                  <form method="post" action="{{ route('dinas.pengguna.hapus', $usr) }}" onsubmit="return confirm('Hapus akun {{ $usr->email }}?')">
                    @csrf @method('DELETE')
                    <button class="badge bdel">🗑 Hapus</button>
                  </form>
                  @endif
                </div>
              @endif
              </div>
            </details>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-center text-slate-400 py-6">Belum ada pengguna.</td></tr>
      @endforelse
      </tbody>
    </table>
    {{ $users->links() }}
  </div>
</div>
@endsection
