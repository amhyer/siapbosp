{{--
  Gerbang pra-pengajuan (P0-2): daftar pemeriksaan sebelum "Ajukan ke Kepsek".
  Variabel: $pra (hasil App\Services\PraAjukanRkasService::periksa()), $h (RkasHeader).
--}}
@php
  $gayaLevel = [
    'blokir' => ['bg-rose-50 border-rose-200', 'bg-rose-100 text-rose-700', '', 'Blokir'],
    'peringatan' => ['bg-amber-50 border-amber-200', 'bg-amber-100 text-amber-700', '', 'Peringatan'],
    'info' => ['bg-slate-50 border-slate-200', 'bg-slate-100 text-slate-600', 'ℹ', 'Info'],
    'lolos' => ['bg-emerald-50 border-emerald-200', 'bg-emerald-100 text-emerald-700', '', 'Lolos'],
  ];
@endphp
<div class="card border-l-4 {{ $pra['boleh_ajukan'] ? 'border-emerald-500' : 'border-rose-500' }}" id="panelPraAjukan"> <div class="flex flex-wrap justify-between items-start gap-2"> <div> <h3 class="font-bold text-slate-800 text-sm"> Kelayakan Pengajuan Kertas Kerja</h3> <p class="text-xs text-slate-500 mt-0.5"> Pemeriksaan yang sama dengan tombol <b>Ajukan Pengesahan</b> di ARKAS 4.
        @if($pra['boleh_ajukan'])
          Kertas Kerja ini <b class="text-emerald-600">siap diajukan</b>{{ $pra['jumlah_peringatan'] > 0 ? ', dengan '.$pra['jumlah_peringatan'].' catatan' : '' }}.
        @else
          Masih ada <b class="text-rose-600">{{ $pra['jumlah_blokir'] }} hal yang memblokir</b> pengajuan.
        @endif
      </p> </div> <div class="text-right"> <div class="text-[11px] text-slate-500">Belum Dianggarkan</div> <div class="font-bold text-sm {{ ($pra['belum_dianggarkan'] ?? 0) === 0 ? 'text-emerald-600' : 'text-rose-600' }}"> {{ rp((int) ($pra['belum_dianggarkan'] ?? 0)) }}
      </div> <div class="text-[10px] text-slate-400">harus Rp 0</div> </div> </div> <div class="grid gap-1.5 mt-3"> @foreach($pra['cek'] as $c)
      @php [$kotak, $chip, $ikon, $labelLevel] = $gayaLevel[$c['level']] ?? $gayaLevel['info']; @endphp
      <div class="border {{ $kotak }} rounded-lg px-3 py-2"> <div class="flex flex-wrap items-center gap-2"> <span class="badge {{ $chip }}">{{ $ikon }} {{ $labelLevel }}</span> <span class="text-xs font-semibold text-slate-700">{{ $c['judul'] }}</span> @if(! empty($c['baris']))
            <span class="text-[10px] text-slate-400">{{ count($c['baris']) }} baris terkait</span> @endif
        </div> <p class="text-[11px] text-slate-600 mt-1">{{ $c['pesan'] }}</p> @if(! empty($c['baris']))
          <div class="flex flex-wrap gap-1 mt-1"> @foreach($c['baris'] as $idBaris)
              <a href="#baris-{{ $idBaris }}" class="text-[10px] px-1.5 py-0.5 rounded bg-white border border-slate-200 text-slate-500 hover:text-indigo-600">rincian #{{ $idBaris }}</a> @endforeach
          </div> @endif
      </div> @endforeach
  </div> <div class="flex flex-wrap gap-2 items-center mt-3 border-t border-slate-100 pt-2"> <button type="button" class="btn2 btn btn-sm" id="btnPeriksaUlang" data-url="{{ route('rkas.pra-ajukan', $h) }}"> Periksa Ulang</button> <span class="text-[11px] text-slate-400" id="praAjukanStatus"> Pemeriksaan terakhir: {{ now()->format('d/m/Y H:i') }}
    </span> </div>
</div>
