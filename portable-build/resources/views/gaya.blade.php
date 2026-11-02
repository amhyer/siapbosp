@extends('layouts.app', ['title' => 'Gaya Antarmuka'])
@section('subtitle', 'Token, warna, dan komponen SiapBOSP — nilainya diambil dari sistem desain ARKAS 4.3.1')

@section('content')
@php
  /**
   * Daftar token yang ditampilkan di halaman ini. Nilai hex hanya untuk
   * keterangan; kotak warnanya memakai var(--arkas-*) supaya halaman ini
   * sekaligus membuktikan token benar-benar ada di CSS hasil build.
   */
  $tokenWarna = [
    ['nama' => '--arkas-action', 'nilai' => '#0b5fef', 'ket' => 'Aksi utama (tombol, menu aktif)'],
    ['nama' => '--arkas-action-hovered', 'nilai' => '#054bcc', 'ket' => 'Aksi utama saat disentuh kursor'],
    ['nama' => '--arkas-action-pressed', 'nilai' => '#103178', 'ket' => 'Aksi utama saat ditekan'],
    ['nama' => '--arkas-primary', 'nilai' => '#0b5ae2', 'ket' => 'Biru merek ARKAS'],
    ['nama' => '--arkas-text-default', 'nilai' => '#2f3031', 'ket' => 'Teks utama'],
    ['nama' => '--arkas-text-subdued', 'nilai' => '#65696c', 'ket' => 'Teks sekunder'],
    ['nama' => '--arkas-border-subdued', 'nilai' => '#c9cbcf', 'ket' => 'Garis tabel/kartu'],
    ['nama' => '--arkas-surface-neutral-high', 'nilai' => '#efeff0', 'ket' => 'Kepala tabel'],
    ['nama' => '--arkas-background-default', 'nilai' => '#fafafa', 'ket' => 'Latar halaman'],
    ['nama' => '--arkas-success', 'nilai' => '#35825a', 'ket' => 'Berhasil / sudah selesai'],
    ['nama' => '--arkas-warning', 'nilai' => '#ffc453', 'ket' => 'Peringatan'],
    ['nama' => '--arkas-critical', 'nilai' => '#c82236', 'ket' => 'Kritis / hapus'],
  ];
@endphp

<div class="card border-l-4 border-indigo-600">
  <div class="font-bold text-slate-800 text-sm">🎨 Gaya antarmuka SiapBOSP</div>
  <p class="text-xs text-slate-500 mt-1">
    Warna, sudut, dan ketebalan huruf di halaman ini berasal dari
    <b>design token ARKAS Desktop 4.3.1</b> (<code class="text-[11px]">@wartek-id/design-tokens</code>)
    yang dibaca langsung dari bundel aplikasinya — bukan perkiraan dari tangkapan layar.
    Halaman ini untuk memeriksa hasilnya; tidak ada data sekolah di sini.
  </p>
</div>

{{-- ===== Warna ===== --}}
<div class="card">
  <div class="card-title mb-3">1. Token warna</div>
  <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
    @foreach($tokenWarna as $t)
      <div class="flex items-center gap-2.5 rounded-md border border-slate-200 p-2">
        <span class="h-8 w-8 shrink-0 rounded border border-slate-300" style="background: var({{ $t['nama'] }})"></span>
        <span class="min-w-0">
          <span class="block truncate text-[11px] font-semibold text-slate-700">{{ $t['nama'] }}</span>
          <span class="block text-[11px] text-slate-500">{{ $t['nilai'] }} · {{ $t['ket'] }}</span>
        </span>
      </div>
    @endforeach
  </div>
</div>

{{-- ===== Tipografi & sudut ===== --}}
<div class="grid gap-4 md:grid-cols-2">
  <div class="card">
    <div class="card-title mb-2">2. Tipografi</div>
    <p class="text-slate-800">ARKAS memakai <b>Inter</b>; SiapBOSP memakai Instrument Sans
      sebagai pengganti supaya tidak bergantung font berbayar/jaringan.</p>
    <div class="mt-2 space-y-1">
      <div class="text-lg font-semibold text-slate-800">Judul halaman · 18px / 600</div>
      <div class="text-sm font-semibold text-slate-700">Subjudul kartu · 14px / 600</div>
      <div class="text-[13px] text-slate-600">Isi tabel · 13px / 400</div>
      <div class="text-[11px] uppercase tracking-wide text-slate-500">Label kolom · 11px / 600</div>
    </div>
  </div>
  <div class="card">
    <div class="card-title mb-2">3. Sudut & bayangan</div>
    <p class="text-xs text-slate-500">ARKAS memakai sudut tegas 4px (0,25rem) untuk tombol/badge,
      8px untuk kartu, dan <b>tanpa bayangan</b> — pemisah memakai garis, bukan bayangan.</p>
    <div class="mt-3 flex flex-wrap items-center gap-3">
      <span class="inline-flex h-10 w-16 items-center justify-center border border-slate-300 bg-white text-[11px] text-slate-600" style="border-radius: var(--arkas-radius)">4px</span>
      <span class="inline-flex h-10 w-16 items-center justify-center border border-slate-300 bg-white text-[11px] text-slate-600 rounded-lg">6px</span>
      <span class="inline-flex h-10 w-16 items-center justify-center border border-slate-300 bg-white text-[11px] text-slate-600 rounded-xl">8px</span>
    </div>
  </div>
</div>

