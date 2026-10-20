<?php $__env->startSection('content'); ?>
<?php
  $terkunciBulanIni = in_array($bulan, $terkunciMap, true);
  $tglBulan = \Carbon\Carbon::parse($bulan.'-01');
  $serapan = $pagu > 0 ? min(100, round($belanjaTh / $pagu * 100)) : 0;
  $statusWarna = ['tertutup' => 'bg-rose-100 border-rose-300 text-rose-700',
    'selesai' => 'bg-slate-100 border-slate-300 text-slate-600',
    'draf' => 'bg-indigo-50 border-indigo-300 text-indigo-700',
    'tanpa-dana' => 'bg-emerald-50 border-emerald-200 text-emerald-600',
    'kosong' => 'bg-white border-slate-200 text-slate-400'];
  $statusTeks = ['tertutup' => '🔒 Tertutup', 'selesai' => 'Sudah Selesai',
    'draf' => 'Draf BKU', 'tanpa-dana' => 'Belum Ada Dana', 'kosong' => 'Belum Aktif'];
?>
<div class="flex flex-wrap justify-between items-center gap-2">
  <form class="flex gap-2 items-center text-sm">
    <span class="text-slate-500">Bulan:</span>
    <input type="month" name="bulan" value="<?php echo e($bulan); ?>" class="inp !w-auto" onchange="this.form.submit()">
  </form>
  <div class="flex gap-2">
    <?php if (! ($terkunciBulanIni)): ?>
    <form method="post" action="<?php echo e(route('bku.tutup')); ?>"
          onsubmit="return confirm('Tutup BKU <?php echo e($tglBulan->translatedFormat('F Y')); ?>? Semua catatan bulan ini akan terkunci (Kepala Sekolah yang boleh membuka kembali).')">
      <?php echo csrf_field(); ?> <input type="hidden" name="bulan" value="<?php echo e($bulan); ?>">
      <button class="btn bg-slate-700">🔒 Tutup BKU Bulan Ini</button>
    </form>
    <?php endif; ?>
    <a href="<?php echo e(route('bku.csv')); ?>?bulan=<?php echo e($bulan); ?>" class="btn2 btn">⬇ Ekspor CSV</a>
    <a href="<?php echo e(route('bku.cetak')); ?>?bulan=<?php echo e($bulan); ?>" target="_blank" class="btn2 btn">🖨️ Halaman Cetak</a>
    <?php $semSekarang = (int) $tglBulan->format('n') <= 6 ? 1 : 2 ?>
    <a href="<?php echo e(route('pembukuan.bukuPembantu', ['semester' => $semSekarang])); ?>" target="_blank" class="btn2 btn">📚 Buku Pembantu (Semester <?php echo e($semSekarang); ?>)</a>
    <a href="<?php echo e(route('pembukuan.rekapRealisasi', ['semester' => $semSekarang])); ?>" target="_blank" class="btn2 btn">📊 Rekap Realisasi (Semester <?php echo e($semSekarang); ?>)</a>
    <a href="<?php echo e(route('laporan-arkas.kas-umum', ['bulan' => (int) $tglBulan->format('n')])); ?>" target="_blank" class="btn2 btn">🔎 Detail Nota: Daftar Kas Umum</a>
  </div>
</div>

<?php if($terkunciBulanIni): ?>
<div class="card !border-2 !border-rose-300 bg-rose-50 flex flex-wrap items-center justify-between gap-2">
  <div>
    <div class="font-bold text-rose-800">🔒 BKU <?php echo e($tglBulan->translatedFormat('F Y')); ?> — TERTUTUP</div>
    <div class="text-xs text-rose-700">Semua pencatatan, pengubahan, dan penghapusan transaksi bulan ini terkunci — sama seperti BKU bulanan ARKAS.</div>
  </div>
  <?php if($bisaBuka): ?>
  <form method="post" action="<?php echo e(route('bku.buka')); ?>">
    <?php echo csrf_field(); ?> <input type="hidden" name="bulan" value="<?php echo e($bulan); ?>">
    <button class="btn bg-slate-600 !bg-slate-600">🔓 Buka Kembali</button>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>


<div class="card">
  <div class="flex items-center justify-between mb-3 flex-wrap gap-1">
    <div class="font-semibold text-slate-700">📊 Kontrol Dana Tahun <?php echo e($tahun); ?></div>
    <div class="text-xs text-slate-500">Sebagaimana panel BKU di ARKAS 4</div>
  </div>
  <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 text-center">
    <div class="rounded-lg bg-amber-50 border border-amber-200 p-2">
      <div class="text-[11px] text-amber-700">🪙 Saldo Tunai</div>
      <div class="font-bold text-sm"><?php echo e(rp($saldoSekarang['tunai'])); ?></div>
    </div>
    <div class="rounded-lg bg-blue-50 border border-blue-200 p-2">
      <div class="text-[11px] text-blue-700">🏦 Saldo Bank</div>
      <div class="font-bold text-sm"><?php echo e(rp($saldoSekarang['bank'])); ?></div>
    </div>
    <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-2">
      <div class="text-[11px] text-emerald-700">💵 Dana Tersedia</div>
      <div class="font-bold text-sm"><?php echo e(rp($danaTersedia)); ?></div>
      <div class="text-[10px] text-emerald-600">SiLPA <?php echo e(rp($silpaTahunLalu)); ?> + salur baru <?php echo e(rp($pendapatanTh)); ?></div>
    </div>
    <div class="rounded-lg bg-rose-50 border border-rose-200 p-2">
      <div class="text-[11px] text-rose-700">🛒 Sudah Dibelanjakan</div>
      <div class="font-bold text-sm"><?php echo e(rp($belanjaTh)); ?></div>
    </div>
    <div class="rounded-lg bg-indigo-50 border border-indigo-200 p-2">
      <div class="text-[11px] text-indigo-700">♻️ Bisa Dianggarkan Ulang</div>
      <div class="font-bold text-sm"><?php echo e(rp($bisaDianggarkanUlang)); ?></div>
      <div class="text-[10px] text-indigo-600">sisa pagu <?php echo e(rp($pagu)); ?></div>
    </div>
    <div class="rounded-lg bg-slate-50 border border-slate-200 p-2 col-span-2 lg:col-span-1">
      <div class="text-[11px] text-slate-600">Total Kas Sekarang</div>
      <div class="font-bold text-sm"><?php echo e(rp($saldoSekarang['total'])); ?></div>
    </div>
  </div>
  <div class="mt-3">
    <div class="flex justify-between text-[11px] text-slate-500 mb-1">
      <span>Serapan anggaran (pagu vs belanja)</span><span><?php echo e($serapan); ?>%</span>
    </div>
    <div class="h-2 rounded-full bg-slate-200 overflow-hidden">
      <div class="h-full bg-gradient-to-r from-indigo-500 to-emerald-500" style="width: <?php echo e($serapan); ?>%"></div>
    </div>
  </div>
