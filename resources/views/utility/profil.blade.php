@extends('layouts.app', ['title' => 'Profil Sekolah & Aktivasi ARKAS 4'])
@section('subtitle', 'Sinkronisasi identitas Dapodik, status Negeri/Swasta, Komite Sekolah, dan Pagu BOSP Reguler')
@section('content')
@php
  $namaBulan = \App\Services\JuknisBosp2026Service::NAMA_BULAN;
@endphp
<div class="grid lg:grid-cols-3 gap-5 items-start">
  <div class="card lg:col-span-2">
    <form method="post" action="{{ route('profil.update') }}" class="grid sm:grid-cols-2 gap-4">
      @csrf @method('PUT')
      <div>
        <label class="text-xs font-semibold text-slate-600">NPSN</label>
        <input class="inp mt-1 bg-slate-50" value="{{ $sekolah->npsn }}" disabled>
      </div>
      <div>
        <label class="text-xs font-semibold text-slate-600">Nama Satuan Pendidikan</label>
        <input name="nama" class="inp mt-1" value="{{ old('nama', $sekolah->nama) }}" required>
      </div>
      <div>
        <label class="text-xs font-semibold text-slate-600">Jenjang (Permendikdasmen No. 8/2026)</label>
        <select name="jenjang" class="inp mt-1">
          @foreach($jenjangList as $j)<option value="{{ $j }}" @selected($sekolah->jenjang===$j)>{{ $j }}</option>@endforeach
        </select>
      </div>
      <div>
        <label class="text-xs font-semibold text-slate-600">Status Penyelenggara (Penentu Batas Honor 20%/40%)</label>
        <select name="status_sekolah" class="inp mt-1">
          <option value="negeri" @selected(($sekolah->status_sekolah ?? 'negeri')==='negeri')>Negeri (Pemerintah Daerah)</option>
          <option value="swasta" @selected(($sekolah->status_sekolah ?? 'negeri')==='swasta')>Swasta / Masyarakat</option>
        </select>
      </div>

      <div class="sm:col-span-2 border-t border-slate-100 pt-3 font-semibold text-slate-700 text-xs uppercase tracking-wider">
        📋 Parameter Aktivasi Kertas Kerja ARKAS 4 (Data Dapodik & Komite Sekolah)
      </div>
      <div>
        <label class="text-xs font-semibold text-slate-600">Jumlah Peserta Didik Cut-Off Dapodik</label>
        <input type="number" min="0" name="jumlah_siswa" class="inp mt-1" value="{{ old('jumlah_siswa', $sekolah->jumlah_siswa ?? 0) }}">
        @unless($bolehSinkronPagu)<div class="text-[11px] text-slate-400 mt-1">Hanya sebagai rujukan; pagu resmi dari Dinas.</div>@endunless
      </div>
      <div>
        <label class="text-xs font-semibold text-slate-600">Biaya Satuan BOSP Reguler / Siswa (Rp)</label>
        <input type="number" min="0" name="satuan_biaya_bosp" class="inp mt-1" value="{{ old('satuan_biaya_bosp', $sekolah->satuan_biaya_bosp ?? 0) }}">
      </div>
      <div>
        <label class="text-xs font-semibold text-slate-600">Nama Ketua Komite Sekolah</label>
        <input name="nama_komite" class="inp mt-1" value="{{ old('nama_komite', $sekolah->nama_komite) }}" placeholder="Wajib diisi saat Aktivasi Kertas Kerja ARKAS 4">
      </div>
      <div>
        <label class="text-xs font-semibold text-slate-600">Email Aktif Komite Sekolah</label>
        <input type="email" name="email_komite" class="inp mt-1" value="{{ old('email_komite', $sekolah->email_komite) }}" placeholder="komite@sekolah.sch.id">
      </div>
      <div class="sm:col-span-2">
        <label class="text-xs font-semibold text-slate-600">Bulan Akhir Masa Jabatan Kepala Sekolah (Aturan ARKAS 4 Bab VI)</label>
        <select name="akhir_jabatan_bulan" class="inp mt-1">
          <option value="">— Aktif penuh s.d. akhir tahun anggaran (Desember) —</option>
          @foreach($namaBulan as $idx => $nb)
            <option value="{{ $idx }}" @selected((int)($sekolah->akhir_jabatan_bulan ?? 0) === $idx)>{{ $nb }} {{ $idx < 12 ? '(⚠️ Kepsek hanya boleh menyusun anggaran s.d. bulan ' . $nb . ')' : '' }}</option>
          @endforeach
        </select>
      </div>

      <div class="sm:col-span-2 border-t border-slate-100 pt-3 font-semibold text-slate-700 text-xs uppercase tracking-wider">
        📍 Domisili Satuan Pendidikan
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

      @if($bolehSinkronPagu)
        <div class="sm:col-span-2 flex items-center gap-2 text-xs text-slate-600">
          <input type="checkbox" id="sinkron_pagu" name="sinkron_pagu" value="1">
          <label for="sinkron_pagu">Sekaligus perbarui Pagu BOSP Reguler TA {{ $tahun }} dari hasil perkalian Siswa × Biaya Satuan</label>
        </div>
      @else
        <div class="sm:col-span-2 rounded-lg border border-slate-200 bg-slate-50 p-2 text-[11px] text-slate-500">
          🔒 Pagu anggaran ditetapkan Dinas melalui <b>Alokasi</b>; formulir ini hanya memperbarui identitas sekolah.
        </div>
      @endif
      <div class="sm:col-span-2"><button class="btn">💾 Simpan Profil & Parameter ARKAS 4</button></div>
    </form>
  </div>

  <div class="space-y-4">
    <div class="card border-l-4 border-indigo-600 space-y-2 text-xs">
      <div class="font-bold text-slate-800 text-sm">⚖️ Aturan Aktif Juknis BOSP 2026</div>
      <div class="text-slate-600">{{ $aturanJuknis['label_aturan'] }}</div>
      <div class="grid grid-cols-2 gap-2 pt-2">
        <div class="rounded bg-emerald-50 p-2">
          <div class="text-emerald-700">📚 Buku Minimal</div>
          <div class="font-bold text-base text-emerald-900">{{ $aturanJuknis['buku_min_pct'] }}%</div>
        </div>
        <div class="rounded bg-amber-50 p-2">
          <div class="text-amber-700">🛠️ Sarpras Maksimal</div>
          <div class="font-bold text-base text-amber-900">{{ $aturanJuknis['sarpras_max_pct'] }}%</div>
        </div>
        <div class="rounded bg-indigo-50 p-2">
          <div class="text-indigo-700">👩‍🏫 Honor Maksimal</div>
          <div class="font-bold text-base text-indigo-900">{{ $aturanJuknis['honor_max_pct'] }}%</div>
        </div>
        <div class="rounded bg-sky-50 p-2">
          <div class="text-sky-700">🗓️ Tahap 1 (Jan–Jun)</div>
          <div class="font-bold text-base text-sky-900">Maks {{ $aturanJuknis['tahap1_target_pct'] }}%</div>
        </div>
      </div>
      <div class="pt-2 text-slate-500">
        Pagu Aktif TA {{ $tahun }}: <b class="text-slate-800">{{ rp($paguAktif) }}</b>
        @if($sekolah->estimasiPaguReguler() > 0)
          <br>Estimasi Siswa × Satuan Biaya: <b>{{ rp($sekolah->estimasiPaguReguler()) }}</b>
        @endif
      </div>
      @if($sekolah->isPergantianKepsekMidYear())
        <div class="rounded border border-rose-200 bg-rose-50 p-2 text-rose-800">
          ⚠️ Masa jabatan Kepala Sekolah berakhir pada <b>{{ $namaBulan[$sekolah->akhir_jabatan_bulan] ?? '' }}</b> — sesuai ARKAS 4 Bab VI, anggaran bulan setelahnya disisakan untuk Kepala Sekolah pengganti.
        </div>
      @endif
    </div>

    @if($tarik)
    <div class="card border-l-4 border-indigo-600 scroll-mt-24" id="sinkron-arkas">
        <div class="font-semibold text-indigo-700">🔄 Sinkronisasi ARKAS</div>
        <div class="text-xs text-slate-500 mt-1">
            NPSN ARKAS: {{ $tarik['npsn'] ?? '—' }} · 
            DB: {{ $tarik['db_ada'] ? 'Ditemukan' : 'Tidak ditemukan' }} · 
            ARKAS: {{ ($tarik['arkas_berjalan'] ?? false) ? 'Berjalan' : 'Tertutup' }}
        </div>
        
        @if(! $tarik['db_ada'])
            <div class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2 mt-2">
                ⚠️ Database ARKAS belum ditemukan di laptop ini. 
                Buka aplikasi ARKAS sekali (unduh data sekolah di ARKAS), tutup lagi, lalu muat ulang halaman ini.
                <div class="text-[11px] text-slate-500 mt-1">
                    Dicari di: <code class="bg-slate-100 px-1 rounded">{{ $tarik['path_db'] }}</code>
                </div>
            </div>
        @elseif(($tarik['npsn'] ?? null) && $tarik['npsn'] !== (string) $sekolah->npsn)
            <div class="text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg p-2 mt-2">
                🚫 Database ARKAS di laptop ini milik NPSN <b>{{ $tarik['npsn'] }}</b>, 
                bukan NPSN sekolah ini (<b>{{ $sekolah->npsn }}</b>) — 
                sinkron ditolak agar data tidak tertukar. 
                Samaikan NPSN di Profil bila memang sekolah yang benar.
            </div>
        @elseif($tarik['arkas_berjalan'] ?? false)
            <div class="text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg p-2 mt-2">
                ⚠️ ARKAS Desktop sedang berjalan. 
                Tutup dulu aplikasi ARKAS, lalu coba lagi.
            </div>
        @else
            <form method="post" action="{{ route('tarik-arkas.tarik') }}" class="mt-3" data-tombol-sibuk="⏳ Sinkronisasi… (30-60 dtk)" data-konfirmasi="Pastikan ARKAS Desktop sudah ditutup. Lanjutkan sinkronisasi?">
                @csrf
                <input type="hidden" name="tahun" value="{{ session('tahun', now()->year) }}">
                <button class="btn text-sm">
                    🔄 Sinkron ARKAS Sekarang
                </button>
            </form>
            
            <div class="text-[10px] text-slate-400 mt-2">
                ⚠️ Pastikan ARKAS Desktop <b>tertutup</b> sebelum klik. Proses ~30-60 detik tergantung ukuran DB.
            </div>
        @endif

        @if(session('ok') && str_contains(session('ok'), 'Sinkron ARKAS'))
            <div class="mt-3 bg-emerald-50 border border-emerald-200 rounded-lg p-2 text-[12px] text-emerald-800">
                <div class="font-semibold text-emerald-700 mb-1">✅ Sinkron ARKAS selesai</div>
                <div class="text-emerald-700">{{ str_replace('Sinkron ARKAS selesai: ', '', session('ok')) }}</div>
            </div>
        @endif
    </div>
