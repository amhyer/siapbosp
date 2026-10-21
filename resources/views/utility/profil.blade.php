@extends('layouts.app', ['title' => 'Profil Sekolah'])
@section('content')
@if($tarik)
<div class="card max-w-2xl border-sky-200 !bg-sky-50/50">
  <h3 class="card-title mb-1">⚡ Tarik Data ARKAS — 1 Klik</h3>
  <p class="page-desc mb-3">Ambil data langsung dari aplikasi <b>ARKAS desktop</b> yang terpasang di laptop ini, lalu perbarui ke SiapBOSP. Aman diulang kapan saja — data kembar digabung, tidak jadi dobel, tidak ada yang terhapus.</p>
  @if(! $tarik['db_ada'])
    <div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2 mb-3">⚠️ Database ARKAS belum ditemukan di laptop ini. Buka aplikasi ARKAS sekali (unduh data sekolah di ARKAS), tutup lagi, lalu muat ulang halaman ini.
      <div class="text-[11px] text-slate-500 mt-1">Dicari di: <code class="bg-slate-100 px-1 rounded">{{ $tarik['path_db'] }}</code></div></div>
  @else
    @if($tarik['npsn'] && $tarik['npsn'] !== (string) $sekolah->npsn)
      <div class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg p-2 mb-3">🚫 Database ARKAS di laptop ini milik NPSN <b>{{ $tarik['npsn'] }}</b>, bukan NPSN sekolah ini (<b>{{ $sekolah->npsn }}</b>) — tarik ditolak agar data tidak tertukar. Samaikan NPSN di form Profil di bawah bila memang sekolah yang benar.</div>
    @else
      <div class="text-sm text-slate-600 mb-3">✅ Database ARKAS ditemukan{{ $tarik['npsn'] ? ' — NPSN '.$tarik['npsn'].' ' : ' ' }}di laptop ini, siap ditarik.</div>
      <div class="text-[12px] text-slate-500 mb-3">Catatan: tutup aplikasi ARKAS dulu bila database-nya terkunci. Hasil tarik bisa langsung dicek di <b>Pembukuan → BKU</b>.</div>
      <form method="post" action="{{ route('tarik-arkas.tarik') }}" class="flex flex-wrap items-end gap-2" onsubmit="const b=this.querySelector('button');b.disabled=true;b.textContent='⏳ Menarik data… (sampai 5 menit)';return true;">
        @csrf
        <div>
          <label class="lbl" for="tahun-tarik">Tahun anggaran</label>
          <select id="tahun-tarik" name="tahun" class="inp !w-auto">
            @for($y = 2022; $y <= 2028; $y++)
              <option value="{{ $y }}" @selected(((int) session('tahun', now()->year)) === $y)>{{ $y }}</option>
            @endfor
          </select>
        </div>
        <button class="btn">⬇️ Tarik Data Sekarang</button>
      </form>
    @endif
  @endif
  @if(session('tarik_laporan'))
    @php($lap = session('tarik_laporan'))
    <div class="mt-4 bg-white border border-emerald-200 rounded-lg p-3 text-[13px] text-slate-700">
      <div class="font-semibold text-emerald-700 mb-1">✅ Data ARKAS {{ $lap['tahun'] }} berhasil ditarik{{ ! empty($lap['npsn_arkas']) ? ' (NPSN '.$lap['npsn_arkas'].')' : '' }}</div>
      <ul class="list-disc pl-5 space-y-0.5">
        <li>Transaksi kas: <b>+{{ $lap['dibuat']['transaksi'] }}</b> baru, ~{{ $lap['diperbarui']['transaksi'] }} diperbarui{{ isset($lap['digabung']['transaksi']) ? ', '.$lap['digabung']['transaksi'].' digabung' : '' }} — dilewati {{ $lap['dilewati']['transaksi'] }}</li>
        <li>RKAS: <b>+{{ $lap['dibuat']['rkas'] }}</b> baru, ~{{ $lap['diperbarui']['rkas'] }} diperbarui — dilewati {{ $lap['dilewati']['rkas'] }}</li>
        <li>Pajak: diterima <b>+{{ $lap['dibuat']['pajak_terima'] }}</b>, disetor <b>+{{ $lap['dibuat']['pajak_setor'] }}</b> — dilewati {{ $lap['dilewati']['pajak'] }}</li>
        <li>Penjabaran +{{ $lap['dibuat']['penjab'] }}, pagu/alokasi +{{ $lap['dibuat']['alokasi'] }}</li>
      </ul>
      @if(! empty($lap['rekening_tak_dikenal']))
        <div class="mt-2 text-amber-700">⚠️ Kode rekening tak dikenal: {{ implode(', ', array_slice($lap['rekening_tak_dikenal'], 0, 10)) }}{{ count($lap['rekening_tak_dikenal']) > 10 ? ', …' : '' }} — minta Dinas menambahkannya di Master Rekening.</div>
      @endif
      @if(! empty($lap['peringatan']))
        <ul class="mt-2 list-disc pl-5 text-amber-700 space-y-0.5">@foreach($lap['peringatan'] as $p)<li>{{ $p }}</li>@endforeach</ul>
      @endif
      <div class="mt-2 text-[11px] text-slate-500">Cek hasilnya: <b>Pembukuan → BKU</b> · <b>Laporan → Realisasi</b>.</div>
    </div>
  @endif
