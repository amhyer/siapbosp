@extends('layouts.app', ['title' => 'Impor ARKAS'])
@section('subtitle', 'Tarik data dari laptop ARKAS — sekali klik, atau unggah manual')
@section('content')
<div class="card max-w-2xl border-emerald-200 !bg-emerald-50/50">
  <h3 class="card-title mb-1">💻 Opsi 0 — Aplikasi SiapBOSP di laptop (baru)</h3>
  <p class="page-desc mb-2">Pasang SiapBOSP di <b>laptop bendahara</b> — sudah berisi PHP & agen ARKAS (tak perlu install apa pun). Dua cara:</p>
  <div class="flex flex-wrap gap-2 mb-2">
    <a href="{{ route('unduh-pemasang') }}" class="btn">⬇️ Unduh Pemasang (EXE, ±77 MB)</a>
    <a href="{{ route('unduh-identitas') }}" class="btn2">⬇️ Unduh Identitas Sekolah (.conf)</a>
  </div>
  <p class="text-[13px] text-slate-600 mb-2"><b>Cara baru (disarankan):</b> simpan <b>kedua berkas di folder yang sama</b> (mis. <code class="font-mono">Downloads</code>) → klik 2× <code class="font-mono">SiapBOSP-Setup-v12.exe</code> → isi nama & kata sandi bendahara. Pemasang menyalin diri ke <code class="font-mono">%LocalAppData%\Programs\SiapBOSP</code>, membuat 3 pintasan Desktop, dan terdaftar di “Aplikasi &amp; fitur”.</p>
  <p class="text-[13px] text-slate-600 mb-2"><b>Cara lama (ZIP):</b> <a class="underline" href="{{ route('unduh-aplikasi') }}">⬇️ Unduh SiapBOSP Portable</a> → ekstrak → klik <code class="font-mono">PASANG-SIAPBOSP.bat</code> sekali.</p>
  <p class="text-[12px] text-amber-700">⚠️ Unduhan <b>Identitas</b> (dan ZIP) membuat <b>token API baru</b> — token lama (termasuk di agen TarikArkas) otomatis nonaktif. Pakai berkas hasil unduhan yang paling baru.</p>
</div>
<div class="card max-w-2xl border-indigo-200 !bg-indigo-50/50">
  <h3 class="card-title mb-1">🚀 Opsi 1 — Agen satu-klik (disarankan)</h3>
  <p class="page-desc mb-3">Unduh aplikasi kecil, jalankan di <b>laptop yang ada ARKAS-nya</b> — data langsung terkirim ke SiapBOSP tanpa ekspor manual.</p>
  <div class="flex flex-wrap gap-2 mb-3">
    <a href="{{ route('impor-arkas.unduhGui') }}" class="btn">⬇️ Unduh Aplikasi TarikArkas (±44 MB)</a>
    <a href="{{ route('impor-arkas.unduh') }}" class="btn2 btn-sm">versi teks (BAT)</a>
    <a href="{{ route('profil.edit') }}" class="btn2 btn-sm">🔑 Ambil Token di Profil</a>
  </div>
  <ol class="list-decimal pl-5 text-[13px] text-slate-600 space-y-1">
    <li>Ekstrak ZIP di laptop ARKAS, tutup aplikasi ARKAS.</li>
    <li>Klik 2x <code class="font-mono">TarikArkas-GUI.exe</code> → isi server + token + tahun → Tes Koneksi → Kirim. Agen memeriksa token milik NPSN yang sama dengan ARKAS laptop ini.</li>
  </ol>
</div>
<div class="card max-w-2xl">
  <h3 class="card-title mb-1">📤 Opsi 2 — Unggah Excel manual</h3>
  <p class="page-desc mb-3">Sheet yang dibaca: <b>Sekolah</b>, <b>RAPBS</b>, <b>KAS_Umum</b>, <b>Nota</b>, <b>Pajak_NTPN</b>. Maks 20 MB.</p>
  <form method="post" action="{{ route('impor-arkas.proses') }}" enctype="multipart/form-data" class="space-y-4">
    @csrf
    <div>
      <label class="lbl" for="berkas">Berkas Excel ARKAS (.xlsx)</label>
      <input id="berkas" type="file" name="berkas" accept=".xlsx" required class="inp">
    </div>
    <div>
      <label class="lbl" for="tahun">Tahun anggaran di file</label>
      <select id="tahun" name="tahun" class="inp !w-auto">
        @for($y = 2022; $y <= 2028; $y++) <option value="{{ $y }}" @selected($tahun === $y)>{{ $y }}</option> @endfor
      </select>
    </div>
    <button class="btn">⬆️ Proses Impor</button>
  </form>
</div>
<div class="card max-w-2xl">
  <h3 class="card-title mb-2">ℹ️ Aturan impor</h3>
  <ul class="list-disc pl-5 text-[13px] text-slate-600 space-y-1">
    <li>NPSN di data ARKAS harus sama dengan sekolah Anda — selain itu ditolak.</li>
    <li>Impor ulang hanya memperbarui (kunci <code class="font-mono">arkas_id</code>).</li>
    <li>Periode yang sudah tutup buku dilewati dan dilaporkan.</li>
    <li>Kode rekening yang belum ada di master dilewati dan dilaporkan — tambah dulu via Dinas, lalu kirim ulang.</li>
    <li>Tidak ada data yang dihapus oleh impor ini.</li>
  </ul>
</div>
@endsection
