@extends('layouts.app', ['title' => 'Bantuan & Panduan'])
@section('subtitle', 'Panduan singkat penggunaan SiapBOSP dan letak menunya dibandingkan ARKAS 4')

@section('content')
<div class="card">
  <div class="flex items-center gap-2 text-sm font-bold text-[#2f3031]">
    <x-icon name="help" class="h-4 w-4 text-[#0b5fef]"/> Bantuan &amp; Panduan
  </div>
  <p class="mt-1 max-w-4xl text-xs text-[#65696c]">
    Menu SiapBOSP disusun mengikuti <b>ARKAS 4</b>: tiga area kerja utama di sidebar
    (<b>Penganggaran</b>, <b>Penatausahaan</b>, <b>Arsip</b>), sedangkan data sekolah ada di
    <b>dropdown nama sekolah</b> di kanan atas — sama seperti menu <i>Profil</i> di ARKAS 4.
  </p>
  <p class="text-xs text-slate-500 mt-2">
    Ingin melihat warna, tombol, dan tabel yang dipakai aplikasi?
    Buka <a class="font-semibold text-indigo-700 no-underline hover:underline" href="{{ route('gaya') }}">etalase gaya</a>
    — nilainya dibaca langsung dari design token ARKAS 4.3.1.
  </p>
</div>

