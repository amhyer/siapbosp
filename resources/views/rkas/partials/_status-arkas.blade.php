{{--
  Chip status ganda: status internal SiapBOSP + padanan kosakata ARKAS 4 (P0-1).
  Variabel: $h (RkasHeader). Opsional: $ringkas (bool, default false).
--}}
@php
  $nada = [
    'slate' => 'bg-slate-100 text-slate-600',
    'sky' => 'bg-sky-100 text-sky-700',
    'indigo' => 'bg-indigo-100 text-indigo-700',
    'rose' => 'bg-rose-100 text-rose-700',
    'emerald' => 'bg-emerald-100 text-emerald-700',
  ];
  $internal = \App\Models\RkasHeader::LABEL_STATUS[$h->status] ?? $h->status;
  $arkas = $h->labelStatusArkas();
  $kelasArkas = $nada[$h->nadaStatusArkas()] ?? 'bg-slate-100 text-slate-600';
  $ringkas = $ringkas ?? false;
@endphp
<span class="inline-flex flex-wrap items-center gap-1">
  <span class="badge bg-white border border-slate-300 text-slate-700" title="Status internal SiapBOSP">{{ $internal }}</span>
  @if($h->statusArkasBerbeda())
    <span class="text-slate-300 text-[10px]" aria-hidden="true">&rarr;</span>
  @endif
  <span class="badge {{ $kelasArkas }}" title="Padanan status ARKAS 4: {{ $h->catatanStatusArkas() }}">{{ $arkas }}</span>
  @unless($ringkas)
    <span class="text-[10px] text-slate-400">status ARKAS 4</span>
  @endunless
</span>
