@extends('layouts.app', ['title' => 'Hapus Data Sekolah'])
@section('subtitle', 'Reset seluruh data transaksional — khusus non-Kepala Sekolah')
@section('content')
<div class="card max-w-2xl border-rose-200">
  <h3 class="card-title mb-2">🗑️ Hapus seluruh data {{ $sekolah->nama }}</h3>
  <p class="page-desc mb-3">Tindakan ini <b>tidak bisa dibatalkan</b>. Yang dihapus: {{ $ringkas['transaksi'] }} transaksi kas (+rincian, pajak, berkas lampiran), {{ $ringkas['rkas'] }} RKAS, {{ $ringkas['tutup_buku'] }} tutup buku, {{ $ringkas['lpj'] }} pengajuan LPJ, dan log aktivitas sekolah.</p>
  <p class="page-desc mb-3">Yang <b>tetap aman</b>: profil sekolah, akun pengguna, penandatangan, dan pagu/alokasi.</p>
  <form method="post" action="{{ route('hapusdata.proses') }}" data-konfirmasi="YAKIN menghapus seluruh data? Tindakan ini permanen." class="space-y-4">
    @csrf @method('DELETE')
    <div>
      <label class="lbl" for="konfirmasi">Ketik NPSN <code class="font-mono">{{ $sekolah->npsn }}</code> untuk konfirmasi</label>
      <input id="konfirmasi" name="konfirmasi" class="inp font-mono" placeholder="{{ $sekolah->npsn }}" required autocomplete="off">
    </div>
    <button class="btn-danger">🗑️ Ya, Hapus Semua Data</button>
    <a href="{{ route('dashboard') }}" class="btn2 btn-sm">Batal</a>
  </form>
</div>
@endsection
