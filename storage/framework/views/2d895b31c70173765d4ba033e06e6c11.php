<?php $__env->startSection('content'); ?>
<?php echo $__env->make('laporan-arkas._header', ['judul' => 'DAFTAR NOTA — ' . ($namaBulan[$bulan] ?? '') . ' ' . $kop['tahun']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="card overflow-x-auto">
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr>
      <th>no</th><th>no_bukti</th><th>tanggal_nota</th><th>no_nota</th><th>pembayaran</th>
      <th>nama_toko/ penerima</th><th>alamat</th><th class="num">total</th><th class="num">lunas_bayar</th><th>tgl_transaksi</th>
    </tr></thead>
    <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <tr>
        <td><?php echo e($row['no']); ?></td>
        <td class="whitespace-nowrap"><?php echo e($row['no_bukti']); ?></td>
        <td class="whitespace-nowrap"><?php echo e($row['tanggal_nota']); ?></td>
        <td><?php echo e($row['no_nota'] ?: '—'); ?></td>
        <td><?php echo e($row['pembayaran']); ?></td>
        <td>
          <?php echo e($row['nama'] ?: '—'); ?>

          <?php if($row['alamat'] || $row['no_telp'] || $row['npwp']): ?>
            <div class="text-[11px] text-slate-500">
              <?php echo e($row['alamat']); ?><?php echo e($row['no_telp'] ? ' — Telp. '.$row['no_telp'] : ''); ?><?php echo e($row['npwp'] ? ' — NPWP '.$row['npwp'] : ''); ?>

            </div>
          <?php endif; ?>
        </td>
        <td class="num font-semibold"><?php echo e(rp($row['total'])); ?></td>
        <td class="num"><?php echo e(rp($row['lunas'])); ?></td>
        <td class="whitespace-nowrap"><?php echo e($row['tgl_transaksi']); ?></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <tr><td colspan="10" class="text-center text-slate-400">Tidak ada nota di bulan ini.</td></tr>
      <?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'Daftar Nota'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/laporan-arkas/nota.blade.php ENDPATH**/ ?>