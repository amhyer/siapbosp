@extends('layouts.app', ['title' => 'Pengaturan — Backup Data'])
@section('subtitle', 'Cadangan data sekolah: basis data + berkas konfigurasi')
@section('content')
<div class="card max-w-2xl"> <h3 class="card-title mb-3"> Backup Data Sekolah</h3> <p class="page-desc mb-3">Unduh salinan data aplikasi ini dalam satu berkas ZIP: <b>database.sqlite</b> (seluruh transaksi, RKAS, pajak, dan master), berkas konfigurasi launcher (token disamarkan), serta <b>MANIFEST.json</b> berisi ringkasan.</p> <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 text-[13px] mb-4"> <div class="font-semibold text-slate-700 mb-1">Cara memulihkan</div> <ol class="list-decimal pl-5 text-slate-600 space-y-0.5"> <li>Tutup aplikasi (server lokal dimatikan).</li> <li>Salin <code class="bg-white px-1 rounded">database.sqlite</code> dari berkas ZIP ke folder <code class="bg-white px-1 rounded">database/</code> aplikasi (ganti yang lama).</li> <li>Jalankan kembali aplikasi.</li> </ol> </div> @if($bolehUnduh)
    <a href="{{ route('pengaturan.backup.unduh') }}" class="btn"> Unduh Backup (.zip)</a> @else
    <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-[13px] text-amber-800"> Unduhan dump basis data penuh dinonaktifkan di server pusat karena isinya mencakup seluruh sekolah.
      Pencadangan server dilakukan oleh <b>Dinas (superadmin)</b>; pencadangan per sekolah dilakukan lewat
      aplikasi portabel di laptop masing-masing.
    </div> @endif

  <div class="mt-4 text-[12px] text-slate-500"> @if($ukuran)<div>Ukuran basis data saat ini: <b>{{ $ukuran }} MB</b></div>@endif
    @if($terakhir)<div>Backup terakhir: {{ $terakhir }}</div>@endif
  </div> <div class="mt-4 text-[11px] text-slate-400"> Pada website pusat (server sekolah bersama), pencadangan seluruh basis data dilakukan Dinas — <b>Data Sekolah → Backup</b> di menu Dinas. Backup per sekolah tersedia di aplikasi portabel di laptop masing-masing.
  </div>
</div>
@endsection
