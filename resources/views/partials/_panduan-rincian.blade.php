{{--
  Panduan per baris rincian Kertas Kerja (P2).

  Satu tombol kecil 📖 yang membuka tautan acuan untuk rekening pada baris itu:
  aturan uraian Juknis, standar harga (SSH/SBU) rekening yang sama, dan halaman
  review proporsi. Ditulis sekali di sini supaya tabel rincian mana pun cukup
  menulis satu @include.

  Parameter: $kode = kode rekening baris (string, boleh kosong)
--}}
@php($kodeRekening = trim((string) ($kode ?? '')))
<details class="relative">
  <summary class="badge bg-slate-100 text-slate-600 cursor-pointer list-none" title="Panduan &amp; acuan baris ini">📖</summary>
  <div class="absolute right-0 z-40 mt-1 w-64 space-y-1 rounded-xl border border-slate-200 bg-white p-2 text-xs shadow-xl">
    <div class="font-bold text-slate-700">Panduan {{ $kodeRekening !== '' ? 'rekening ' . $kodeRekening : 'baris ini' }}</div>
    <a href="{{ route('bantuan') }}#rincian" class="block rounded-lg px-2 py-1 text-slate-600 hover:bg-slate-50">📘 Aturan uraian &amp; proporsi</a>
    @if($kodeRekening !== '')
      <a href="{{ route('referensi.harga', ['q' => $kodeRekening]) }}" class="block rounded-lg px-2 py-1 text-slate-600 hover:bg-slate-50">🏷️ Standar harga rekening ini</a>
    @else
      <a href="{{ route('referensi.harga') }}" class="block rounded-lg px-2 py-1 text-slate-600 hover:bg-slate-50">🏷️ Standar harga (SSH/SBU)</a>
    @endif
    <a href="{{ route('penganggaran.review') }}" class="block rounded-lg px-2 py-1 text-slate-600 hover:bg-slate-50">📊 Review &amp; proporsi Juknis</a>
  </div>
</details>
