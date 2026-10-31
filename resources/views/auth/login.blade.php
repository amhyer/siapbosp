@extends('layouts.guest', ['title' => 'Masuk'])
@section('content')
<div class="flex min-h-screen items-center justify-center bg-[#eef1f6] p-4">
  <div class="grid w-full max-w-3xl overflow-hidden rounded border border-[#d9dbdd] bg-white shadow-[0_10px_30px_rgba(37,40,43,0.12)] md:grid-cols-2">
    {{-- Panel merek — biru ARKAS, tanpa gradien. --}}
    <div class="relative hidden flex-col justify-between bg-[#1e3a8a] p-8 text-white md:flex">
      <div class="relative">
        <div class="flex h-11 w-11 items-center justify-center rounded bg-white/10">
          <x-icon name="book" class="h-6 w-6"/>
        </div>
        <h1 class="mt-4 text-2xl font-bold leading-tight">SiapBOSP</h1>
        <p class="mt-1 text-[13px] text-blue-100">Sistem Akuntansi &amp; Pertanggungjawaban Dana BOSP · Multi Sekolah</p>
      </div>
      <ul class="relative space-y-3 text-[13px] text-blue-50">
        <li class="flex items-center gap-2"><x-icon name="file" class="h-4 w-4 shrink-0"/><span>Perencanaan RKAS → Pembukuan BKU → LPJ</span></li>
        <li class="flex items-center gap-2"><x-icon name="bank" class="h-4 w-4 shrink-0"/><span>Satu aplikasi untuk semua sekolah, data terisolasi</span></li>
        <li class="flex items-center gap-2"><x-icon name="chart" class="h-4 w-4 shrink-0"/><span>Panel Dinas: pantau serapan &amp; konsolidasi wilayah</span></li>
      </ul>
      <p class="relative text-[11px] text-blue-200">Zona waktu WITA · Tahun anggaran {{ date('Y') }}</p>
    </div>

    {{-- Panel form --}}
    <div class="p-6 sm:p-8">
      <div class="mb-6 text-center md:text-left">
        <div class="mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded bg-[#1e3a8a] text-white md:hidden">
          <x-icon name="book" class="h-6 w-6"/>
        </div>
        <h2 class="text-xl font-bold text-[#2f3031]">Masuk ke SiapBOSP</h2>
        <p class="mt-1 text-xs text-[#65696c]">Gunakan akun operator, kepala sekolah, atau dinas.</p>
      </div>

      @if($errors->any())
        <div class="mb-4 flex items-start gap-2 rounded border border-rose-200 bg-rose-50 px-3 py-2.5 text-[13px] text-rose-700" role="alert">
          <x-icon name="x-circle" class="h-4 w-4 shrink-0"/> <span>{{ $errors->first() }}</span>
        </div>
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
            <button type="button" id="toggle-pass" class="absolute inset-y-0 right-0 flex items-center px-3 text-[#808489] hover:text-[#2f3031]" aria-label="Tampilkan sandi">
              <x-icon name="eye" class="icon-eye h-4 w-4"/>
              <x-icon name="eye-off" class="icon-eye-off hidden h-4 w-4"/>
            </button>
          </div>
        </div>
        <button class="btn w-full justify-center !py-2.5">Masuk</button>
      </form>

      <details class="mt-5 rounded border border-[#d9dbdd] bg-[#fafafa] px-3 py-2.5">
        <summary class="cursor-pointer text-xs font-semibold text-[#65696c]">Akun demo (klik untuk isi otomatis)</summary>
        <div class="mt-2 grid gap-2 text-[12px]">
          <button type="button" class="btn2 btn-sm justify-start" data-login="operator@sekolah.demo"><x-icon name="user" class="h-4 w-4"/> Operator · operator@sekolah.demo</button>
          <button type="button" class="btn2 btn-sm justify-start" data-login="kepsek@sekolah.demo"><x-icon name="user" class="h-4 w-4"/> Kepsek · kepsek@sekolah.demo</button>
          <button type="button" class="btn2 btn-sm justify-start" data-login="dinas@siapbosp.id"><x-icon name="bank" class="h-4 w-4"/> Dinas · dinas@siapbosp.id</button>
          <p class="text-[11px] text-[#808489]">Sandi demo: <code class="rounded bg-slate-200 px-1 font-mono">password</code> — ganti di produksi.</p>
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
    tog.querySelector('.icon-eye')?.classList.toggle('hidden', show);
    tog.querySelector('.icon-eye-off')?.classList.toggle('hidden', !show);
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
