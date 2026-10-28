@extends('layouts.app', ['title' => 'Cek Ulang Integritas Impor'])
@section('subtitle', 'Bandingkan data ARKAS terbaru dengan pencatatan di SiapBOSP')
@section('content')
<div class="card max-w-3xl">
  <h3 class="card-title mb-1">🔍 Cek Ulang Integritas Impor</h3>
  <p class="page-desc mb-3">Dipakai setelah ARKAS digalakkan ulang (Pasca <b>Pergeseran / Perubahan RKAS</b>): data di ARKAS bisa berubah sementara pencatatan di SiapBOSP belum. Hasilnya: jumlah baris & nilai per bulan, plus daftar bulan yang berbeda.</p>

  @if(! empty($hasil))
    <div class="mb-4 rounded-lg border p-3 text-[13px] {{ $hasil['total']['bulan_berbeda'] === 0 ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-amber-50 border-amber-200 text-amber-800' }}">
      <b>{{ $hasil['total']['bulan_berbeda'] === 0 ? '✅ Semua bulan cocok' : '⚠️ '.$hasil['total']['bulan_berbeda'].' bulan berbeda' }}</b>
      — sumber: {{ $sumber }} · TA {{ $hasil['tahun'] }}
      <div class="mt-1 text-[12px]">
        ARKAS: {{ $hasil['total']['arkas_jumlah'] }} baris / {{ rp($hasil['total']['arkas_nilai']) }}
        · SiapBOSP: {{ $hasil['total']['lokal_jumlah'] }} baris / {{ rp($hasil['total']['lokal_nilai']) }}
        @if($hasil['total']['manual_jumlah'] > 0)
          · catatan lokal (manual): {{ $hasil['total']['manual_jumlah'] }} baris / {{ rp($hasil['total']['manual_nilai']) }}
        @endif
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="tbl">
        <thead><tr><th>Bulan</th><th class="num">ARKAS (baris)</th><th class="num">ARKAS (nilai)</th><th class="num">SiapBOSP (baris)</th><th class="num">SiapBOSP (nilai)</th><th class="num">Selisih</th><th>Status</th></tr></thead>
        <tbody>
          @foreach($hasil['bulan'] as $b)
            <tr>
              <td>{{ $b['nama'] }}</td>
              <td class="num">{{ $b['arkas']['jumlah'] }}</td>
              <td class="num">{{ rp($b['arkas']['nilai']) }}</td>
              <td class="num">{{ $b['lokal']['jumlah'] }}</td>
              <td class="num">{{ rp($b['lokal']['nilai']) }}</td>
              <td class="num {{ $b['status'] === 'beda' ? 'text-rose-700 font-semibold' : 'text-slate-400' }}">
                {{ $b['selisih_jumlah'] == 0 ? '0' : ($b['selisih_jumlah'] > 0 ? '+' : '').$b['selisih_jumlah'] }}
                @if($b['selisih_nilai'] != 0)
                  <div class="text-[11px]">{{ $b['selisih_nilai'] > 0 ? '+' : '−' }}{{ rp(abs($b['selisih_nilai'])) }}</div>
                @endif
              </td>
              <td>
                @if($b['status'] === 'cocok')
                  <span class="badge bg-emerald-100 text-emerald-700">✅ Cocok</span>
                @else
                  <span class="badge bg-amber-100 text-amber-700">⚠️ Beda</span>
                @endif
                @if($b['manual']['jumlah'] > 0)
                  <div class="text-[11px] text-slate-400 mt-1">{{ $b['manual']['jumlah'] }} catatan lokal</div>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <ul class="mt-3 text-[12px] text-slate-600 list-disc pl-5 space-y-0.5">
      @foreach($hasil['catatan'] as $c)<li>{!! nl2br(e($c)) !!}</li>@endforeach
    </ul>

    <div class="mt-3 flex gap-2">
      <a href="{{ route('impor-arkas.form') }}" class="btn2 btn">⬆️ Ke halaman Impor ARKAS</a>
      <a href="{{ route('impor-arkas.cek-integritas') }}" class="btn2 btn">🔄 Ulangi</a>
    </div>
  @else
    <form method="post" action="{{ route('impor-arkas.cek-integritas.jalankan') }}" enctype="multipart/form-data" class="space-y-3">
      @csrf
      <div class="flex flex-wrap items-end gap-3">
        <div>
          <label class="text-xs font-semibold text-slate-600">Tahun anggaran</label>
          <select name="tahun" class="inp mt-1">
            @for($y = min(2022, (int) $tahun); $y <= max(2028, (int) $tahun, (int) now()->year + 1); $y++)
              <option value="{{ $y }}" @selected($tahun === $y)>{{ $y }}</option>
            @endfor
          </select>
        </div>
        @if($portabel)
          <button class="btn">🖥️ Tarik dari ARKAS laptop ini & periksa</button>
        @endif
      </div>
      @unless($portabel)
        <div>
          <label class="text-xs font-semibold text-slate-600">Atau unggah file ekspor ARKAS (Excel .xlsx)</label>
          <input type="file" name="berkas" accept=".xlsx,.xls" class="inp mt-1">
          <div class="text-[11px] text-slate-500 mt-1">Di ARKAS: menu <b>Unduh Database</b> → simpan Excel → unggah di sini.</div>
        </div>
        <button class="btn">🔍 Periksa</button>
      @endunless
    </form>

    <div class="mt-4 text-[12px] text-slate-500 border-t border-slate-100 pt-3">
      <b>Cara memperbaiki bila ada selisih:</b> buka <b>Impor ARKAS</b> → tarik/unggah file ARKAS terbaru. Impor bersifat idempoten: data kembar diperbarui, tak ada yang dobel, dan tak ada yang terhapus.
      <div class="mt-1">Dasar: artikel resmi "Penanganan Kendala BOSP Reguler (TA 2025&2026) Terkait Ketidaksesuaian Antara Data Anggaran dan Realisasi Pasca Pergeseran / Perubahan".</div>
    </div>
  @endif
</div>
@endsection