</div>
@endif
<div class="card max-w-2xl">
  <form method="post" action="{{ route('profil.update') }}" class="grid sm:grid-cols-2 gap-4">
    @csrf @method('PUT')
    <div class="sm:col-span-2"><label class="text-xs font-semibold text-slate-600">NPSN</label>
      <input class="inp mt-1 bg-slate-50" value="{{ $sekolah->npsn }}" disabled></div>
    <div class="sm:col-span-2"><label class="text-xs font-semibold text-slate-600">Nama Sekolah</label>
      <input name="nama" class="inp mt-1" value="{{ old('nama', $sekolah->nama) }}" required></div>
    <div><label class="text-xs font-semibold text-slate-600">Jenjang</label>
      <select name="jenjang" class="inp mt-1">@foreach(['SD','SMP','SMA','SMK','SLB','TK','KB','TPA','SPS','PKBM','SKB'] as $j)<option @selected($sekolah->jenjang===$j)>{{ $j }}</option>@endforeach</select></div>
    <div><label class="text-xs font-semibold text-slate-600">Sifat Sekolah</label>
      <select name="sifat" class="inp mt-1">
        <option value="negeri" @selected(($sekolah->sifat ?? 'negeri') === 'negeri')>Negeri</option>
        <option value="swasta" @selected(($sekolah->sifat ?? 'negeri') === 'swasta')>Swasta</option>
      </select>
      <div class="text-[11px] text-slate-500 mt-1">Dipakai untuk batas komponen honor pada Cek Juknis 8/2026 (dasmen: 20% negeri / 40% swasta).</div>
    </div>
    <div><label class="text-xs font-semibold text-slate-600">Desa / Kelurahan</label>
      <input name="desa" class="inp mt-1" value="{{ old('desa', $sekolah->desa) }}"></div>
    <div><label class="text-xs font-semibold text-slate-600">Kecamatan</label>
      <input name="kecamatan" class="inp mt-1" value="{{ old('kecamatan', $sekolah->kecamatan) }}"></div>
    <div><label class="text-xs font-semibold text-slate-600">Kabupaten/Kota</label>
      <input name="kab_kota" class="inp mt-1" value="{{ old('kab_kota', $sekolah->kab_kota) }}"></div>
    <div><label class="text-xs font-semibold text-slate-600">Provinsi</label>
      <input name="provinsi" class="inp mt-1" value="{{ old('provinsi', $sekolah->provinsi) }}"></div>
    <div class="sm:col-span-2"><label class="text-xs font-semibold text-slate-600">Alamat</label>
      <input name="alamat" class="inp mt-1" value="{{ old('alamat', $sekolah->alamat) }}"></div>
    <div class="sm:col-span-2"><button class="btn">💾 Simpan Profil</button></div>
  </form>
</div>

<div class="card max-w-2xl mt-5">
  <h3 class="font-semibold text-slate-700 mb-1">🔑 Token API (Integrasi)</h3>
  <p class="text-xs text-slate-500 mb-3">Dipakai aplikasi lain untuk membaca data sekolah ini via <code class="bg-slate-100 px-1 rounded">/api/v1</code> (baca-saja). Simpan rahasia — siapa pun yang memegang token bisa membaca data Anda.</p>
  @if(session('token_baru'))
    <div class="bg-slate-900 text-emerald-300 font-mono text-xs rounded p-3 break-all select-all">{{ session('token_baru') }}</div>
    <div class="mt-2 text-[11px] font-semibold text-amber-600">⚠️ Salin sekarang — token hanya ditampilkan sekali ini. Nilainya disimpan ter-hash sehingga tidak bisa dilihat lagi nanti.</div>
    <div class="mt-3 text-[11px] text-slate-500">Contoh: <code class="bg-slate-100 px-1 rounded break-all">curl -H "Authorization: Bearer &lt;token&gt;" {{ url('/api/v1/saldo') }}</code></div>
  @elseif(auth()->user()->api_token)
    <div class="text-sm text-slate-600 mb-2">✅ Token aktif — nilai aslinya tersimpan ter-hash (terenkripsi satu arah) dan tidak dapat ditampilkan lagi. Pakai salinan yang Anda simpan saat membuat token.</div>
    <div class="mt-3 text-[11px] text-slate-500">Contoh: <code class="bg-slate-100 px-1 rounded break-all">curl -H "Authorization: Bearer &lt;token&gt;" {{ url('/api/v1/saldo') }}</code></div>
  @else
    <div class="text-sm text-slate-400 italic mb-3">Belum ada token. Buat satu untuk mulai integrasi.</div>
  @endif
  <form method="post" action="{{ route('api-token.store') }}" class="mt-3" onsubmit="return confirm('Buat token baru? Token lama akan langsung tidak berlaku.')">
    @csrf
    <button class="btn">♻️ {{ auth()->user()->api_token ? 'Buat Ulang Token' : 'Buat Token' }}</button>
  </form>
</div>
@endsection
