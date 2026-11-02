@extends('layouts.app', ['title' => 'Kontrol Tahap Penyaluran'])
@section('content')
<div class="flex items-center justify-between flex-wrap gap-2"> <p class="text-sm text-slate-500">Buka/tutup tahap penyaluran tahun <b>{{ $tahun }}</b>; tahap yang ditutup <b>tidak bisa dipilih</b> sekolah saat mencatat pendapatan/belanja.</p> <form method="post" action="{{ route('dinas.tahap.buat') }}" class="flex items-end gap-2" data-konfirmasi="Buat paket tahap untuk tahun yang dipilih? Tahap yang sudah ada tidak akan digandakan."> @csrf
    <div> <label class="text-xs font-semibold text-slate-500">Buat paket tahap tahun</label> <select name="tahun" class="inp mt-1 !w-auto"> @for($y = now()->year - 1; $y <= now()->year + 2; $y++) <option value="{{ $y }}" @selected((int) session('tahun', now()->year) === $y)>{{ $y }}</option> @endfor
      </select> </div> <div> <label class="text-xs font-semibold text-slate-500">Jumlah tahap</label> <select name="jumlah" class="inp mt-1 !w-auto" title="Juknis BOSP TA 2026 mengikuti dua tahap salur (Permendikdasmen No 8/2026); model lama memakai 5 tahap"> @for($n = 2; $n <= 5; $n++) <option value="{{ $n }}" @selected($n === 5)>{{ $n }} tahap</option> @endfor
      </select> </div> <button class="btn text-xs"> Buat Paket Tahun</button> </form>
</div> <div class="card border-sky-200 bg-sky-50 text-xs text-sky-800 mb-4 space-y-1"> <p> <b>Juknis BOSP:</b> minimal <b>50% dana tersedia</b> dialokasikan pada <b>tahap pertama</b> — kartu cek di bawah memantau otomatis begitu paket tahap dibuat.</p> <p> <b>Jumlah tahap mengikuti ketentuan dinas/juknis tahun berjalan</b>: TA 2026 memakai <b>dua tahap salur</b> (Permendikdasmen No 8/2026); tahun sebelumnya memakai 5 tahap. Pilih jumlah saat membuat paket.</p>
</div>
@if($stages->isEmpty())
  <div class="card border border-amber-200 bg-amber-50 text-sm text-amber-700 mb-4"> Tahun <b>{{ $tahun }}</b> belum memiliki tahap. Gunakan formulir di atas untuk membuat paket tahap — setelah itu sekolah bisa mulai mencatat pada tahun tersebut.
  </div>
@endif
<div class="card overflow-x-auto max-w-2xl"> <table class="tbl"> <thead><tr><th>Tahun</th><th>Tahap</th><th>Urutan</th><th>Status</th><th></th></tr></thead> <tbody> @foreach($stages as $s)
      <tr> <td>{{ $s->tahun }}</td><td class="font-semibold">{{ $s->nama }}</td><td>#{{ $s->urutan }}</td> <td>{!! $s->is_open ? '<span class="badge bg-emerald-100 text-emerald-700"> BUKA</span>' : '<span class="badge bg-red-100 text-red-700"> TUTUP</span>' !!}</td> <td class="text-right"> <form method="post" action="{{ route('dinas.tahap.toggle', $s->id) }}">@csrf
            <button class="btn2 btn text-xs">{{ $s->is_open ? ' Tutup Tahap' : ' Buka Tahap' }}</button> </form> </td> </tr> @endforeach
    </tbody> </table>
</div>
@if($tahap1 && $cekJuknis->isNotEmpty())
<div class="card overflow-x-auto mt-4"> <h3 class="font-semibold text-slate-700 mb-1"> Cek juknis 50% — alokasi {{ $tahap1->nama }}</h3> <p class="text-xs text-slate-500 mb-2">Belanja tercatat pada {{ $tahap1->nama }} dibanding pagu (dana tersedia) RKAS aktif per sekolah — pengingat bantu, bukan blokir.</p> <table class="tbl"> <thead><tr><th>Sekolah</th><th class="num">Pagu (dana tersedia)</th><th class="num">Belanja {{ $tahap1->nama }}</th><th>Cakupan</th></tr></thead> <tbody> @foreach($cekJuknis as $c)
      <tr> <td>{{ $c->sekolah->nama }} <span class="text-xs text-slate-400">({{ $c->sekolah->npsn }})</span></td> <td class="num">{{ rp($c->pagu) }}</td> <td class="num">{{ rp($c->belanja1) }}</td> <td class="normal-case"> @if($c->persen === null)
            <span class="badge bg-slate-100 text-slate-500">pagu belum ada</span> @elseif($c->persen >= 50)
            <span class="badge bg-emerald-100 text-emerald-700"> {{ $c->persen }}%</span> @else
            <span class="badge bg-amber-100 text-amber-700"> {{ $c->persen }}% (target ≥ 50%)</span> @endif
        </td> </tr> @endforeach
    </tbody> </table>
</div>
@endif
@endsection
