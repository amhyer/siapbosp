@extends('layouts.app', ['title' => 'Perencanaan Berbasis Data (PBD → RKM → RKT)'])
@section('subtitle', 'Alur Hulu Pra-ARKAS Tahun Anggaran ' . $tahun . ' · Rancang matang di SiapBOSP, lalu tuangkan ke Kertas Kerja RKAS & salin cepat ke ARKAS 4')
@section('content')
@php
  $jmlBelum = $rkts->where('butuh_anggaran', true)->where('status_rkas', 'belum')->count();
  $estBelum = $rkts->where('butuh_anggaran', true)->where('status_rkas', 'belum')->sum('estimasi_biaya');
  $namaBulan = \App\Services\JuknisBosp2026Service::NAMA_BULAN;
@endphp

<div class="card border-l-4 border-indigo-600 flex flex-wrap items-center justify-between gap-3">
  <div>
    <div class="font-bold text-slate-800 text-sm">🧭 Alur Perencanaan Pra-ARKAS (Rapor Pendidikan → RKM 4 Tahun → RKT Tahunan → Kertas Kerja RKAS)</div>
    <p class="text-xs text-slate-500 mt-0.5">Susun akar masalah Rapor Pendidikan (PBD), turunkan ke RKM 4 tahun &amp; RKT tahun {{ $tahun }}, lalu klik <b>Dorong ke Kertas Kerja RKAS</b> agar otomatis dicek kepatuhan Juknis BOSP 2026 sebelum diinput ke ARKAS 4 Desktop.</p>
  </div>
  <div class="flex flex-wrap gap-2">
    <form method="post" action="{{ route('perencanaan.pbd.template') }}">@csrf
      <button class="btn2 btn">✨ Muat Contoh Prioritas Rapor Pendidikan</button>
    </form>
    @if($jmlBelum > 0)
    <form method="post" action="{{ route('perencanaan.rkt.keRkas') }}">@csrf
      <button class="btn">🚀 Dorong {{ $jmlBelum }} RKT ({{ rp($estBelum) }}) ke Kertas Kerja RKAS →</button>
    </form>
    @endif
  </div>
</div>

{{-- STEP 1: PBD / RAPOR PENDIDIKAN --}}
<div class="card space-y-4">
  <div class="flex items-center justify-between">
    <h3 class="font-bold text-slate-700 text-sm">1️⃣ Rekomendasi Prioritas PBD / Rapor Pendidikan ({{ $pbds->count() }})</h3>
    <span class="badge bg-indigo-100 text-indigo-700">Tahap Identifikasi · Refleksi · Benahi</span>
  </div>
  <form method="post" action="{{ route('perencanaan.pbd.store') }}" class="grid sm:grid-cols-6 gap-3 bg-slate-50 p-3 rounded-lg border border-slate-200">
    @csrf
    <div class="sm:col-span-2">
      <label class="text-xs font-semibold text-slate-600">Indikator Rapor Pendidikan</label>
      <select name="kode_indikator" class="inp mt-1">
        @foreach($indikatorRapor as $kd => $nm)<option value="{{ $kd }}">{{ $nm }}</option>@endforeach
      </select>
    </div>
    <div>
      <label class="text-xs font-semibold text-slate-600">Skor Capaian</label>
      <input type="number" step="0.01" min="0" max="100" name="skor_capaian" class="inp mt-1" placeholder="65.5">
    </div>
    <div>
      <label class="text-xs font-semibold text-slate-600">Prioritas</label>
      <select name="prioritas" class="inp mt-1">
        <option value="tinggi">Prioritas Tinggi</option>
        <option value="sedang">Prioritas Sedang</option>
        <option value="rendah">Prioritas Rendah</option>
      </select>
    </div>
    <div class="sm:col-span-2">
      <label class="text-xs font-semibold text-slate-600">Rekomendasi Kode Kegiatan SNP</label>
      <select name="kode_kegiatan" class="inp mt-1">
        @foreach($kodeKegiatan as $kd => $ur)<option value="{{ $kd }}">{{ $kd }} · {{ Str::limit($ur, 34) }}</option>@endforeach
      </select>
    </div>
    <div class="sm:col-span-3">
      <label class="text-xs font-semibold text-slate-600">Akar Masalah (Refleksi)</label>
      <input name="akar_masalah" class="inp mt-1" required placeholder="Contoh: Capaian teks informasi & buku bacaan bermutu masih kurang">
    </div>
    <div class="sm:col-span-2">
      <label class="text-xs font-semibold text-slate-600">Inspirasi Benahi</label>
      <input name="benahi" class="inp mt-1" required placeholder="Contoh: Pengadaan buku pengayaan perpustakaan & kombel literasi">
    </div>
    <div class="flex items-end"><button class="btn w-full justify-center">+ Simpan PBD</button></div>
  </form>

  <div class="overflow-x-auto">
    <table class="tbl">
      <thead><tr><th>Indikator</th><th>Skor</th><th>Prioritas</th><th>Akar Masalah</th><th>Benahi</th><th>Kegiatan SNP</th><th></th></tr></thead>
      <tbody>
        @forelse($pbds as $p)
        <tr>
          <td class="font-semibold">{{ $p->nama_indikator }}</td>
          <td>{{ $p->skor_capaian ?? '—' }}</td>
          <td><span class="badge {{ $p->prioritas==='tinggi'?'bg-rose-100 text-rose-700':($p->prioritas==='sedang'?'bg-amber-100 text-amber-700':'bg-slate-100 text-slate-600') }}">{{ strtoupper($p->prioritas) }}</span></td>
          <td>{{ $p->akar_masalah }}</td>
          <td>{{ $p->benahi }}</td>
          <td class="font-mono text-xs">{{ $p->kode_kegiatan ?? '—' }}</td>
          <td><form method="post" action="{{ route('perencanaan.pbd.destroy', $p) }}">@csrf @method('DELETE')<button class="badge bg-rose-50 text-rose-600">✖</button></form></td>
        </tr>
        @empty
        <tr><td colspan="7" class="text-center text-slate-400 py-4">Belum ada data PBD. Klik tombol <b>Muat Contoh Prioritas Rapor Pendidikan</b> di atas atau isi form.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- STEP 2: RKM 4 TAHUN --}}
