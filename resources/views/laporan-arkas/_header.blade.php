@php
  $bnya = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
@endphp
<div class="flex flex-wrap justify-between items-end gap-2 mb-3">
  <div>
    <div class="font-bold text-[15px]">{{ $judul }}</div>
    <div class="text-[12px] text-slate-500">{{ auth()->user()->school->nama }} — {{ $kop['sumber'] . $kop['revisiLabel'] }} — Tahun Anggaran {{ $kop['tahun'] }}</div>
  </div>
  @if(isset($periode) && $periode === 'semester')
  <form method="get" class="flex gap-2 items-center text-sm">
    <input type="hidden" name="periode" value="semester">
    <span class="text-slate-500">Semester:</span>
    <select name="semester" class="inp !w-auto" data-kirim-otomatis>
      <option value="1" @selected((int) ($semester ?? 1) === 1)>Semester 1 (Januari–Juni)</option>
      <option value="2" @selected((int) ($semester ?? 1) === 2)>Semester 2 (Juli–Desember)</option>
    </select>
  </form>
  @elseif(isset($bulan) && $bulan)
  <form method="get" class="flex gap-2 items-center text-sm">
    <span class="text-slate-500">{{ $pilihLabel ?? 'Bulan' }}:</span>
    <select name="bulan" class="inp !w-auto" data-kirim-otomatis>
      @foreach($namaBulan as $n => $nm) <option value="{{ $n }}" @selected($n == $bulan)>{{ $nm }} {{ $kop['tahun'] }}</option> @endforeach
    </select>
  </form>
  @endif
</div>
