{{--
  Tab sumber dana ala ARKAS 4 (BOSP Reguler · Daerah · Kinerja · SiLPA
  Kinerja · Lainnya · Sumber Dana Tambahan) — bentuknya sama di setiap
  halaman, jadi ditulis sekali di sini.

  Tab hanya dipakai halaman yang MEMANG menyaring per sumber dana; jangan
  dipasang di halaman yang tidak punya penyaringan, agar tidak ada tab mati.

  Parameter:
    $rute    (string) URL dasar, mis. route('rkas.index')
    $aktif   (string) kunci tab yang sedang dipilih ('' = semua)
    $tabs    (array)  peta kunci => ['label'=>...]; bawaan RkasHeader
    $param   (string) nama parameter query (bawaan 'tab')
    $query   (array)  parameter lain yang harus dipertahankan
    $semua   (string|null) bila diisi, tampilkan tab "Semua" berlabel ini
--}}
@php
  $tabs = $tabs ?? \App\Models\RkasHeader::TAB_SUMBER_DANA;
  $aktif = $aktif ?? '';
  $param = $param ?? 'tab';
  $query = $query ?? [];
  $semua = $semua ?? null;
  $tautan = function (string $kunci) use ($rute, $param, $query) {
      return $rute.'?'.http_build_query(array_merge($query, [$param => $kunci]));
  };
@endphp
<div class="bg-white rounded-t-2xl border-b border-slate-200 px-5 pt-2">
  <div class="flex flex-wrap items-center gap-2" role="tablist" aria-label="Sumber dana">
    @if($semua !== null)
      <a href="{{ $tautan('') }}"
         role="tab" aria-selected="{{ $aktif === '' ? 'true' : 'false' }}"
         class="border-b-[3px] px-3.5 py-3 text-[14px] font-semibold no-underline transition
                {{ $aktif === ''
                    ? 'border-indigo-600 text-indigo-600'
                    : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900' }}">
        {{ $semua }}
      </a>
    @endif
    @foreach($tabs as $kunci => $tab)
      <a href="{{ $tautan($kunci) }}"
         role="tab" aria-selected="{{ $aktif === $kunci ? 'true' : 'false' }}"
         class="border-b-[3px] px-3.5 py-3 text-[14px] font-semibold no-underline transition
                {{ $aktif === $kunci
                    ? 'border-indigo-600 text-indigo-600'
                    : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900' }}">
        {{ $tab['label'] }}
      </a>
    @endforeach
  </div>
</div>
