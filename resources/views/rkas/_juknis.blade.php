@if(! empty($juknis))
<div class="card border-sky-200 !bg-sky-50/40">
  <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
    <div>
      <h3 class="card-title mb-1">📐 Cek Juknis BOSP 2026 (Permendikdasmen No 8/2026 Pasal 42)</h3>
      <p class="page-desc mb-0">Batas komponen dihitung dari pagu <b>{{ $juknis['sumber'] }}</b> — {{ rp($juknis['pagu']) }}.</p>
    </div>
    <div class="text-right text-[11px] text-slate-500">Total RKAS diperiksa<br><b class="text-sm text-slate-700">{{ rp($juknis['total']) }}</b></div>
  </div>
  <div class="overflow-x-auto">
    <table class="tbl">
      <thead><tr><th>Komponen</th><th class="num">Anggaran</th><th class="num">% pagu</th><th class="num">Batas</th><th class="num">Sisa ruang</th><th>Status</th></tr></thead>
      <tbody>
        @foreach($juknis['aturan'] as $a)
          <tr>
            <td>
              <b>{{ $a['nama'] }}</b>
              <div class="text-[11px] text-slate-500">{{ $a['keterangan'] }}</div>
            </td>
            <td class="num">{{ rp($a['anggaran']) }}</td>
            <td class="num">{{ $a['persen'] }}%</td>
            <td class="num">{{ $a['jenis'] === 'maks' ? 'maks' : 'min' }} {{ $a['batas_persen'] }}%<br><span class="text-[11px] text-slate-400">{{ rp($a['batas_nominal']) }}</span></td>
            <td class="num">
              @if($a['status'] === 'tak_tercek')
                <span class="text-slate-400">—</span>
              @elseif($a['jenis'] === 'min')
                @if($a['status'] === 'aman')
                  <span class="text-emerald-700">terpenuhi (+{{ rp($a['sisa']) }})</span>
                @else
                  <span class="text-amber-700 font-semibold">kurang {{ rp($a['sisa']) }}</span>
                @endif
              @elseif($a['sisa'] >= 0)
                <span class="text-emerald-700">{{ rp($a['sisa']) }}</span>
              @else
                <span class="text-rose-700 font-semibold">−{{ rp(abs($a['sisa'])) }}</span>
              @endif
            </td>
            <td>
              @php
                $badge = match ($a['status']) {
                    'aman' => 'bg-emerald-100 text-emerald-700',
                    'terlampaui' => 'bg-rose-100 text-rose-700',
                    'kurang' => 'bg-amber-100 text-amber-700',
                    default => 'bg-slate-100 text-slate-600',
                };
                $teks = match ($a['status']) {
                    'aman' => '✅ Aman',
                    'terlampaui' => '⚠️ Terlampaui',
                    'kurang' => '⚠️ Kurang',
                    default => 'Belum bisa dicek',
                };
              @endphp
              <span class="badge {{ $badge }}">{{ $teks }}</span>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @if(! empty($juknis['catatan']))
    <ul class="mt-2 text-[11px] text-slate-500 list-disc pl-5 space-y-0.5">
      @foreach($juknis['catatan'] as $c)<li>{{ $c }}</li>@endforeach
    </ul>
  @endif
  <p class="text-[11px] text-slate-400 mt-2">Dasar: Permendikdasmen No 8 Tahun 2026 Pasal 42 — honor maksimum 20% (dasmen negeri) / 40% (dasmen swasta) / 40% (PAUD &amp; Kesetaraan), buku minimum 10% (5% PAUD), sarpras maksimum 20%, semuanya dari 100% total pagu. Klasifikasi memakai kode kegiatan resmi ARKAS (07.12.x, 05.08.x, 05.02.x) dan rekening (5.1.02.02.01.0013, 5.2.02.05.02.0001).</p>
</div>
@endif
