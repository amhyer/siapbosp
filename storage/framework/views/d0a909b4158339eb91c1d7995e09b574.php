<?php $__env->startSection('content'); ?>
<?php echo $__env->make('laporan-arkas._header', ['judul' => 'DAFTAR KAS UMUM — ' . ($namaBulan[$bulan] ?? '') . ' ' . $kop['tahun']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="mb-2 nonprint">
  <a href="<?php echo e(route('bku', ['bulan' => $kop['tahun'].'-'.str_pad((string) $bulan, 2, '0', STR_PAD_LEFT)])); ?>" class="btn2 btn text-sm">← Buku resmi BKU bulan ini (dengan saldo)</a>
</div>
<div class="card overflow-x-auto">
  <div class="tbl-wrap"><table class="tbl text-[12px]">
    <thead><tr>
      <th>no</th><th>tgl_transaksi</th><th>no_bukti</th><th>tanggal_nota</th><th>no_nota</th>
      <th>uraian</th><th>kode_rekening</th><th>uraian_rekening</th><th class="num">volume</th>
      <th class="num">nominal</th><th>kegiatan</th><th>penerima</th>
    </tr></thead>
    <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <tr>
        <td><?php echo e($row['no']); ?></td>
        <td class="whitespace-nowrap"><?php echo e($row['tgl']); ?></td>
        <td class="whitespace-nowrap"><?php echo e($row['no_bukti']); ?></td>
        <td class="whitespace-nowrap"><?php echo e($row['tanggal_nota'] ?? '—'); ?></td>
        <td><?php echo e($row['no_nota'] ?: '—'); ?></td>
        <td><?php echo e(\Illuminate\Support\Str::limit($row['uraian'], 45)); ?></td>
        <td class="font-mono text-xs whitespace-nowrap"><?php echo e($row['kode'] ?: '—'); ?></td>
        <td class="text-xs"><?php echo e(\Illuminate\Support\Str::limit($row['rekNama'], 28)); ?></td>
        <td class="num"><?php echo e($row['volume'] ?? '—'); ?></td>
        <td class="num font-semibold"><?php echo e(rp($row['nominal'])); ?></td>
        <td class="text-xs"><?php echo e($row['kegiatan'] !== '' ? ($row['kegiatan'] . ($row['kegiatanNama'] ? ' ' . $row['kegiatanNama'] : '')) : '—'); ?></td>
        <td class="text-xs"><?php echo e($row['penerima'] ?: '—'); ?></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <tr><td colspan="12" class="text-center text-slate-400">Tidak ada transaksi di bulan ini.</td></tr>
      <?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'Daftar Kas Umum'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/laporan-arkas/kas-umum.blade.php ENDPATH**/ ?>