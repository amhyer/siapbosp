@extends('layouts.app', ['title' => 'Setor Tunai'])
@section('content')
<div class="grid lg:grid-cols-3 gap-5">
<div class="card">
  <p class="text-xs text-slate-500 mb-3">Pencatatan setor uang tunai kembali ke rekening bank (Tunai → Bank) — dipakai bila tarik tunai berlebih. Sesuai histori setor di bank, seperti cara ARKAS.</p>
  <div class="flex justify-between text-xs text-slate-500"><span>Saldo Tunai tersedia:</span><b class="text-emerald-700">{{ rp($saldo['tunai']) }}</b></div>
  <form method="post" action="{{ route('mutasi.store') }}" class="space-y-3 mt-3">
    @csrf
    <div><label class="text-xs font-semibold text-slate-600">Tanggal</label><input type="date" name="tanggal" class="inp mt-1" value="{{ date('Y-m-d') }}" required></div>
    <div><label class="text-xs font-semibold text-slate-600">Nilai Mutasi (Rp)</label><input type="number" name="nilai" min="1" class="inp mt-1" max="{{ $saldo['tunai'] }}" required></div>
    <div><label class="text-xs font-semibold text-slate-600">Keterangan</label><input name="keterangan" class="inp mt-1" placeholder="mis. Setor sisa uang ke rekening" required></div>
    <button class="btn w-full justify-center">💾 Simpan</button>
  </form>
</div>
<div class="card lg:col-span-2">
  <h3 class="font-semibold text-slate-700 mb-3">Riwayat Setor Tunai {{ $tahun }}</h3>
  <table class="tbl">
    <thead><tr><th>BKU</th><th>Tanggal</th><th>Keterangan</th><th class="num">Nilai</th><th></th></tr></thead>
    <tbody>
      @forelse($items as $t)
      <tr><td>#{{ $t->no_bku }}</td><td>{{ $t->tanggal->format('d/m/Y') }}</td><td>{{ $t->keterangan }}</td>
        <td class="num">{{ rp($t->total) }}</td>
        <td><form method="post" action="{{ route('penerimaan.destroy',$t) }}" data-konfirmasi="Hapus?">@csrf @method('DELETE')<button class="badge bdel">✖</button></form></td></tr>
      @empty
      <tr><td colspan="5" class="text-center text-slate-400 py-6">Belum ada.</td></tr>
      @endforelse
    </tbody>
  </table>
  {{ $items->links() }}
</div>
</div>
@endsection
