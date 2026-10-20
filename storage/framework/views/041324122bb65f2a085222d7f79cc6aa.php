<?php $__env->startSection('content'); ?>
<?php echo $__env->make('laporan-arkas._header', ['judul' => 'Rincian Rencana Kegiatan Dan Anggaran Sekolah (RKAS) Per Tahap'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="card overflow-x-auto">
  <div class="tbl-wrap"><table class="tbl text-[12px]">
    <thead><tr>
      <th>No</th><th>Kode Rekening</th><th>Kode Program</th><th>Uraian</th>
      <th class="num">Volume</th><th>Satuan</th><th class="num">Tarif</th><th class="num">Jumlah</th>
      <?php for($i = 1; $i <= $nTahap; $i++): ?> <th class="num">Tahap <?php echo e($i); ?></th> <?php endfor; ?>
    </tr></thead>
    <tbody>
      <?php $tj = 0; ?>
      <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <?php $tj += $row['jumlah']; ?>
      <tr>
        <td><?php echo e($row['no']); ?></td>
        <td class="font-mono text-xs whitespace-nowrap"><?php echo e($row['rek']); ?></td>
        <td class="text-xs"><?php echo e($row['program']); ?></td>
        <td><?php echo e(\Illuminate\Support\Str::limit($row['uraian'], 55)); ?></td>
        <td class="num"><?php echo e($row['volume']); ?></td>
        <td><?php echo e($row['satuan']); ?></td>
        <td class="num"><?php echo e(rp($row['tarif'])); ?></td>
        <td class="num font-semibold"><?php echo e(rp($row['jumlah'])); ?></td>
        <?php for($i = 1; $i <= $nTahap; $i++): ?>
          <td class="num <?php echo e($row['tahap'][$i] > 0 ? '' : 'text-slate-300'); ?>"><?php echo e($row['tahap'][$i] > 0 ? rp($row['tahap'][$i]) : '—'); ?></td>
        <?php endfor; ?>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <tr><td colspan="9" class="text-center text-slate-400">Belum ada RKAS untuk tahun ini.</td></tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr class="font-bold bg-slate-50"><td colspan="7" class="text-right">Jumlah</td>
        <td class="num"><?php echo e(rp($tj)); ?></td>
        <?php for($i = 1; $i <= $nTahap; $i++): ?> <td class="num"><?php echo e(rp($totalTahap[$i] ?? 0)); ?></td> <?php endfor; ?>
      </tr>
    </tfoot>
  </table></div>
  <?php if($tahaps->isEmpty()): ?><p class="text-xs text-amber-700 mt-2">Tahap/periode belum dibuat Dinas untuk tahun ini — pembagian tahap memakai rata-rata ke <?php echo e($nTahap); ?> tahap.</p><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'Rincian RKAS per Tahap'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/laporan-arkas/rkas-tahap.blade.php ENDPATH**/ ?>