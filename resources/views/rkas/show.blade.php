@extends('layouts.app', ['title' => 'Review Kertas Kerja RKAS'])
@section('content')
@php
  $sekAcuan = $h->school ?? auth()->user()->school;
  // Mode tinjauan Dinas (variabel $review terisi) tidak boleh memakai estimasi
  // pagu yang diisi sekolah — hanya alokasi resmi dari Dinas.
  $reviewJuknis ??= \App\Services\JuknisBosp2026Service::reviewKertasKerja($h, $sekAcuan, null, empty($review));
  $kodeNama ??= \App\Services\JuknisBosp2026Service::KODE_SNP_DEFAULT;
  $namaBulan ??= \App\Services\JuknisBosp2026Service::NAMA_BULAN;
  $bisaSisip = empty($review) && in_array($h->status, ['draft', 'revisi'], true);
@endphp

@if(!empty($review))
<div class="mb-4 flex gap-2 items-center flex-wrap bg-amber-50 border border-amber-300 rounded-lg p-3">
  <span class="text-sm font-semibold text-amber-800">🔍 MODE TINJAUAN DINAS — RKAS {{ $review->school->nama ?? '' }} · TA {{ $review->tahun }} revisi ke-{{ $review->revisi_ke }} (status: {{ strtoupper($review->status) }})</span>
  @if($review->status === 'dikirim_dinas')
  <form method="post" action="{{ route('dinas.rkas.sahkan', $review->id) }}" data-konfirmasi="Sahkan RKAS ini? Sekolah akan dinotifikasi.">@csrf
    <button class="border rounded px-3 py-1 text-sm bg-emerald-600 text-white font-semibold">✅ Sahkan</button>
  </form>
  <details class="relative">
    <summary class="border rounded px-3 py-1 text-sm bg-rose-600 text-white font-semibold cursor-pointer list-none">🟠 Minta Revisi</summary>
    <form method="post" action="{{ route('dinas.rkas.revisi', $review->id) }}" class="absolute left-0 mt-2 w-72 bg-white border border-slate-200 rounded-xl shadow-xl p-3 z-50">
      @csrf
      <label class="text-xs font-semibold text-slate-600">Catatan revisi untuk sekolah (wajib)</label>
      <textarea name="catatan_dinas" rows="3" class="border rounded w-full px-2 py-1 mt-1 text-xs" required minlength="5"></textarea>
      <button class="border rounded px-3 py-1 text-xs bg-rose-600 text-white font-semibold mt-2 w-full">Kirim Permintaan Revisi</button>
    </form>
  </details>
  @endif
  <a href="{{ route('dinas.rkas.pdf', $review->id) }}" class="border rounded px-3 py-1 text-sm bg-rose-50 border-rose-300">📕 PDF</a>
  <a href="{{ route('dinas.rkas.xlsx', $review->id) }}" class="border rounded px-3 py-1 text-sm bg-green-50 border-green-300">📗 Excel</a>
  <a href="{{ route('dinas.rkas') }}" class="border rounded px-3 py-1 text-sm">← Kembali ke RKAS Masuk</a>
</div>
@endif

