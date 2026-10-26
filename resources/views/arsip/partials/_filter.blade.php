{{--
  Baris filter arsip (ala ARKAS 4: Format · Sumber dana · Tahun).

  Dipakai halaman arsip mana pun agar bentuknya sama. Semua pilihan bersifat
  OPSIONAL: halaman yang hanya punya satu format cukup mengirim $tahun dan
  $daftarTahun. Daftar tahun selalu berasal dari data + tahun sesi, bukan
  rentang yang dipaku di view — supaya tahun arsip lama tetap bisa dipilih.

  Parameter:
    $tahun         (int|string) tahun terpilih (wajib)
    $daftarTahun   (iterable)   daftar tahun yang bisa dipilih (wajib)
    $sumberFields  (iterable|null) null = sembunyikan select Sumber dana
    $sumber        (string)     nilai sumber terpilih
    $formatFields  (array|null) ['nama'=>..., 'nilai'=>..., 'pilihan'=>[kode=>label]]
--}}
@php
  $sumberFields = $sumberFields ?? null;
  $sumber = $sumber ?? '';
  $formatFields = $formatFields ?? null;
@endphp
<form method="get" class="flex flex-wrap gap-2 items-end">
  @if($formatFields)
    <div>
      <label class="lbl" for="arsip-format">{{ $formatFields['nama'] ?? 'Format' }}</label>
      <select id="arsip-format" name="format" class="inp !w-auto" data-kirim-otomatis>
        @foreach($formatFields['pilihan'] as $kode => $label)
          <option value="{{ $kode }}" @selected((string) ($formatFields['nilai'] ?? '') === (string) $kode)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
  @endif

  @if($sumberFields)
    <div>
      <label class="lbl" for="arsip-sumber">Sumber dana</label>
      <select id="arsip-sumber" name="sumber" class="inp !w-auto" data-kirim-otomatis>
        <option value="">Semua sumber dana</option>
        @foreach($sumberFields as $sd)
          <option value="{{ $sd }}" @selected($sumber === $sd)>{{ $sd }}</option>
        @endforeach
      </select>
    </div>
  @endif

  <div>
    <label class="lbl" for="arsip-tahun">Tahun</label>
    <select id="arsip-tahun" name="tahun" class="inp !w-auto" data-kirim-otomatis>
      @foreach($daftarTahun as $th)
        <option value="{{ $th }}" @selected((string) $tahun === (string) $th)>{{ $th }}</option>
      @endforeach
    </select>
  </div>
</form>
