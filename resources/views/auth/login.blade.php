@extends('layouts.guest', ['title' => 'Masuk'])
@section('content')
<div class="flex min-h-screen items-center justify-center bg-[radial-gradient(60rem_30rem_at_50%_-5rem,#312e81_0%,#0f172a_60%)] p-4">
  <div class="grid w-full max-w-3xl overflow-hidden rounded-3xl bg-white shadow-2xl md:grid-cols-2">
    {{-- Panel merek --}}
    <div class="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-indigo-700 via-indigo-600 to-sky-600 p-8 text-white md:flex">
      <div class="absolute -right-16 -top-16 h-56 w-56 rounded-full bg-white/10"></div>
      <div class="absolute -bottom-20 -left-10 h-64 w-64 rounded-full bg-sky-400/20"></div>
      <div class="relative">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 text-2xl">🎓</div>
        <h1 class="mt-4 text-2xl font-extrabold leading-tight">SiapBOSP</h1>
        <p class="mt-1 text-[13px] text-indigo-100">Sistem Akuntansi &amp; Pertanggungjawaban Dana BOSP · Multi Sekolah</p>
      </div>
      <ul class="relative space-y-2.5 text-[13px] text-indigo-50">
        <li class="flex gap-2"><span>📝</span> Perencanaan RKAS → Pembukuan BKU → LPJ</li>
        <li class="flex gap-2"><span>🏫</span> Satu aplikasi untuk semua sekolah, data terisolasi</li>
        <li class="flex gap-2"><span>🏛️</span> Panel Dinas: pantau serapan &amp; konsolidasi wilayah</li>
      </ul>
      <p class="relative text-[11px] text-indigo-200">Zona waktu WITA · Tahun anggaran {{ date('Y') }}</p>
    </div>

    {{-- Panel form --}}
    <div class="p-6 sm:p-8">
      <div class="mb-6 text-center md:text-left">
        <div class="text-3xl md:hidden">🎓</div>
        <h2 class="mt-1 text-xl font-bold text-slate-800">Masuk ke SiapBOSP</h2>
        <p class="text-xs text-slate-500">Gunakan akun operator, kepala sekolah, atau dinas.</p>
      </div>

      @if($errors->any())
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2.5 text-[13px] text-rose-700" role="alert">⛔ {{ $errors->first() }}</div>
      @endif

      <form method="post" action="/login" class="space-y-4">
        @csrf
        <div>
          <label class="lbl" for="email">Email</label>
          <input id="email" type="email" name="email" value="{{ old('email') }}" class="inp" placeholder="nama@sekolah.sch.id" required autofocus autocomplete="username">
        </div>
        <div>
          <label class="lbl" for="password">Kata Sandi</label>
          <div class="relative">
            <input id="password" type="password" name="password" class="inp pr-11" placeholder="••••••••" required autocomplete="current-password">
            <button type="button" id="toggle-pass" class="absolute inset-y-0 right-0 px-3 text-slate-400 hover:text-slate-600" aria-label="Tampilkan sandi">👁️</button>
          </div>
        </div>
        <button class="btn w-full !py-2.5">Masuk →</button>
      </form>

      <details class="mt-5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5">
        <summary class="cursor-pointer text-xs font-semibold text-slate-600">🔑 Akun demo (klik untuk isi otomatis)</summary>
        <div class="mt-2 grid gap-2 text-[12px]">
          <button type="button" class="btn2 btn-sm justify-start" data-login="operator@sekolah.demo">👩‍💼 Operator · operator@sekolah.demo</button>
          <button type="button" class="btn2 btn-sm justify-start" data-login="kepsek@sekolah.demo">👨‍🏫 Kepsek · kepsek@sekolah.demo</button>
          <button type="button" class="btn2 btn-sm justify-start" data-login="dinas@siapbosp.id">🏛️ Dinas · dinas@siapbosp.id</button>
          <p class="text-[11px] text-slate-400">Sandi demo: <code class="rounded bg-slate-200 px-1 font-mono">password</code> — ganti di produksi.</p>
        </div>
      </details>
    </div>
  </div>
</div>
@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(function(){
  const pass = document.getElementById('password');
  const tog = document.getElementById('toggle-pass');
  if (tog && pass) tog.addEventListener('click', () => {
    const show = pass.type === 'password';
    pass.type = show ? 'text' : 'password';
    tog.textContent = show ? '🙈' : '👁️';
    tog.setAttribute('aria-label', show ? 'Sembunyikan sandi' : 'Tampilkan sandi');
  });
  document.querySelectorAll('[data-login]').forEach(b => b.addEventListener('click', () => {
    document.getElementById('email').value = b.dataset.login;
    const p = document.getElementById('password');
    p.value = 'password';
    p.focus();
  }));
})();
</script>
@endpush
@endsection
