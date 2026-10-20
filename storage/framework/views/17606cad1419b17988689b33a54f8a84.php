<?php $__env->startSection('content'); ?>
<?php echo $__env->make('laporan-arkas._header', ['judul' => 'Realisasi Anggaran — ' . ($namaBulan[$bulan] ?? '') . ' ' . $kop['tahun']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="card overflow-x-auto">
  <div class="flex gap-2 mb-2">
    <a href="<?php echo e(route('laporan-arkas.realisasi.csv', ['bulan' => $bulan])); ?>" class="btn2 btn text-sm">⬇ Ekspor CSV</a>
  </div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr>
      <th>Kode Rekening</th><th>Uraian</th><th>Komponen</th><th class="num">Jumlah Anggaran</th>
      <th class="num">Realisasi s.d. Bulan Lalu</th><th class="num">Realisasi Bulan Ini</th>
      <th class="num">Jumlah Realisasi s.d. Bulan Ini</th><th class="num">Selisih/ Kurang</th><th class="num">%</th>
    </tr></thead>
    <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <tr>
        <td class="font-mono text-xs whitespace-nowrap"><?php echo e($row['kode']); ?></td>
        <td><?php echo e($row['nama'] ?: '—'); ?></td>
        <td class="text-xs"><?php echo e($row['komponen'] ?: '—'); ?></td>
        <td class="num"><?php echo e(rp($row['ang'])); ?></td>
        <td class="num"><?php echo e(rp($row['lalu'])); ?></td>
        <td class="num <?php echo e($row['ini'] > 0 ? 'text-emerald-700 font-semibold' : 'text-slate-400'); ?>"><?php echo e(rp($row['ini'])); ?></td>
        <td class="num font-semibold"><?php echo e(rp($row['sd'])); ?></td>
        <td class="num <?php echo e($row['selisih'] < 0 ? 'text-rose-700' : ''); ?>"><?php echo e(rp($row['selisih'])); ?></td>
        <td class="num <?php echo e($row['persen'] >= 100 ? 'text-emerald-700' : ''); ?>"><?php echo e($row['ang'] > 0 ? $row['persen'].'%' : '—'); ?></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <tr><td colspan="9" class="text-center text-slate-400">Belum ada anggaran maupun belanja tahun ini.</td></tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr class="font-bold bg-slate-50"><td colspan="3" class="text-right">Total</td>
        <td class="num"><?php echo e(rp($total['ang'])); ?></td><td class="num"><?php echo e(rp($total['lalu'])); ?></td>
        <td class="num"><?php echo e(rp($total['ini'])); ?></td><td class="num"><?php echo e(rp($total['sd'])); ?></td>
        <td class="num"><?php echo e(rp($total['selisih'])); ?></td>
        <td class="num"><?php echo e($total['ang'] > 0 ? $total['persen'].'%' : '—'); ?></td></tr>
    </tfoot>
  </table></div>
</div>
<p class="text-xs text-slate-400 mt-1">Realisasi = belanja tercatat di kas (hasil impor ARKAS). Anggaran dari RKAS terakhir yang disahkan.</p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'Realisasi Anggaran'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/laporan-arkas/realisasi.blade.php ENDPATH**/ ?>