<div class="card space-y-4">
  <h3 class="font-bold text-slate-700 text-sm">2️⃣ Rencana Kerja Menengah / RKM 4 Tahun ({{ $rkms->count() }})</h3>
  <form method="post" action="{{ route('perencanaan.rkm.store') }}" class="grid sm:grid-cols-5 gap-3 bg-slate-50 p-3 rounded-lg border border-slate-200">
    @csrf
    <div>
      <label class="text-xs font-semibold text-slate-600">Acuan PBD</label>
      <select name="pbd_id" class="inp mt-1">
        <option value="">— Tanpa acuan khusus —</option>
        @foreach($pbds as $p)<option value="{{ $p->id }}">{{ $p->kode_indikator }} · {{ Str::limit($p->benahi, 30) }}</option>@endforeach
      </select>
    </div>
    <div>
      <label class="text-xs font-semibold text-slate-600">Tahun Awal RKM</label>
      <input type="number" name="tahun_awal" value="{{ $tahun }}" class="inp mt-1" required>
    </div>
    <div class="sm:col-span-2">
      <label class="text-xs font-semibold text-slate-600">Sasaran Strategis 4 Tahun</label>
      <input name="sasaran" class="inp mt-1" required placeholder="Sasaran peningkatan mutu 4 tahun">
    </div>
    <div class="flex items-end"><button class="btn w-full justify-center">+ Simpan RKM</button></div>
  </form>
  <div class="overflow-x-auto">
    <table class="tbl">
      <thead><tr><th>Periode</th><th>Acuan PBD</th><th>Sasaran Strategis</th><th>Target Capaian</th><th></th></tr></thead>
      <tbody>
        @forelse($rkms as $m)
        <tr>
          <td class="font-mono text-xs">{{ $m->tahun_awal }}–{{ $m->tahun_akhir }}</td>
          <td class="text-xs text-slate-500">{{ $m->pbd?->kode_indikator ?? '—' }}</td>
          <td class="font-semibold">{{ $m->sasaran }}</td>
          <td class="text-xs">{{ $m->target_capaian ?? '—' }}</td>
          <td><form method="post" action="{{ route('perencanaan.rkm.destroy', $m) }}">@csrf @method('DELETE')<button class="badge bg-rose-50 text-rose-600">✖</button></form></td>
        </tr>
        @empty
        <tr><td colspan="5" class="text-center text-slate-400 py-4">Belum ada sasaran RKM 4 Tahun.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- STEP 3: RKT TAHUNAN (BER-ANGGARAN & NON-ANGGARAN) --}}