<div class="grid gap-4 md:grid-cols-2">
  {{-- Padanan tugas ARKAS 4 -> menu SiapBOSP --}}
  <div class="card">
    <h3 class="card-title mb-2 flex items-center gap-2"><x-icon name="search" class="h-4 w-4 text-slate-500"/> Kalau di ARKAS 4 Anda biasa membuka…</h3>
    <div class="tbl-wrap">
    <table class="tbl text-xs">
      <thead><tr><th>Tugas di ARKAS 4</th><th>Di SiapBOSP</th></tr></thead>
      <tbody>
        <tr><td>Aktivasi &amp; susun Kertas Kerja</td><td><a href="{{ route('rkas.index') }}" class="link">Penganggaran → Kertas Kerja (RKAS)</a></td></tr>
        <tr><td>Review &amp; Grafik Proporsi</td><td><a href="{{ route('penganggaran.review') }}" class="link">Penganggaran → Review &amp; Proporsi</a></td></tr>
        <tr><td>Catat pembelanjaan &amp; pajak</td><td><a href="{{ route('belanja.index') }}" class="link">Penatausahaan → Pembelanjaan</a></td></tr>
        <tr><td>Buku Kas Umum &amp; Tutup BKU</td><td><a href="{{ route('bku') }}" class="link">Penatausahaan → Buku Kas Umum</a></td></tr>
        <tr><td>Tarik / setor tunai</td><td><a href="{{ route('ambil.index') }}" class="link">Penatausahaan → Tarik Tunai</a> · <a href="{{ route('mutasi.index') }}" class="link">Setor Tunai</a></td></tr>
        <tr><td>Cetak RKAS / BKU yang sudah sah</td><td><a href="{{ route('arsip.rkas') }}" class="link">Arsip → RKAS Disahkan</a> · <a href="{{ route('arsip.bku') }}" class="link">BKU Ditutup</a></td></tr>
        <tr><td>Data Penanggung Jawab</td><td><a href="{{ route('penandatangan') }}" class="link">Dropdown nama sekolah → Data Penanggung Jawab</a></td></tr>
      </tbody>
    </table>
    </div>
  </div>

  {{-- Urutan kerja satu tahun anggaran --}}
  <div class="card" id="tutorial" style="scroll-margin-top:6rem">
    <h3 class="card-title mb-2 flex items-center gap-2"><x-icon name="calendar" class="h-4 w-4 text-slate-500"/> Tutorial: urutan kerja satu tahun anggaran</h3>
    <ol class="list-decimal space-y-1.5 pl-5 text-xs text-[#65696c]">
      <li>Lengkapi <a href="{{ route('profil.edit') }}" class="link">Profil Sekolah</a> dan <a href="{{ route('penandatangan') }}" class="link">Data Penanggung Jawab</a>.</li>
      <li>Pastikan <a href="{{ route('datasekolah.sumber-dana') }}" class="link">Sumber Dana</a> dan pagunya sudah benar.</li>
      <li>Susun <a href="{{ route('rkas.index') }}" class="link">Kertas Kerja</a>; tombol <b>Ajukan</b> baru aktif setelah <i>Belum Dianggarkan</i> bernilai <b>Rp 0</b>.</li>
      <li>Setelah <b>Disahkan</b>, catat transaksi di <a href="{{ route('belanja.index') }}" class="link">Pembelanjaan</a> dan <a href="{{ route('bku') }}" class="link">BKU</a>.</li>
      <li>Tutup BKU tiap akhir bulan, lalu cetak laporan di <a href="{{ route('laporan.tahap') }}" class="link">Laporan</a>.</li>
      <li>Dokumen yang sudah sah otomatis masuk <a href="{{ route('arsip.rkas') }}" class="link">Arsip</a>.</li>
    </ol>
  </div>

  {{-- Aturan rincian (target kolom Panduan pada baris Kertas Kerja) --}}
  <div class="card" id="rincian" style="scroll-margin-top:6rem">
    <h3 class="card-title mb-2 flex items-center gap-2"><x-icon name="book" class="h-4 w-4 text-slate-500"/> Aturan menulis satu baris rincian</h3>
    <ul class="list-disc space-y-1.5 pl-5 text-xs text-[#65696c]">
      <li><b>Uraian maksimal 500 karakter</b> — lebih dari itu ditolak saat menyimpan.</li>
      <li><b>Kode rekening wajib</b> dipilih; baris tanpa rekening membuat pengajuan diblokir.</li>
      <li><b>Harga satuan</b> sebaiknya tidak melebihi standar harga (SSH/SBU) di
        <a href="{{ route('referensi.harga') }}" class="link">Referensi Harga</a>.
        Kelebihan harga memunculkan peringatan, bukan blokir.</li>
      <li><b>Proporsi komponen</b> (buku, honor, sarpras) mengikuti Permendikdasmen No. 8 Tahun 2026 dan
        diperiksa di <a href="{{ route('penganggaran.review') }}" class="link">Review &amp; Proporsi</a>.</li>
      <li><b>Belum Dianggarkan harus Rp 0</b> sebelum tombol <b>Ajukan</b> aktif.</li>
    </ul>
    <p class="mt-2 text-[11px] text-[#808489]">
      Di ARKAS 4 aturan ini ditegakkan server Dinas; di SiapBOSP sebagian berupa peringatan agar sekolah tetap bisa bekerja offline.
    </p>
  </div>

  {{-- Dokumentasi teknis di folder aplikasi --}}
  <div class="card">
    <h3 class="card-title mb-2 flex items-center gap-2"><x-icon name="folder" class="h-4 w-4 text-slate-500"/> Dokumentasi teknis (folder <code>docs/</code>)</h3>
    <ul class="space-y-1 text-xs text-[#65696c]">
      <li><code>docs/MASUKAN-ARKAS4-UNTUK-SIAPBOSP.md</code> — perbandingan menu &amp; tampilan dengan ARKAS 4.</li>
      <li><code>docs/GAMBARAN-ARKAS-4.md</code> — ringkasan alur ARKAS 4 dan keselarasannya.</li>
      <li><code>docs/AUDIT-TARIK-ARKAS.md</code> — catatan penarikan data dari database ARKAS.</li>
      <li><code>docs/IMPOR-ARKAS-EXCEL.md</code> — format berkas Excel/CSV untuk impor.</li>
      <li><code>launcher-windows/BACA-DULU.txt</code> — petunjuk pemasangan di laptop sekolah.</li>
    </ul>
    <p class="mt-2 text-[11px] text-[#808489]">Berkas ini ada di folder aplikasi, tidak ditampilkan di dalam web.</p>
  </div>

  {{-- Pertanyaan yang paling sering muncul di sekolah --}}
  <div class="card md:col-span-2" id="faq" style="scroll-margin-top:6rem">
    <h3 class="card-title mb-2 flex items-center gap-2"><x-icon name="help" class="h-4 w-4 text-slate-500"/> Pertanyaan umum</h3>
    <div class="space-y-2 text-xs text-[#65696c]">
      <details class="rounded border border-[#d9dbdd] bg-[#fafafa] p-2.5">
        <summary class="cursor-pointer font-semibold text-[#2f3031]">Apakah SiapBOSP harus selalu terhubung internet?</summary>
        <p class="mt-2 leading-relaxed">Tidak. Pencatatan, BKU, dan cetak laporan berjalan offline di laptop sekolah. Internet hanya dibutuhkan bila ingin mengirim data ke pusat atau memeriksa pembaruan aplikasi.</p>
      </details>
      <details class="rounded border border-[#d9dbdd] bg-[#fafafa] p-2.5">
        <summary class="cursor-pointer font-semibold text-[#2f3031]">Mengapa tombol <b>Ajukan</b> di Kertas Kerja belum aktif?</summary>
        <p class="mt-2 leading-relaxed">Masih ada baris berstatus <i>Belum Dianggarkan</i>. Lengkapi rekening dan nominalnya sampai nilai <b>Belum Dianggarkan Rp 0</b>, lalu ajukan kembali.</p>
      </details>
      <details class="rounded border border-[#d9dbdd] bg-[#fafafa] p-2.5">
        <summary class="cursor-pointer font-semibold text-[#2f3031]">Bagaimana memindahkan data dari ARKAS 4?</summary>
        <p class="mt-2 leading-relaxed">Gunakan <a href="{{ route('pengaturan.impor-database') }}" class="link">Impor Database (ARKAS)</a> untuk berkas Excel, atau <b>Sinkron ARKAS 1-klik</b> bila aplikasi dipasang portabel di laptop yang sama dengan ARKAS.</p>
      </details>
      <details class="rounded border border-[#d9dbdd] bg-[#fafafa] p-2.5">
        <summary class="cursor-pointer font-semibold text-[#2f3031]">Di mana mencetak format laporan yang lengkap?</summary>
        <p class="mt-2 leading-relaxed">Buka <a href="{{ route('laporan.format') }}" class="link">Pusat Format Laporan</a>. Semua format BKU, buku pembantu, rekap realisasi, SPTJM, dan LPJ dikumpulkan di satu halaman beserta pemilih periode.</p>
      </details>
      <details class="rounded border border-[#d9dbdd] bg-[#fafafa] p-2.5">
        <summary class="cursor-pointer font-semibold text-[#2f3031]">Mengapa angka pajak di laporan berbeda dengan catatan saya?</summary>
        <p class="mt-2 leading-relaxed">Periksa status setor pada menu <a href="{{ route('setorpajak.index') }}" class="link">Setor &amp; Lapor Pajak</a>. Pajak berstatus <i>belum setor</i> tetap muncul di Buku Pembantu Pajak, tetapi belum mengurangi kas bank.</p>
      </details>
    </div>
  </div>

  {{-- Versi & pembaruan (target tautan badge versi di topbar) --}}
  <div class="card" id="versi" style="scroll-margin-top:6rem">
    <h3 class="card-title mb-2 flex items-center gap-2"><x-icon name="refresh" class="h-4 w-4 text-slate-500"/> Versi &amp; pembaruan</h3>
    <dl class="grid grid-cols-[9rem_1fr] gap-y-1.5 text-xs">
      <dt class="text-[#65696c]">SiapBOSP</dt>
      <dd class="font-semibold">{{ config('app.version', 'v4.3.0') }}</dd>
      <dt class="text-[#65696c]">Acuan ARKAS</dt>
      <dd class="font-semibold">ARKAS 4 v{{ config('app.arkas_acuan', '4.3.0') }}</dd>
      <dt class="text-[#65696c]">Juknis</dt>
      <dd class="font-semibold">Permendikdasmen No. 8 Tahun 2026</dd>
      <dt class="text-[#65696c]">Tahun anggaran aktif</dt>
      <dd class="font-semibold">{{ session('tahun', now()->year) }}</dd>
    </dl>
    <div class="mt-3 flex flex-wrap gap-2">
      <a href="{{ route('pengaturan.versi') }}" class="btn2 btn-sm"><x-icon name="search" class="h-4 w-4"/> Deteksi Versi ARKAS</a>
      <a href="{{ route('pengaturan.log') }}" class="btn2 btn-sm"><x-icon name="file" class="h-4 w-4"/> Log Sistem</a>
    </div>
  </div>

  <div class="card" id="kontak" style="scroll-margin-top:6rem">
    <h3 class="card-title mb-1 flex items-center gap-2"><x-icon name="chat" class="h-4 w-4 text-slate-500"/> Hubungi kami</h3>
    <p class="text-xs leading-relaxed text-[#65696c]">
      Hubungi operator/bendahara sekolah atau Dinas Pendidikan setempat. Saat melapor, sertakan
      <b>NPSN</b>, <b>tahun anggaran</b>, dan <b>tangkapan layar</b> pesan kesalahan agar penelusuran lebih cepat.
    </p>
    <div class="mt-3 flex flex-wrap gap-2">
      <a href="{{ route('pengaturan.log') }}" class="btn2 btn-sm"><x-icon name="file" class="h-4 w-4"/> Buka Log Sistem</a>
      <a href="{{ route('pengaturan.versi') }}" class="btn2 btn-sm"><x-icon name="search" class="h-4 w-4"/> Cek Versi Aplikasi</a>
    </div>
  </div>
</div>
@endsection
