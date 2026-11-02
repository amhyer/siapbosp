@extends('layouts.app', ['title' => 'Impor ARKAS'])
@section('subtitle', 'Unduh Aplikasi Desktop, atau unggah data ARKAS manual dari laptop')
@section('content')
@php
    $portabel = \App\Http\Controllers\UnduhAplikasiController::isPortable();
@endphp
@if(! $portabel)
<div class="card max-w-2xl border-emerald-200 !bg-emerald-50/50"> <h3 class="card-title mb-1"> Opsi 0 — Aplikasi Desktop (pintu unduh)</h3> <p class="page-desc mb-2">Pasang SiapBOSP di <b>laptop bendahara</b> — sudah berisi PHP &amp; agen ARKAS (tak perlu install apa pun). Penarikan data ARKAS dijalankan dari aplikasi ini; website pusat hanya menerima data.</p> <div class="flex flex-wrap gap-2 mb-2"> <a href="{{ route('unduh-pemasang') }}" class="btn"> Unduh Pemasang (EXE, ±77 MB)</a> <a href="{{ route('unduh-identitas') }}" class="btn2"> Unduh Identitas Sekolah (.conf)</a> </div> <p class="text-[13px] text-slate-600 mb-2"><b>Cara baru (disarankan):</b> simpan <b>kedua berkas di folder yang sama</b> (mis. <code class="font-mono">Downloads</code>) → klik 2× <code class="font-mono">SiapBOSP-Setup-v12.exe</code> → isi nama &amp; kata sandi bendahara. Pemasang menyalin diri ke <code class="font-mono">%LocalAppData%\Programs\SiapBOSP</code>, membuat pintasan Desktop + Start Menu, dan terdaftar di “Aplikasi &amp; fitur”.</p> <p class="text-[13px] text-slate-600 mb-2"><b>Cara lama (ZIP):</b> <a class="underline" href="{{ route('unduh-aplikasi') }}"> Unduh SiapBOSP Portable</a> → ekstrak → klik <code class="font-mono">PASANG-SIAPBOSP.bat</code> sekali.</p> <p class="text-[12px] text-amber-700"> Unduhan <b>Identitas</b> (dan ZIP) membuat <b>token API baru</b> — token lama (termasuk di agen TarikArkas) otomatis nonaktif. Pakai berkas hasil unduhan yang paling baru.</p>
</div>
@endif
<div class="card max-w-2xl border-indigo-200 !bg-indigo-50/50"> <h3 class="card-title mb-1"> Opsi 1 — Cara Penarikan Data ARKAS</h3> <p class="page-desc mb-3">Data ARKAS ditarik lewat <b>aplikasi desktop di laptop</b> — <b>bukan lewat website pusat</b>.</p> @if($portabel)
  <p class="text-[13px] text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg p-2 mb-3"> <b>Agen Tarik ARKAS sudah terbundel di laptop ini</b> — gunakan tombol <b>Tarik Data dari ARKAS</b> di sidebar aplikasi untuk menarik seluruh data dari database ARKAS Desktop ke aplikasi desktop ini sekaligus mengirim/sinkron otomatis ke website pusat (pastikan aplikasi ARKAS sudah ditutup).</p>
@else
  <div class="flex flex-wrap gap-2 mb-3"> <a href="{{ route('aplikasi-desktop') }}" class="btn"> Unduh Aplikasi Desktop</a> </div> <ol class="list-decimal pl-5 text-[13px] text-slate-600 space-y-1"> <li>Unduh &amp; pasang aplikasi SiapBOSP di laptop bendahara.</li> <li>Buka aplikasi → tombol <b>Tarik Data dari ARKAS</b> di sidebar → pastikan ARKAS Desktop <b>tertutup</b> → klik tombol.</li> </ol> @endif
</div>
@if($imporTerakhir)
<div class="card max-w-2xl border-sky-200 !bg-sky-50/50"> <h3 class="card-title mb-1">⏳ Impor terakhir</h3> <p class="page-desc mb-2">Tahun {{ $imporTerakhir->tahun }} · status: <b>{{ $imporTerakhir->status }}</b> · {{ $imporTerakhir->created_at->format('d/m/Y H:i') }}</p> <a href="{{ route('impor-arkas.status', $imporTerakhir) }}" class="btn2 btn-sm">Lihat status dan hasil</a>
</div>
@endif
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
        @for($y = min(2022, (int) $tahun); $y <= max(2028, (int) $tahun, (int) now()->year + 1); $y++) <option value="{{ $y }}" @selected($tahun === $y)>{{ $y }}</option> @endfor
      </select>
    </div>
    <button class="btn">⬆️ Proses Impor</button>
  </form>
</div>
<div class="card max-w-2xl border-slate-200"> <h3 class="card-title mb-1"> Pendamping ARKAS 4 (alat bantu)</h3> <p class="page-desc mb-2">Halaman HTML mandiri: checklist persiapan pra-input, rekap pagu–rencana–realisasi, <b>berita regulasi 2026</b> (Juknis 8/2026, BOSP Afirmasi, Permendikdasmen 15/2026, PPN 12%), dan kumpulan tautan panduan resmi ARKAS 4. Data tersimpan otomatis di browser perangkat Anda — tanpa internet, tanpa dikirim ke mana pun.</p> <a href="{{ asset('pendamping-arkas.html') }}" target="_blank" rel="noopener" class="btn2 btn-sm"> Buka Pendamping ARKAS 4 ↗</a>
</div>
<div class="card max-w-2xl border-amber-200 !bg-amber-50/40"> <h3 class="card-title mb-1"> Cek Ulang Integritas Impor</h3> <p class="page-desc mb-2">Setelah ARKAS digalakkan ulang (pasca <b>Pergeseran / Perubahan RKAS</b>), bandingkan data ARKAS terbaru dengan pencatatan di SiapBOSP per bulan. Tak mengubah apa pun — hanya laporan selisih.</p> <a href="{{ route('impor-arkas.cek-integritas') }}" class="btn2 btn"> Buka Cek Integritas</a>
</div>
<div class="card max-w-2xl"> <h3 class="card-title mb-2">ℹ Aturan impor</h3> <ul class="list-disc pl-5 text-[13px] text-slate-600 space-y-1"> <li>NPSN di data ARKAS harus sama dengan sekolah Anda — selain itu ditolak.</li> <li>Impor ulang hanya memperbarui (kunci <code class="font-mono">arkas_id</code>).</li> <li>Periode yang sudah tutup buku dilewati dan dilaporkan.</li> <li>Kode rekening yang belum ada di master dilewati dan dilaporkan — tambah dulu via Dinas, lalu kirim ulang.</li> <li>Tidak ada data yang dihapus oleh impor ini.</li> </ul>
</div>
@endsection
