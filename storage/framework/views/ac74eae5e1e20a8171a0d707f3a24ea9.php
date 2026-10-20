<?php $__env->startSection('content'); ?>
<div class="mb-3 flex gap-2">
  <a href="<?php echo e(route('belanja.kwitansi', $t)); ?>" class="btn2 btn text-sm">🧾 Cetak Kwitansi</a>
  <a href="<?php echo e(route('belanja.spj', $t)); ?>" class="btn2 btn text-sm">🗂️ Paket Dokumen SPJ</a>
  <a href="<?php echo e(route('belanja.index')); ?>" class="btn2 btn text-sm">← Daftar Belanja</a>
</div>
<div class="card grid sm:grid-cols-3 gap-3 text-sm">
  <div><div class="text-xs text-slate-500">No Bukti / BKU</div><div class="font-bold"><?php echo e($t->no_bukti ?? '—'); ?> · #<?php echo e($t->no_bku); ?></div></div>
  <div><div class="text-xs text-slate-500">Tanggal</div><div class="font-bold"><?php echo e($t->tanggal->format('d/m/Y')); ?></div></div>
  <div><div class="text-xs text-slate-500">Dana / Tahap</div><div class="font-bold"><?php echo e($t->bosType->nama ?? '—'); ?> · <?php echo e($t->stage->nama ?? '—'); ?></div></div>
  <div><div class="text-xs text-slate-500">Pembayaran</div><div class="font-bold"><?php echo e(ucfirst($t->metode_bayar)); ?></div></div>
  <div class="sm:col-span-2"><div class="text-xs text-slate-500">Keterangan</div><div class="font-bold"><?php echo e($t->keterangan); ?></div></div>
</div>
<div class="card">
  <h3 class="font-semibold text-slate-700 mb-3">Rincian Belanja</h3>
  <table class="tbl">
    <thead><tr><th>Kode Rekening</th><th>Nama</th><th>Uraian</th><th class="num">Nilai</th></tr></thead>
    <tbody><?php $__currentLoopData = $t->details; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td class="font-mono"><?php echo e($d->rekening->kode ?? ''); ?></td><td><?php echo e($d->rekening->nama ?? ''); ?></td><td><?php echo e($d->uraian); ?></td><td class="num"><?php echo e(rp($d->nilai)); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody>
    <tfoot><tr class="font-bold"><td colspan="3" class="text-right">TOTAL</td><td class="num"><?php echo e(rp($t->total)); ?></td></tr></tfoot>
  </table>
</div>
<?php if($t->taxes->isNotEmpty()): ?>
<div class="card">
  <h3 class="font-semibold text-slate-700 mb-3">Pajak Diterima</h3>
  <table class="tbl">
    <thead><tr><th>Pajak</th><th>Kode Billing</th><th>NTPN</th><th class="num">Nilai</th><th>Status</th></tr></thead>
    <tbody><?php $__currentLoopData = $t->taxes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($p->rekeningPajak->nama); ?></td><td class="font-mono"><?php echo e($p->kode_billing ?? '—'); ?></td><td class="font-mono"><?php echo e($p->ntpn ?? '—'); ?></td><td class="num"><?php echo e(rp($p->nilai)); ?></td>
      <td><span class="badge <?php echo e($p->status==='sudah'?'bg-emerald-100 text-emerald-700':'bg-amber-100 text-amber-700'); ?>"><?php echo e($p->status==='sudah'?'Sudah disetor':'Belum disetor'); ?></span></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody>
  </table>
</div>
<?php endif; ?>
<div class="card">
  <h3 class="font-semibold text-slate-700 mb-3">📎 Lampiran Bukti</h3>
  <table class="tbl">
    <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $t->attachments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <tr><td>📄 <?php echo e($a->nama); ?></td><td class="text-xs text-slate-400"><?php echo e($a->ukuranLabel()); ?> · <?php echo e($a->created_at->format('d/m/Y')); ?></td>
        <td class="text-right"><a href="<?php echo e(route('lampiran.unduh', $a)); ?>" class="badge bdan">⬇ unduh</a>
        <form method="post" action="<?php echo e(route('lampiran.destroy', $a)); ?>" class="inline" onsubmit="return confirm('Hapus lampiran?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="badge bdel">✖</button></form></td></tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <tr><td class="text-slate-400 text-sm py-2">Belum ada lampiran.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <form method="post" action="<?php echo e(route('lampiran.store', $t)); ?>" enctype="multipart/form-data" class="flex gap-2 mt-3 items-center">
    <?php echo csrf_field(); ?>
    <input type="file" name="lampiran[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="inp" required>
    <button class="btn whitespace-nowrap">⬆ Unggah</button>
  </form>
</div>
<a href="<?php echo e(route('belanja.index')); ?>" class="btn2 btn">← Kembali</a>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'Detail Belanja #' . $t->no_bku], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/pengeluaran/belanja-show.blade.php ENDPATH**/ ?>