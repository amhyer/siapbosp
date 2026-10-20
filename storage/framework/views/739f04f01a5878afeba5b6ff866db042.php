<?php $__env->startSection('content'); ?>
<?php echo $__env->make('laporan-arkas._header', ['judul' => 'Realisasi Barang Habis Pakai — ' . ($namaBulan[$bulan] ?? '') . ' ' . $kop['tahun']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="card overflow-x-auto">
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr>
      <th>Kode Rekening</th><th>Uraian</th><th>Tanggal Transaksi</th>
      <th class="num">Volume</th><th class="num">Harga Satuan</th><th class="num">Jumlah</th>
    </tr></thead>
    <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <tr>
        <td class="font-mono text-xs whitespace-nowrap"><?php echo e($row['kode']); ?></td>
        <td><?php echo e(\Illuminate\Support\Str::limit($row['uraian'], 60)); ?></td>
        <td class="whitespace-nowrap"><?php echo e($row['tanggal']); ?></td>
        <td class="num"><?php echo e($row['volume'] ?? '—'); ?></td>
        <td class="num"><?php echo e($row['harga'] !== null ? rp($row['harga']) : '—'); ?></td>
        <td class="num font-semibold"><?php echo e(rp($row['jumlah'])); ?></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <tr><td colspan="6" class="text-center text-slate-400">Tidak ada belanja barang (5.1.02.01) di bulan ini.</td></tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr class="font-bold bg-slate-50"><td colspan="5" class="text-right">Total</td><td class="num"><?php echo e(rp($total)); ?></td></tr>
    </tfoot>
  </table></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'Realisasi Barang Habis Pakai'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/laporan-arkas/habis-pakai.blade.php ENDPATH**/ ?>