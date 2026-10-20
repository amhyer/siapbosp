<?php $__env->startSection('subtitle', 'Petakan belanja (khususnya dari SIPLah) ke rekening, kegiatan, dan rincian RKAS'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-head">
  <p class="page-desc">Tahun <?php echo e($tahun); ?> · Tanpa pemetaan, belanja SIPLah tidak bisa dieksekusi ke pergeseran/perubahan.</p>
</div>
<div class="toolbar">
  <form method="get" class="flex flex-wrap gap-2 items-center text-sm">
    <select name="sumber" class="inp inp-sm !w-auto" onchange="this.form.submit()">
      <option value="">Semua sumber</option>
      <option value="siplah" <?php if($sumber==='siplah'): echo 'selected'; endif; ?>>SIPLah</option>
      <option value="langsung" <?php if($sumber==='langsung'): echo 'selected'; endif; ?>>Langsung</option>
    </select>
    <select name="status" class="inp inp-sm !w-auto" onchange="this.form.submit()">
      <option value="">Semua status</option>
      <option value="belum" <?php if($status==='belum'): echo 'selected'; endif; ?>>Belum terpetakan</option>
      <option value="terpetakan" <?php if($status==='terpetakan'): echo 'selected'; endif; ?>>Terpetakan</option>
    </select>
    <?php if($sumber || $status): ?><a href="<?php echo e(route('hpp.index')); ?>" class="text-xs text-slate-400 hover:text-rose-500">✖ hapus filter</a><?php endif; ?>
  </form>
</div>
<form method="post" action="<?php echo e(route('hpp.store')); ?>">
<?php echo csrf_field(); ?>
<div class="card">
  <div class="tbl-wrap">
  <table class="tbl">
    <thead><tr><th>Tanggal</th><th>Belanja / Uraian</th><th>Kode Rekening</th><th>Sumber</th><th class="num">Nilai</th><th style="min-width:260px">Petakan ke RKAS (kegiatan)</th></tr></thead>
    <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <tr>
        <td class="whitespace-nowrap"><?php echo e($d->transaksi->tanggal->format('d/m/Y')); ?></td>
        <td><?php echo e($d->uraian); ?><div class="text-[11px] text-slate-400"><?php echo e(Str::limit($d->transaksi->keterangan, 50)); ?></div></td>
        <td class="font-mono text-xs"><?php echo e($d->rekening->kode ?? '—'); ?></td>
        <td>
          <span class="badge <?php echo e($d->sumber==='siplah'?'bg-sky-100 text-sky-700':'bg-slate-100 text-slate-600'); ?>"><?php echo e($d->sumber); ?></span>
          <select name="peta[<?php echo e($d->id); ?>][sumber]" class="inp inp-sm !w-auto mt-1">
            <option value="langsung" <?php if($d->sumber==='langsung'): echo 'selected'; endif; ?>>langsung</option>
            <option value="siplah" <?php if($d->sumber==='siplah'): echo 'selected'; endif; ?>>siplah</option>
          </select>
        </td>
        <td class="num"><?php echo e(rp($d->nilai)); ?></td>
        <td>
          <select name="peta[<?php echo e($d->id); ?>][rkas_detail_id]" class="inp inp-sm">
            <option value="">— belum dipetakan —</option>
            <?php $__currentLoopData = $rincian; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($rc->id); ?>" <?php if($d->rkas_detail_id===$rc->id): echo 'selected'; endif; ?>><?php echo e($rc->rekening->kode ?? ''); ?> · <?php echo e(Str::limit($rc->uraian, 50)); ?> (<?php echo e(rp($rc->jumlah)); ?>)</option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
          <?php if($d->rkasDetail): ?><div class="text-[11px] text-emerald-600 mt-0.5">✓ <?php echo e(Str::limit($d->rkasDetail->uraian, 45)); ?></div><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <tr><td colspan="6"><div class="empty">Tidak ada rincian belanja. Input belanja atau impor ARKAS dulu.</div></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
  <?php echo e($items->links()); ?>

  <div class="mt-3"><button class="btn">💾 Simpan Pemetaan</button></div>
</div>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'HPP — Pemetaan Pembelanjaan'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/hpp/index.blade.php ENDPATH**/ ?>