</div>


<div class="card">
  <div class="font-semibold text-slate-700 mb-2 text-sm">🗓️ Status BKU per Bulan <?php echo e($tahun); ?> <span class="text-xs text-slate-400 font-normal">— klik bulan untuk membukanya</span></div>
  <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-2">
    <?php $__currentLoopData = $bulanGrid; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <a href="<?php echo e(route('bku', ['bulan' => $b['ym']])); ?>"
       class="border-2 rounded-lg p-2 text-center block hover:shadow transition <?php echo e($statusWarna[$b['status']]); ?> <?php if($b['now']): ?> ring-2 ring-indigo-400 <?php endif; ?>"
       <?php if($b['status'] === 'tertutup'): ?> title="BKU bulan ini sudah ditutup" <?php endif; ?>>
      <div class="text-xs font-semibold"><?php echo e($b['nama']); ?><?php echo e($b['now'] ? ' • bulan ini' : ''); ?></div>
      <div class="text-[10px] mt-0.5"><?php echo e($statusTeks[$b['status']]); ?></div>
      <?php if($b['n']): ?>
      <div class="text-[10px] mt-0.5 opacity-80">↑<?php echo e(rp($b['masuk'])); ?> ↓<?php echo e(rp($b['keluar'])); ?></div>
      <?php endif; ?>
    </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</div>

<div class="card overflow-x-auto">
  <div class="text-center mb-4">
    <div class="font-bold">Buku Kas Umum</div>
    <div class="text-sm"><?php echo e(auth()->user()->school->nama); ?> — <?php echo e(\Carbon\Carbon::parse($bulan)->translatedFormat('F Y')); ?></div>
  </div>
  <table class="tbl">
    <thead><tr><th>Tanggal</th><th>Kegiatan</th><th>Rekening</th><th>No. Bukti</th><th>Uraian</th><th class="num">Penerimaan</th><th class="num">Pengeluaran</th><th class="num">Saldo</th></tr></thead>
    <tbody>
      <tr class="font-semibold bg-slate-50"><td colspan="6">Saldo Awal Bulan</td><td class="num"></td><td class="num"><?php echo e(rp($saldoAwal)); ?></td></tr>
      <?php $saldo = $saldoAwal; $tm=0; $tk=0; ?>
      <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php
        $saldo += $row['masuk'] - $row['keluar']; $tm += $row['masuk']; $tk += $row['keluar']; $t=$row['trx'];
        $keg = ($t->details->first(fn($d) => $d->kode_kegiatan) ?? $t->details->first())?->kode_kegiatan ?? '';
        $kegLabel = $keg !== '' ? trim($keg . ' ' . ($kodeNama[$keg] ?? '')) : '—';
        $rek = $t->details->map(fn($d) => $d->rekening->kode ?? '')->filter()->unique()->implode(', ');
      ?>
      <tr>
        <td class="whitespace-nowrap"><?php echo e($t->tanggal->format('d-m-Y')); ?></td>
        <td class="text-xs"><?php echo e($kegLabel); ?></td>
        <td class="font-mono text-xs whitespace-nowrap"><?php echo e($rek ?: '—'); ?></td>
        <td class="whitespace-nowrap"><?php echo e($t->arkas_bku ?: ($t->no_bku ? 'BKU#'.$t->no_bku : '—')); ?></td>
        <td><?php echo e($t->keterangan); ?></td>
        <td class="num text-emerald-700"><?php echo e($row['masuk'] ? rp($row['masuk']) : ''); ?></td>
        <td class="num text-rose-700"><?php echo e($row['keluar'] ? rp($row['keluar']) : ''); ?></td>
        <td class="num font-semibold"><?php echo e(rp($saldo)); ?></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
    <tfoot>
      <tr class="font-bold"><td colspan="5" class="text-right">Jumlah</td><td class="num"><?php echo e(rp($tm)); ?></td><td class="num"><?php echo e(rp($tk)); ?></td><td class="num"></td></tr>
      <tr class="font-bold bg-slate-50"><td colspan="6" class="text-right">Saldo Buku Kas Umum</td><td class="num"></td><td class="num"><?php echo e(rp($saldo)); ?></td></tr>
    </tfoot>
  </table>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'Buku Kas Umum (BKU)'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/pembukuan/bku.blade.php ENDPATH**/ ?>