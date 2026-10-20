<?php $__env->startSection('content'); ?>
<?php echo $__env->make('laporan-arkas._header', ['judul' => 'Realisasi Penerimaan Dan Belanja — ' . ($namaBulan[$bulan] ?? '') . ' ' . $kop['tahun']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="card">
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr>
      <th>Uraian</th><th class="num">Jumlah Anggaran</th>
      <th class="num">Realisasi s/d Bulan Sebelumnya</th><th class="num">Realisasi Bulan Ini</th>
      <th class="num">Jumlah Realisasi s/d Bulan Ini</th><th class="num">Selisih/ Kurang</th>
    </tr></thead>
    <tbody>
      <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td><?php echo e($row['uraian']); ?></td>
        <td class="num"><?php echo e(rp($row['ang'])); ?></td>
        <td class="num"><?php echo e(rp($row['lalu'])); ?></td>
        <td class="num <?php echo e($row['ini'] > 0 ? 'text-emerald-700 font-semibold' : ''); ?>"><?php echo e(rp($row['ini'])); ?></td>
        <td class="num font-semibold"><?php echo e(rp($row['sd'])); ?></td>
        <td class="num <?php echo e($row['selisih'] < 0 ? 'text-rose-700' : ''); ?>"><?php echo e(rp($row['selisih'])); ?></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
    <tfoot>
      <tr class="font-bold bg-slate-50"><td class="text-right">Jumlah</td>
        <td class="num"><?php echo e(rp($total['ang'])); ?></td><td class="num"><?php echo e(rp($total['lalu'])); ?></td>
        <td class="num"><?php echo e(rp($total['ini'])); ?></td><td class="num"><?php echo e(rp($total['sd'])); ?></td>
        <td class="num"><?php echo e(rp($total['selisih'])); ?></td></tr>
    </tfoot>
  </table></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'Realisasi Penerimaan Dan Belanja'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/laporan-arkas/penerimaan-belanja.blade.php ENDPATH**/ ?>