{{-- ===== Tombol ===== --}}
<div class="card">
  <div class="card-title mb-3">4. Tombol</div>
  <div class="flex flex-wrap items-center gap-2">
    <button type="button" class="btn">Simpan</button>
    <button type="button" class="btn2">Batal</button>
    <button type="button" class="btn-danger">Hapus</button>
    <button type="button" class="btn btn-sm">Simpan (kecil)</button>
    <button type="button" class="btn2 btn-sm">Batal (kecil)</button>
    <button type="button" class="btn" disabled>Nonaktif</button>
  </div>
  <p class="mt-2 text-[11px] text-slate-500">
    <code>.btn</code> memakai <code>--arkas-action</code>; hover <code>--arkas-action-hovered</code>;
    sudut <code>--arkas-radius</code>; tebal <code>--arkas-weight-action</code> (600).
  </p>
</div>

{{-- ===== Badge ===== --}}
<div class="card">
  <div class="card-title mb-3">5. Label status</div>
  <div class="flex flex-wrap items-center gap-2">
    <span class="badge bdan">Draf</span>
    <span class="badge bdel">Ditutup</span>
    <span class="badge bg-emerald-100 text-emerald-700">Sudah Selesai</span>
    <span class="badge bg-amber-100 text-amber-700">Belum Pajak</span>
    <span class="badge bg-slate-100 text-slate-600">Nonaktif</span>
  </div>
</div>

{{-- ===== Formulir ===== --}}
<div class="card">
  <div class="card-title mb-3">6. Formulir</div>
  <div class="grid gap-3 sm:grid-cols-3">
    <div>
      <label class="lbl" for="contoh-teks">Teks</label>
      <input id="contoh-teks" class="inp" value="BOS Reguler">
    </div>
    <div>
      <label class="lbl" for="contoh-pilih">Pilihan</label>
      <select id="contoh-pilih" class="inp">
        <option>BOS Reguler</option>
        <option>BOS Daerah</option>
      </select>
    </div>
    <div>
      <label class="lbl" for="contoh-tanggal">Tanggal</label>
      <input id="contoh-tanggal" type="date" class="inp" value="2026-10-28">
    </div>
  </div>
</div>

{{-- ===== Tabel ===== --}}
<div class="card">
  <div class="card-title mb-3">7. Tabel</div>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr><th>Tanggal</th><th>Uraian</th><th class="num">Penerimaan</th><th class="num">Pengeluaran</th><th class="num">Saldo</th></tr>
      </thead>
      <tbody>
        <tr><td>01-10-2026</td><td>Saldo awal bulan</td><td class="num"></td><td class="num"></td><td class="num">12.500.000</td></tr>
        <tr><td>05-10-2026</td><td>Pencairan BOS Reguler tahap 1</td><td class="num">50.000.000</td><td class="num"></td><td class="num">62.500.000</td></tr>
        <tr><td>09-10-2026</td><td>Belanja ATK</td><td class="num"></td><td class="num">1.250.000</td><td class="num">61.250.000</td></tr>
      </tbody>
      <tfoot>
        <tr><td colspan="2">Jumlah</td><td class="num">50.000.000</td><td class="num">1.250.000</td><td class="num">61.250.000</td></tr>
      </tfoot>
    </table>
  </div>
</div>

{{-- ===== Tab ===== --}}
<div class="card !p-0 overflow-hidden">
  <div class="flex flex-wrap items-center gap-1 border-b border-slate-200 px-3" role="tablist" aria-label="Contoh tab">
    <span role="tab" aria-selected="true" class="border-b-2 border-indigo-600 px-3.5 py-3 text-[13px] font-semibold text-indigo-700">BOSP Reguler</span>
    <span role="tab" aria-selected="false" class="border-b-2 border-transparent px-3.5 py-3 text-[13px] font-semibold text-slate-500">BOSP Daerah</span>
    <span role="tab" aria-selected="false" class="border-b-2 border-transparent px-3.5 py-3 text-[13px] font-semibold text-slate-500">SiLPA BOSP Kinerja</span>
  </div>
  <div class="p-4 text-xs text-slate-500">Isi tab. Garis bawah biru = tab aktif (pola ARKAS).</div>
</div>

{{-- ===== Daftar token ===== --}}
<div class="card">
  <div class="card-title mb-2">8. Daftar token yang dipakai</div>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Token</th><th>Nilai</th><th>Padanan token ARKAS 4.3.1</th></tr></thead>
      <tbody>
        <tr><td><code>--arkas-action</code></td><td>#0b5fef</td><td><code>--color-action-default</code></td></tr>
        <tr><td><code>--arkas-action-hovered</code></td><td>#054bcc</td><td><code>--color-action-hovered</code></td></tr>
        <tr><td><code>--arkas-action-pressed</code></td><td>#103178</td><td><code>--color-action-pressed</code></td></tr>
        <tr><td><code>--arkas-text-default</code></td><td>#2f3031</td><td><code>--color-text-default</code></td></tr>
        <tr><td><code>--arkas-text-subdued</code></td><td>#65696c</td><td><code>--color-text-subdued</code></td></tr>
        <tr><td><code>--arkas-surface-neutral-high</code></td><td>#efeff0</td><td><code>--color-surface-neutral-high</code></td></tr>
        <tr><td><code>--arkas-radius</code></td><td>0,25rem</td><td>sudut tombol/badge ARKAS (0,25–0,3rem)</td></tr>
        <tr><td><code>--arkas-weight-action</code></td><td>600</td><td><code>--font-weight-action</code></td></tr>
      </tbody>
    </table>
  </div>
</div>
@endsection
