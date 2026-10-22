@extends('layouts.app', ['title' => 'Review & Proporsi Anggaran'])
@section('subtitle', 'Kepatuhan Juknis BOSP 2026 (Permendikdasmen No 8/2026 Pasal 42) per kertas kerja')
@section('content')
<div class="card">
  <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
    <h3 class="card-title mb-0">📊 Review & Proporsi Anggaran — TA {{ $tahun }}</h3>
    <a href="{{ route('rkas.index') }}" class="btn2 btn-sm">📝 Kertas Kerja (RKAS)</a>
  </div>

  @if(empty($baris))
    <p class="text-center text-slate-400 py-8">Belum ada kertas kerja untuk tahun {{ $tahun }}.</p>
  @else
    <div class="space-y-3">
      @foreach($baris as $b)
        @php
          $h = $b['header'];
        @endphp
        <div class="border border-slate-200 rounded-lg p-3">
          <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
            <div>
              <div class="font-semibold text-slate-800">Revisi ke-{{ $h->revisi_ke }} · {{ $h->no_rkas ?? '(tanpa nomor)' }}</div>
              <div class="text-[11px] text-slate-500">
                {{ $h->labelJenis() }} · {{ $h->sumber_dana ?? 'BOS Reguler' }} · {{ $h->details->count() }} rincian · total {{ rp($b['total']) }}
                @if($b['pagu'] > 0) · pagu {{ rp($b['pagu']) }} @endif
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span class="badge {{ $h->status === 'disahkan' || $h->status === 'dikunci' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ \App\Models\RkasHeader::LABEL_STATUS[$h->status] ?? $h->status }}</span>
              <a href="{{ route('rkas.show', $h) }}" class="btn2 btn-sm">Buka Review →</a>
            </div>
          </div>

          @if(! empty($b['proporsi']))
            <div class="grid sm:grid-cols-3 gap-2">
              @foreach($b['proporsi'] as $key => $p)
                @php
                  $label = $p['label'] ?? $key;
                  $nilai = $p['nilai'] ?? $p['nominal'] ?? null;
                  $persen = $p['persen'] ?? $p['pct'] ?? null;
                  $batasLabel = $p['batas_label'] ?? null;
                  $patuh = $p['ok'] ?? $p['patuh'] ?? null;
                @endphp
                <div class="border border-slate-200 rounded-lg px-3 py-2">
                  <div class="text-[11px] text-slate-500">{{ $label }}</div>
                  <div class="font-semibold text-slate-800">{{ $nilai !== null ? rp((int) $nilai) : '—' }}@if($persen !== null)<span class="text-[11px] text-slate-500"> ({{ $persen }}%)</span>@endif</div>
                  @if($batasLabel !== null)
                    <div class="text-[11px] {{ $patuh === false ? 'text-rose-700 font-semibold' : 'text-emerald-700' }}">
                      {{ $batasLabel }}{{ $patuh === false ? ' · terlampaui' : ' · sesuai' }}
                    </div>
                  @endif
                </div>
              @endforeach
            </div>
          @endif

          @if(! empty($b['temuan']))
            <ul class="mt-2 text-[12px] text-amber-700 list-disc pl-5">
              @foreach($b['temuan'] as $t)
                <li>{{ is_array($t) ? ($t['pesan'] ?? $t['keterangan'] ?? json_encode($t)) : $t }}</li>
              @endforeach
            </ul>
          @endif
        </div>
      @endforeach
    </div>
    <p class="text-[11px] text-slate-400 mt-3">Rincian penuh (grafik proporsi, per tahapan, per tahun, dan temuan validasi) tersedia di panel review tiap kertas kerja.</p>
  @endif
</div>
@endsection