{{-- HEADER KERTAS KERJA --}}
<div class="card flex flex-wrap justify-between items-start gap-4">
  <div>
    <div class="text-xs text-slate-500">
      {{ $h->labelJenis() }} · Sumber Dana: <b>{{ $h->sumber_dana ?? 'BOS Reguler' }}</b> · Tahun {{ $h->tahun }} · Revisi ke-{{ $h->revisi_ke }}
    </div>
    <div class="text-lg font-bold">{{ $h->no_rkas ?? '(belum ada nomor dokumen)' }}</div>
    <div class="text-sm text-slate-500 mt-1">{{ $h->catatan }}</div>
    <div class="mt-2">@include('rkas.partials._status-arkas', ['h' => $h])</div>
    @if($h->status==='dikirim_dinas')<div class="text-[11px] text-indigo-500 mt-1">dikirim {{ $h->dikirim_dinas_at?->format('d/m/Y H:i') }} — menunggu tinjauan Dinas</div>@endif
    @if($h->status==='disahkan')<div class="text-[11px] text-emerald-600 mt-1">🎉 disahkan oleh {{ $h->ditinjau_oleh }} ({{ $h->ditinjau_at?->format('d/m/Y') }})</div>@endif
    @if($h->status==='revisi')<div class="mt-1 text-[11px] bg-rose-50 border border-rose-200 text-rose-700 rounded px-2 py-1">🟠 Catatan Dinas ({{ $h->ditinjau_oleh }}): <b>{{ $h->catatan_dinas }}</b> — susun RKAS revisi baru, lalu kirim ulang ke Dinas.</div>@endif
  </div>
  @if(empty($review))
  <div class="flex flex-wrap gap-2 items-center">
    <form method="post" action="{{ route('rkas.aksi', $h) }}" class="flex flex-wrap gap-2">
      @csrf
      @if($h->status==='draft')
        <button name="aksi" value="ajukan" class="btn"
                @disabled(! $praAjukan['boleh_ajukan'])
                title="{{ $praAjukan['boleh_ajukan'] ? 'Ajukan Kertas Kerja ke Kepala Sekolah' : 'Belum bisa diajukan: selesaikan '.$praAjukan['jumlah_blokir'].' hal yang memblokir pada panel Kelayakan Pengajuan' }}">📤 Ajukan ke Kepsek</button>
      @endif
      @if($h->status==='diajukan' && auth()->user()->role==='kepsek')<button name="aksi" value="setujui" class="btn">✅ Setujui (Kepsek)</button>@endif
      @if(in_array($h->status, ['disetujui', 'revisi']))
        <button name="aksi" value="kirim_dinas" class="btn bg-indigo-600" data-konfirmasi="Kirim RKAS ini ke Dinas untuk disahkan?">{{ $h->status === 'revisi' ? '🔄 Kirim Ulang ke Dinas' : '📨 Kirim ke Dinas' }}</button>
      @endif
      @if($h->status==='disahkan')<button name="aksi" value="kunci" class="btn2 btn">🔒 Kunci RKAS</button>@endif
    </form>
    <a href="{{ route('rkas.salinArkas', $h) }}" class="btn2 btn bg-indigo-50 border-indigo-200 text-indigo-700">📋 Salin Cepat ke ARKAS 4</a>
    <a href="{{ route('rkas.pdf', $h) }}" class="btn2 btn bg-rose-50 border-rose-300">📕 PDF</a>
    <a href="{{ route('rkas.xlsx', $h) }}" class="btn2 btn bg-green-50 border-green-300">📗 Excel</a>
    <a href="{{ route('rkas.index') }}" class="btn2 btn">← Kembali</a>
  </div>
  @endif
</div>

@if(in_array($h->status, ['draft', 'revisi'], true))
  @include('rkas.partials._pra-ajukan', ['pra' => $praAjukan, 'h' => $h])
@endif

