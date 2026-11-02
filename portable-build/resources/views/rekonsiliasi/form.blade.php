@extends('layouts.app', ['title' => 'Rekonsiliasi ARKAS'])
@section('subtitle', 'Bandingkan file ARKAS dengan data SiapBOS — tanpa mengubah data')
@section('content')
<div class="card max-w-2xl">
  <form method="post" action="{{ route('rekonsiliasi.proses') }}" enctype="multipart/form-data" class="space-y-4">
    @csrf
    <div>
      <label class="lbl" for="berkas">Berkas Excel ARKAS (.xlsx)</label>
      <input id="berkas" type="file" name="berkas" accept=".xlsx" required class="inp">
    </div>
    <div>
      <label class="lbl" for="tahun">Tahun</label>
      <select id="tahun" name="tahun" class="inp !w-auto">
        @for($y = min(2022, (int) $tahun); $y <= max(2028, (int) $tahun, (int) now()->year + 1); $y++) <option value="{{ $y }}" @selected($tahun === $y)>{{ $y }}</option> @endfor
      </select>
    </div>
    <button class="btn">🔍 Bandingkan</button>
  </form>
</div>
<div class="card max-w-2xl"> <h3 class="card-title mb-2">ℹ Cara baca hasil</h3> <ul class="list-disc pl-5 text-[13px] text-slate-600 space-y-1"> <li><b>Cocok</b>: baris ARKAS ada di SiapBOS dengan nominal sama (kunci id/tanda digital).</li> <li><b>Selisih nominal</b>: baris ketemu tapi nilainya beda — periksa.</li> <li><b>Hilang/Lebih</b>: ada di satu sisi saja.</li> <li><b>Peta BKU</b>: nomor BKU SiapBOS ↔ nomor BKU ARKAS per transaksi cocok.</li> </ul>
</div>
@endsection