@endif

    <div class="card">
      <h3 class="font-semibold text-slate-700 mb-1 text-sm">🔑 Token API (Integrasi)</h3>
      <p class="text-xs text-slate-500 mb-3">Dipakai agen lokal / aplikasi pendamping untuk membaca data via <code class="bg-slate-100 px-1 rounded">/api/v1</code>.</p>
      @if(session('token_baru'))
        <div class="bg-slate-900 text-emerald-300 font-mono text-xs rounded p-3 break-all select-all">{{ session('token_baru') }}</div>
        <div class="mt-2 text-[11px] font-semibold text-amber-600">⚠️ Salin sekarang — token hanya ditampilkan sekali ini.</div>
      @elseif(auth()->user()->api_token)
        <div class="text-xs text-slate-600 mb-1">✅ Token aktif (tersimpan ter-hash).
          @if(auth()->user()->api_token_expires_at)
            Berlaku s.d. <b>{{ auth()->user()->api_token_expires_at->format('d/m/Y') }}</b>.
          @endif
          @if(auth()->user()->api_token_used_at)
            Terakhir dipakai {{ auth()->user()->api_token_used_at->diffForHumans() }}.
          @endif
        </div>
        @php($sisaHari = auth()->user()->api_token_expires_at?->diffInDays(now(), true))
        @if(auth()->user()->api_token_expires_at && auth()->user()->api_token_expires_at->isPast())
          <div class="text-xs font-semibold text-rose-600 mb-2">Token sudah kedaluwarsa — buat token baru.</div>
        @elseif($sisaHari !== null && $sisaHari <= 14)
          <div class="text-xs font-semibold text-amber-600 mb-2">Token akan kedaluwarsa dalam {{ (int) $sisaHari }} hari.</div>
        @endif
      @else
        <div class="text-xs text-slate-400 italic mb-3">Belum ada token.</div>
      @endif
      <div class="mt-3 flex flex-wrap gap-2">
        <form method="post" action="{{ route('api-token.store') }}" data-konfirmasi="Buat token baru? Token lama akan langsung tidak berlaku.">
          @csrf
          <button class="btn text-xs">♻️ {{ auth()->user()->api_token ? 'Buat Ulang Token' : 'Buat Token' }}</button>
        </form>
        @if(auth()->user()->api_token)
          <form method="post" action="{{ route('api-token.cabut') }}" data-konfirmasi="Cabut token API sekarang? Perangkat yang memakainya akan langsung kehilangan akses.">
            @csrf
            <button class="btn2 text-xs">🚫 Cabut Token</button>
          </form>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection