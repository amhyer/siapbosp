<?php $__env->startSection('content'); ?>
<?php echo $__env->make('laporan-arkas._header', ['judul' => 'Rencana Kegiatan Anggaran Sekolah (RKAS) - Bulanan'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="card overflow-x-auto">
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr>
      <th>No</th><th>Program</th><th>Kegiatan</th><th>Rekening Belanja</th><th>Uraian</th>
      <th class="num">Anggaran</th><th class="num">Belanja</th><th>Satuan</th><th class="num">Harga Satuan</th>
      <th class="num">Total</th><th>Status - Bulan</th>
    </tr></thead>
    <tbody>
      <?php $ta=0; $tb=0; ?>
      <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <?php $ta += $row['anggaran']; $tb += $row['belanja']; ?>
      <tr>
        <td><?php echo e($row['no']); ?></td>
        <td class="text-xs"><?php echo e($row['program']); ?></td>
        <td class="text-xs"><?php echo e($row['kegiatan']); ?></td>
        <td class="font-mono text-xs whitespace-nowrap"><?php echo e($row['rek']); ?></td>
        <td><?php echo e(\Illuminate\Support\Str::limit($row['uraian'], 60)); ?></td>
        <td class="num"><?php echo e(rp($row['anggaran'])); ?></td>
        <td class="num <?php echo e($row['belanja'] > 0 ? 'text-emerald-700 font-semibold' : 'text-slate-400'); ?>"><?php echo e($row['belanja'] > 0 ? rp($row['belanja']) : '0'); ?></td>
        <td><?php echo e($row['satuan']); ?></td>
        <td class="num"><?php echo e(rp($row['harga'])); ?></td>
        <td class="num"><?php echo e(rp($row['total'])); ?></td>
        <td><span class="px-1.5 py-0.5 rounded text-[11px] <?php echo e($row['status'] === 'Sudah' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'); ?>"><?php echo e($row['status']); ?></span></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <tr><td colspan="11" class="text-center text-slate-400">Belum ada RKAS yang disahkan / diimpor untuk tahun ini.</td></tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr class="font-bold bg-slate-50"><td colspan="5" class="text-right">Jumlah</td>
        <td class="num"><?php echo e(rp($ta)); ?></td><td class="num"><?php echo e(rp($tb)); ?></td><td colspan="4"></td></tr>
    </tfoot>
  </table></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'RKAS Bulanan'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/laporan-arkas/rkas-bulanan.blade.php ENDPATH**/ ?>