{{-- PANEL REVIEW KERTAS KERJA ALA ARKAS 4 v4.3.0 (3 MODE: GRAFIK PROPORSI · PER TAHAPAN · PER TAHUN) --}}
<div class="card space-y-4">
  <div class="flex flex-wrap justify-between items-center gap-2 border-b border-slate-100 pb-3">
    <div>
      <h3 class="font-bold text-slate-800 text-sm">📊 Review Kertas Kerja &amp; Kepatuhan Juknis BOSP 2026</h3>
      <p class="text-xs text-slate-500">{{ $reviewJuknis['aturan']['label_aturan'] }}</p>
    </div>
    <div class="flex gap-1.5 text-xs">
      <button type="button" data-tab-review="proporsi" id="btnTab-proporsi" class="btn btn-sm">🥧 Grafik Proporsi</button>
      <button type="button" data-tab-review="tahapan" id="btnTab-tahapan" class="btn2 btn-sm">🗓️ Per Tahapan</button>
      <button type="button" data-tab-review="tahunan" id="btnTab-tahunan" class="btn2 btn-sm">📅 Per Tahun</button>
    </div>
  </div>

  {{-- Ringkasan 4 Kartu Kontrol Pagu --}}
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
    <div class="rounded-lg bg-slate-50 border border-slate-200 p-3">
      <div class="text-slate-500">Total Pagu Sumber Dana</div>
      <div class="font-bold text-base text-slate-800">{{ rp($reviewJuknis['pagu']) }}</div>
    </div>
    <div class="rounded-lg bg-indigo-50 border border-indigo-200 p-3">
      <div class="text-indigo-700">Total Dianggarkan</div>
      <div class="font-bold text-base text-indigo-900">{{ rp($reviewJuknis['total_anggaran']) }}</div>
    </div>
    <div class="rounded-lg {{ $reviewJuknis['belum_dianggarkan'] === 0 ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200' }} border p-3">
      <div class="text-slate-600">Belum Dianggarkan (Target Rp 0)</div>
      <div class="font-bold text-base">{{ rp($reviewJuknis['belum_dianggarkan']) }}</div>
    </div>
    <div class="rounded-lg {{ $reviewJuknis['tahap1_pct'] <= 50 ? 'bg-sky-50 border-sky-200' : 'bg-rose-50 border-rose-200' }} border p-3">
      <div class="text-slate-600">Proporsi Tahap 1 vs Tahap 2</div>
      <div class="font-bold text-base">{{ $reviewJuknis['tahap1_pct'] }}% : {{ $reviewJuknis['tahap2_pct'] }}%</div>
    </div>
  </div>

  {{-- Daftar Temuan Validasi Juknis 2026 --}}
  @if(count($reviewJuknis['temuan']))
  <div class="space-y-1.5 text-xs">
    @foreach($reviewJuknis['temuan'] as $tm)
      <div class="rounded-lg px-3 py-2 border {{ $tm['level']==='blokir' ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-amber-50 border-amber-200 text-amber-800' }}">
        {{ $tm['level']==='blokir' ? '⛔' : '⚠️' }} {{ $tm['pesan'] }}
      </div>
    @endforeach
  </div>
  @else
  <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2 text-xs text-emerald-800 font-semibold">
    ✅ Seluruh parameter Kertas Kerja telah memenuhi ketentuan ARKAS 4 v{{ config('app.arkas_acuan', '4.3.0') }} &amp; Permendikdasmen No. 8 Tahun 2026.
  </div>
  @endif

  {{-- TAB 1: GRAFIK PROPORSI JUKNIS 2026 + ANTAR JENIS BELANJA --}}
  <div id="tabReview-proporsi" class="grid md:grid-cols-2 gap-4 pt-1">
    <div class="border border-slate-200 rounded-lg p-3 space-y-3">
      <div class="font-semibold text-xs text-slate-700">⚖️ Proporsi Komponen Kunci Permendikdasmen No. 8 Tahun 2026</div>
      @if($reviewJuknis['aturan']['mode'] === 'reguler')
        @foreach($reviewJuknis['proporsi'] as $k => $pr)
        <div>
          <div class="flex justify-between text-xs mb-1">
            <span class="font-medium">{{ $pr['label'] }}</span>
            <span class="font-bold {{ $pr['ok'] ? 'text-emerald-700' : 'text-rose-700' }}">
              {{ rp($pr['nilai']) }} ({{ $pr['pct'] }}% · {{ $pr['batas_label'] }})
            </span>
          </div>
          <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
            <div class="h-full {{ $pr['ok'] ? 'bg-emerald-500' : 'bg-rose-500' }}" style="width:{{ min(100, $pr['pct']) }}%"></div>
          </div>
        </div>
        @endforeach
      @else
        <p class="text-xs text-slate-500">Sesuai pembaruan ARKAS v4.2.19 &amp; Permendikdasmen No. 8/2026, sumber dana non-reguler tidak dikenai batas persentase honor/buku reguler dan dievaluasi berdasarkan <b>Proporsi Antar Jenis Belanja</b> di samping kanan.</p>
      @endif
    </div>

    <div class="border border-slate-200 rounded-lg p-3 space-y-3">
      <div class="font-semibold text-xs text-slate-700">📦 Proporsi Antar Jenis Belanja (Akun 5.1 / 5.2)</div>
      @foreach($reviewJuknis['antar_jenis'] as $aj)
      <div>
        <div class="flex justify-between text-xs mb-1">
          <span>{{ $aj['label'] }}</span>
          <span class="font-bold">{{ rp($aj['nilai']) }} ({{ $aj['pct'] }}%)</span>
        </div>
        <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
          <div class="h-full bg-indigo-500" style="width:{{ min(100, $aj['pct']) }}%"></div>
        </div>
      </div>
      @endforeach
    </div>
  </div>

  {{-- TAB 2: PER TAHAPAN (TAHAP 1 vs TAHAP 2 + 12 BULAN) --}}
  <div id="tabReview-tahapan" class="hidden space-y-3 pt-1">
    <div class="grid sm:grid-cols-2 gap-3 text-xs">
      <div class="rounded-lg border border-slate-200 p-3">
        <div class="font-bold text-slate-700">Tahap 1 (Januari – Juni)</div>
        <div class="text-lg font-bold text-sky-700 mt-0.5">{{ rp($reviewJuknis['tahap1']) }} ({{ $reviewJuknis['tahap1_pct'] }}%)</div>
      </div>
      <div class="rounded-lg border border-slate-200 p-3">
        <div class="font-bold text-slate-700">Tahap 2 (Juli – Desember)</div>
        <div class="text-lg font-bold text-indigo-700 mt-0.5">{{ rp($reviewJuknis['tahap2']) }} ({{ $reviewJuknis['tahap2_pct'] }}%)</div>
      </div>
    </div>
    <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 text-xs">
      @foreach($reviewJuknis['per_bulan'] as $m => $nom)
      <div class="rounded border border-slate-200 p-2 bg-slate-50">
        <div class="text-slate-500">{{ $namaBulan[$m] }}</div>
        <div class="font-bold mt-0.5">{{ rp($nom) }}</div>
      </div>
      @endforeach
    </div>
  </div>

  {{-- TAB 3: REKAP PER TAHUN PER KEGIATAN SNP --}}
  <div id="tabReview-tahunan" class="hidden pt-1">
    <table class="tbl">
      <thead><tr><th>Kode Kegiatan SNP</th><th>Nama Kegiatan</th><th class="num">Total Setahun</th></tr></thead>
      <tbody>
        @foreach($reviewJuknis['per_kegiatan'] as $kodeKeg => $totKeg)
        <tr>
          <td class="font-mono text-xs">{{ $kodeKeg }}</td>
          <td>{{ $kodeNama[$kodeKeg] ?? 'Kegiatan Operasional Sekolah' }}</td>
          <td class="num font-bold">{{ rp($totKeg) }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

{{-- TABEL RINCIAN KERTAS KERJA + FITUR SISIP URAIAN --}}
<div class="card overflow-x-auto">
  <div class="flex justify-between items-center mb-2">
    <h3 class="font-semibold text-slate-700 text-sm">📑 Rincian Uraian Kertas Kerja ({{ $h->details->count() }} item)</h3>
    @if($bisaSisip)
      <span class="text-xs text-slate-400">Klik <b>+ Sisip</b> pada baris mana pun untuk menyisipkan uraian baru pada kegiatan/rekening yang sama (ala ARKAS 4).</span>
    @endif
  </div>
  <table class="tbl">
    <thead>
      <tr>
        <th>Keg.</th>
        <th>Kode</th>
        <th>Rekening</th>
        <th>Uraian</th>
        <th>Satuan</th>
        <th class="num">Banyak</th>
        <th class="num">Harga</th>
        <th class="num">Tahap 1</th>
        <th class="num">Tahap 2</th>
        <th class="num">Jumlah</th>
        <th>Sumber</th>
        <th>Panduan</th>
        @if($bisaSisip)<th></th>@endif
      </tr>
    </thead>
    <tbody>
      @foreach($h->details as $d)
      <tr id="baris-{{ $d->id }}" class="scroll-mt-24">
        <td class="font-mono text-xs">{{ $d->kode_kegiatan ?? '—' }}</td>
        <td class="font-mono text-xs">{{ $d->rekening->kode }}</td>
        <td>{{ $d->rekening->nama }}</td>
        <td>{{ $d->uraian }}</td>
        <td>{{ $d->satuan }}</td>
        <td class="num">{{ $d->banyak }}</td>
        <td class="num">{{ rp($d->harga_satuan) }}</td>
        <td class="num text-xs text-sky-700">{{ rp($d->nominalTahap1()) }}</td>
        <td class="num text-xs text-indigo-700">{{ rp($d->nominalTahap2()) }}</td>
        <td class="num font-semibold">{{ rp($d->jumlah) }}</td>
        <td>{{ $d->sumber_dana }}</td>
        <td>@include('partials._panduan-rincian', ['kode' => $d->rekening->kode ?? ''])</td>
        @if($bisaSisip)
        <td>
          <details class="relative">
            <summary class="badge bg-indigo-50 text-indigo-700 cursor-pointer list-none">+ Sisip</summary>
            <form method="post" action="{{ route('rkas.sisip', [$h, $d]) }}" class="absolute right-0 mt-1 w-64 bg-white border border-slate-200 rounded-xl shadow-xl p-3 z-40 space-y-2 text-xs">
              @csrf
              <div class="font-bold text-slate-700">Sisip Uraian ({{ $d->rekening->kode }})</div>
              <input name="uraian" class="inp text-xs" maxlength="500" required placeholder="Uraian barang/jasa sisipan (maks. 500 karakter)">
              <div class="grid grid-cols-2 gap-1.5">
                <input type="number" name="banyak" min="1" value="1" class="inp text-xs" placeholder="Vol">
                <input type="number" name="harga_satuan" min="0" value="{{ (int) $d->harga_satuan }}" class="inp text-xs" placeholder="Harga">
              </div>
              <select name="bulan" class="inp text-xs">
                @foreach($namaBulan as $idx => $nb)<option value="{{ $idx }}">{{ $nb }}</option>@endforeach
              </select>
              <button class="btn w-full justify-center text-xs">Simpan Sisipan</button>
            </form>
          </details>
        </td>
        @endif
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr class="font-bold">
        <td colspan="9" class="text-right">TOTAL</td>
        <td class="num">{{ rp($h->details->sum('jumlah')) }}</td>
        <td @if($bisaSisip) colspan="3" @else colspan="2" @endif></td>
      </tr>
    </tfoot>
  </table>
</div>

<script nonce="{{ request()->attributes->get('csp_nonce') }}">
function setTabReview(tab){
  ['proporsi','tahapan','tahunan'].forEach(t => {
    document.getElementById('tabReview-' + t).classList.toggle('hidden', t !== tab);
    const btn = document.getElementById('btnTab-' + t);
    btn.className = (t === tab ? 'btn btn-sm' : 'btn2 btn-sm');
  });
}
// Delegasi event menggantikan atribut on* inline (audit R-03).
document.addEventListener('click', function (ev) {
  const el = ev.target.closest('[data-tab-review]');
  if (el) setTabReview(el.dataset.tabReview);
});

// Periksa Ulang kelayakan pengajuan: ambil hasil terbaru lalu render ulang panel
// (server yang menggambar daftar, agar tidak ada logika tampilan ganda).
document.addEventListener('click', async function (ev) {
  const btn = ev.target.closest('#btnPeriksaUlang');
  if (!btn) return;
  ev.preventDefault();
  const status = document.getElementById('praAjukanStatus');
  btn.disabled = true;
  if (status) status.textContent = 'Memeriksa…';
  try {
    const res = await fetch(btn.dataset.url, { headers: { 'Accept': 'application/json' } });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();
    if (status) {
      status.textContent = 'Pemeriksaan terbaru: ' + (data.boleh_ajukan
        ? 'siap diajukan'
        : data.jumlah_blokir + ' hal memblokir');
    }
    window.location.reload();
  } catch (e) {
    if (status) status.textContent = 'Gagal memeriksa, coba lagi.';
    btn.disabled = false;
  }
});
</script>
@endsection
