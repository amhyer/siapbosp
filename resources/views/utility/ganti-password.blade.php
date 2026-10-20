@extends('layouts.app', ['title' => 'Ganti Kata Sandi'])
@section('content')
<div class="card max-w-md">
  <h3 class="font-semibold text-slate-700 mb-1">🔐 Ganti Kata Sandi</h3>
  <p class="text-xs text-slate-500 mb-4">Minimal 6 karakter. Setelah diganti, kata sandi lama tidak berlaku lagi.</p>
  <form method="post" action="{{ route('password.update') }}" class="space-y-4">
    @csrf
    <div>
      <label class="text-xs font-semibold text-slate-600">Kata Sandi Saat Ini</label>
      <input type="password" name="password_lama" class="inp mt-1" required autocomplete="current-password">
    </div>
    <div>
      <label class="text-xs font-semibold text-slate-600">Kata Sandi Baru</label>
      <input type="password" name="password" class="inp mt-1" required minlength="6" autocomplete="new-password">
    </div>
    <div>
      <label class="text-xs font-semibold text-slate-600">Ulangi Kata Sandi Baru</label>
      <input type="password" name="password_confirmation" class="inp mt-1" required minlength="6" autocomplete="new-password">
    </div>
    <button class="btn">💾 Simpan Kata Sandi Baru</button>
  </form>
</div>
@endsection
