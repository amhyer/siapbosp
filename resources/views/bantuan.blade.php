@extends('layouts.app', ['title' => 'Bantuan & Panduan'])
@section('subtitle', 'Panduan singkat penggunaan SiapBOSP dan letak menunya dibandingkan ARKAS 4')

@section('content')
<div class="card border-l-4 border-indigo-600">
  <div class="font-bold text-slate-800 text-sm">ⓘ Bantuan &amp; Panduan</div>
  <p class="text-xs text-slate-500 mt-1">
    Menu SiapBOSP disusun mengikuti <b>ARKAS 4</b>: tiga area kerja utama di sidebar
    (<b>Penganggaran</b>, <b>Penatausahaan</b>, <b>Arsip</b>), sedangkan data sekolah ada di
    <b>dropdown nama sekolah</b> di kanan atas — sama seperti menu <i>Profil</i> di ARKAS 4.
  </p>
</div>

<div class="grid gap-4 md:grid-cols-2">
  {{-- Padanan tugas ARKAS 4 -> menu SiapBOSP --}}
  <div class="card">
    <h3 class="font-semibold text-slate-700 text-sm mb-2">🧭 Kalau di ARKAS 4 Anda biasa membuka…</h3>
    <table class="tbl text-xs">
      <thead><tr><th>Tugas di ARKAS 4</th><th>Di SiapBOSP</th></tr></thead>
      <tbody>
        <tr><td>Aktivasi &amp; susun Kertas Kerja</td><td><a href="{{ route('rkas.index') }}" class="text-indigo-600 underline">Penganggaran → Kertas Kerja (RKAS)</a></td></tr>
        <tr><td>Review &amp; Grafik Proporsi</td><td><a href="{{ route('penganggaran.review') }}" class="text-indigo-600 underline">Penganggaran → Review &amp; Proporsi</a></td></tr>
        <tr><td>Catat pembelanjaan &amp; pajak</td><td><a href="{{ route('belanja.index') }}" class="text-indigo-600 underline">Penatausahaan → Pembelanjaan</a></td></tr>
        <tr><td>Buku Kas Umum &amp; Tutup BKU</td><td><a href="{{ route('bku') }}" class="text-indigo-600 underline">Penatausahaan → Buku Kas Umum</a></td></tr>
        <tr><td>Tarik / setor tunai</td><td><a href="{{ route('ambil.index') }}" class="text-indigo-600 underline">Penatausahaan → Tarik Tunai</a> · <a href="{{ route('mutasi.index') }}" class="text-indigo-600 underline">Setor Tunai</a></td></tr>
        <tr><td>Cetak RKAS / BKU yang sudah sah</td><td><a href="{{ route('arsip.rkas') }}" class="text-indigo-600 underline">Arsip → RKAS Disahkan</a> · <a href="{{ route('arsip.bku') }}" class="text-indigo-600 underline">BKU Ditutup</a></td></tr>
        <tr><td>Data Penanggung Jawab</td><td><a href="{{ route('penandatangan') }}" class="text-indigo-600 underline">Dropdown nama sekolah → Data Penanggung Jawab</a></td></tr>
      </tbody>
    </table>
  </div>

  {{-- Urutan kerja satu tahun anggaran --}}
  <div class="card">
    <h3 class="font-semibold text-slate-700 text-sm mb-2">📅 Urutan kerja satu tahun anggaran</h3>
    <ol class="list-decimal pl-5 space-y-1.5 text-xs text-slate-600">
      <li>Lengkapi <a href="{{ route('profil.edit') }}" class="text-indigo-600 underline">Profil Sekolah</a> dan <a href="{{ route('penandatangan') }}" class="text-indigo-600 underline">Data Penanggung Jawab</a>.</li>
      <li>Pastikan <a href="{{ route('datasekolah.sumber-dana') }}" class="text-indigo-600 underline">Sumber Dana</a> dan pagunya sudah benar.</li>
      <li>Susun <a href="{{ route('rkas.index') }}" class="text-indigo-600 underline">Kertas Kerja</a>; tombol <b>Ajukan</b> baru aktif setelah <i>Belum Dianggarkan</i> bernilai <b>Rp 0</b>.</li>
      <li>Setelah <b>Disahkan</b>, catat transaksi di <a href="{{ route('belanja.index') }}" class="text-indigo-600 underline">Pembelanjaan</a> dan <a href="{{ route('bku') }}" class="text-indigo-600 underline">BKU</a>.</li>
      <li>Tutup BKU tiap akhir bulan, lalu cetak laporan di <a href="{{ route('laporan.tahap') }}" class="text-indigo-600 underline">Laporan</a>.</li>
      <li>Dokumen yang sudah sah otomatis masuk <a href="{{ route('arsip.rkas') }}" class="text-indigo-600 underline">Arsip</a>.</li>
    </ol>
  </div>

  {{-- Aturan rincian (target kolom Panduan pada baris Kertas Kerja) --}}
  <div class="card" id="rincian" style="scroll-margin-top:6rem">
    <h3 class="font-semibold text-slate-700 text-sm mb-2">📘 Aturan menulis satu baris rincian</h3>
    <ul class="list-disc space-y-1.5 pl-5 text-xs text-slate-600">
      <li><b>Uraian maksimal 500 karakter</b> — lebih dari itu ditolak saat menyimpan.</li>
      <li><b>Kode rekening wajib</b> dipilih; baris tanpa rekening membuat pengajuan diblokir.</li>
      <li><b>Harga satuan</b> sebaiknya tidak melebihi standar harga (SSH/SBU) di
        <a href="{{ route('referensi.harga') }}" class="text-indigo-600 underline">Referensi Harga</a>.
        Kelebihan harga memunculkan peringatan, bukan blokir.</li>
      <li><b>Proporsi komponen</b> (buku, honor, sarpras) mengikuti Permendikdasmen No. 8 Tahun 2026 dan
        diperiksa di <a href="{{ route('penganggaran.review') }}" class="text-indigo-600 underline">Review &amp; Proporsi</a>.</li>
      <li><b>Belum Dianggarkan harus Rp 0</b> sebelum tombol <b>Ajukan</b> aktif.</li>
    </ul>
    <p class="mt-2 text-[11px] text-slate-400">
      Di ARKAS 4 aturan ini ditegakkan server Dinas; di SiapBOSP sebagian berupa peringatan agar sekolah tetap bisa bekerja offline.
    </p>
  </div>

  {{-- Dokumentasi teknis di folder aplikasi --}}
  <div class="card">
    <h3 class="font-semibold text-slate-700 text-sm mb-2">📚 Dokumentasi teknis (folder <code>docs/</code>)</h3>
    <ul class="space-y-1 text-xs text-slate-600">
      <li><code>docs/MASUKAN-ARKAS4-UNTUK-SIAPBOSP.md</code> — perbandingan menu &amp; tampilan dengan ARKAS 4.</li>
      <li><code>docs/GAMBARAN-ARKAS-4.md</code> — ringkasan alur ARKAS 4 dan keselarasannya.</li>
      <li><code>docs/AUDIT-TARIK-ARKAS.md</code> — catatan penarikan data dari database ARKAS.</li>
      <li><code>docs/IMPOR-ARKAS-EXCEL.md</code> — format berkas Excel/CSV untuk impor.</li>
      <li><code>launcher-windows/BACA-DULU.txt</code> — petunjuk pemasangan di laptop sekolah.</li>
    </ul>
    <p class="text-[11px] text-slate-400 mt-2">Berkas ini ada di folder aplikasi, tidak ditampilkan di dalam web.</p>
  </div>

  {{-- Versi & pembaruan (target tautan badge versi di topbar) --}}
  <div class="card" id="versi" style="scroll-margin-top:6rem">
    <h3 class="font-semibold text-slate-700 text-sm mb-2">↻ Versi &amp; pembaruan</h3>
    <dl class="grid grid-cols-[9rem_1fr] gap-y-1.5 text-xs">
      <dt class="text-slate-500">SiapBOSP</dt>
      <dd class="font-semibold">{{ config('app.version', 'v4.3.0') }}</dd>
      <dt class="text-slate-500">Acuan ARKAS</dt>
      <dd class="font-semibold">ARKAS 4 v4.3.0</dd>
      <dt class="text-slate-500">Juknis</dt>
      <dd class="font-semibold">Permendikdasmen No. 8 Tahun 2026</dd>
      <dt class="text-slate-500">Tahun anggaran aktif</dt>
      <dd class="font-semibold">{{ session('tahun', now()->year) }}</dd>
    </dl>
    <div class="mt-3 flex flex-wrap gap-2">
      <a href="{{ route('pengaturan.versi') }}" class="btn2 btn btn-sm">🔎 Deteksi Versi ARKAS</a>
      <a href="{{ route('pengaturan.log') }}" class="btn2 btn btn-sm">📋 Log Sistem</a>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="font-semibold text-slate-700 text-sm mb-1">🆘 Butuh bantuan lain?</h3>
  <p class="text-xs text-slate-500">
    Hubungi operator/bendahara sekolah atau Dinas Pendidikan setempat. Untuk masalah teknis
    (aplikasi tidak mau terbuka, tampilan tanpa warna, data tidak muncul), buka
    <a href="{{ route('pengaturan.log') }}" class="text-indigo-600 underline">Log Sistem</a> terlebih dahulu —
    pesan kesalahan di sana memudahkan penelusuran.
  </p>
</div>
@endsection