<div class="card space-y-4">
  <div class="flex flex-wrap items-center justify-between gap-2">
    <h3 class="font-bold text-slate-700 text-sm">3️⃣ Rencana Kerja Tahunan / RKT Tahun {{ $tahun }} ({{ $rkts->count() }} kegiatan)</h3>
    <a href="{{ route('perencanaan.ssh') }}" class="btn2 btn text-xs">🏷️ Cek Standar Harga (SSH/SBU)</a>
  </div>
  <form method="post" action="{{ route('perencanaan.rkt.store') }}" class="grid sm:grid-cols-6 gap-3 bg-slate-50 p-3 rounded-lg border border-slate-200">
    @csrf
    <div class="sm:col-span-2">
      <label class="text-xs font-semibold text-slate-600">Sasaran RKM</label>
      <select name="rkm_id" class="inp mt-1">
        <option value="">— Pilih sasaran RKM —</option>
        @foreach($rkms as $m)<option value="{{ $m->id }}">{{ Str::limit($m->sasaran, 45) }}</option>@endforeach
      </select>
    </div>
    <div class="sm:col-span-2">
      <label class="text-xs font-semibold text-slate-600">Kode Kegiatan SNP (ARKAS 4)</label>
      <select name="kode_kegiatan" class="inp mt-1">
        @foreach($kodeKegiatan as $kd => $ur)<option value="{{ $kd }}">{{ $kd }} · {{ Str::limit($ur, 36) }}</option>@endforeach
      </select>
    </div>
    <div>
      <label class="text-xs font-semibold text-slate-600">Kebutuhan Biaya</label>
      <select name="butuh_anggaran" id="rktButuh" class="inp mt-1" onchange="document.querySelectorAll('.rkt-biaya').forEach(e=>e.style.opacity=this.value=='1'?1:.45)">
        <option value="1">Butuh Anggaran (Masuk RKAS)</option>
        <option value="0">Tanpa Biaya (Non-Anggaran)</option>
      </select>
    </div>
    <div>
      <label class="text-xs font-semibold text-slate-600">Bulan Pelaksanaan</label>
      <select name="bulan_target" class="inp mt-1">
        @foreach($namaBulan as $idx => $nb)<option value="{{ $idx }}">{{ $nb }} (Tahap {{ $idx<=6?1:2 }})</option>@endforeach
      </select>
    </div>
    <div class="sm:col-span-3">
      <label class="text-xs font-semibold text-slate-600">Uraian Kegiatan RKT</label>
      <input name="nama_kegiatan" class="inp mt-1" required placeholder="Contoh: Pengadaan buku teks & pengayaan literasi perpustakaan">
    </div>
    <div class="sm:col-span-3 rkt-biaya">
      <label class="text-xs font-semibold text-slate-600">Rekening Belanja ARKAS</label>
      <select name="rekening_id" class="inp mt-1">
        <option value="">— Pilih rekening belanja —</option>
        @foreach($rekenings as $rk)<option value="{{ $rk->id }}">{{ $rk->kode }} · {{ Str::limit($rk->nama, 42) }}</option>@endforeach
      </select>
    </div>
    <div class="rkt-biaya">
      <label class="text-xs font-semibold text-slate-600">Volume</label>
      <input type="number" min="1" value="1" name="volume" class="inp mt-1">
    </div>
    <div class="rkt-biaya">
      <label class="text-xs font-semibold text-slate-600">Satuan</label>
      <input name="satuan" value="paket" class="inp mt-1">
    </div>
    <div class="rkt-biaya">
      <label class="text-xs font-semibold text-slate-600">Harga Satuan (Rp)</label>
      <input type="number" min="0" value="0" name="harga_satuan" class="inp mt-1">
    </div>
    <div class="sm:col-span-2 rkt-biaya">
      <label class="text-xs font-semibold text-slate-600">Sumber Dana</label>
      <select name="sumber_dana" class="inp mt-1">
        @foreach($sumberDanaList as $sd => $lbl)<option value="{{ $sd }}">{{ $lbl }}</option>@endforeach
      </select>
    </div>
    <div class="flex items-end"><button class="btn w-full justify-center">+ Tambah RKT</button></div>
  </form>

  <div class="overflow-x-auto">
    <table class="tbl">
      <thead><tr><th>Kode SNP</th><th>Kegiatan RKT</th><th>Bulan</th><th>Rekening</th><th class="num">Estimasi Biaya</th><th>Status RKAS</th><th></th></tr></thead>
      <tbody>
        @forelse($rkts as $k)
        <tr>
          <td class="font-mono text-xs">{{ $k->kode_kegiatan ?? '—' }}</td>
          <td class="font-semibold">{{ $k->nama_kegiatan }}</td>
          <td>{{ $namaBulan[$k->bulan_target] ?? 'Januari' }}</td>
          <td class="font-mono text-xs">{{ $k->butuh_anggaran ? ($k->rekening?->kode ?? '—') : 'Non-Anggaran' }}</td>
          <td class="num font-semibold">{{ $k->butuh_anggaran ? rp($k->estimasi_biaya) : 'Rp 0' }}</td>
          <td>
            @if(!$k->butuh_anggaran)
              <span class="badge bg-slate-100 text-slate-500">Non-Anggaran</span>
            @elseif($k->status_rkas === 'belum')
              <span class="badge bg-amber-100 text-amber-700">Siap Didorong ke RKAS</span>
            @else
              <span class="badge bg-emerald-100 text-emerald-700">✓ Masuk Draft RKAS</span>
            @endif
          </td>
          <td><form method="post" action="{{ route('perencanaan.rkt.destroy', $k) }}">@csrf @method('DELETE')<button class="badge bg-rose-50 text-rose-600">✖</button></form></td>
        </tr>
        @empty
        <tr><td colspan="7" class="text-center text-slate-400 py-4">Belum ada rincian kegiatan RKT tahun {{ $tahun }}.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
