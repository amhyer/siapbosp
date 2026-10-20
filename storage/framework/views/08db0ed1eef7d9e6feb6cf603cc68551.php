<?php $__env->startSection('subtitle', 'Bandingkan file ARKAS dengan data SiapBOS — tanpa mengubah data'); ?>
<?php $__env->startSection('content'); ?>
<div class="card max-w-2xl">
  <form method="post" action="<?php echo e(route('rekonsiliasi.proses')); ?>" enctype="multipart/form-data" class="space-y-4">
    <?php echo csrf_field(); ?>
    <div>
      <label class="lbl" for="berkas">Berkas Excel ARKAS (.xlsx)</label>
      <input id="berkas" type="file" name="berkas" accept=".xlsx" required class="inp">
    </div>
    <div>
      <label class="lbl" for="tahun">Tahun</label>
      <select id="tahun" name="tahun" class="inp !w-auto">
        <?php for($y = 2022; $y <= 2028; $y++): ?> <option value="<?php echo e($y); ?>" <?php if($tahun === $y): echo 'selected'; endif; ?>><?php echo e($y); ?></option> <?php endfor; ?>
      </select>
    </div>
    <button class="btn">🔍 Bandingkan</button>
  </form>
</div>
<div class="card max-w-2xl">
  <h3 class="card-title mb-2">ℹ️ Cara baca hasil</h3>
  <ul class="list-disc pl-5 text-[13px] text-slate-600 space-y-1">
    <li><b>Cocok</b>: baris ARKAS ada di SiapBOS dengan nominal sama (kunci id/tanda digital).</li>
    <li><b>Selisih nominal</b>: baris ketemu tapi nilainya beda — periksa.</li>
    <li><b>Hilang/Lebih</b>: ada di satu sisi saja.</li>
    <li><b>Peta BKU</b>: nomor BKU SiapBOS ↔ nomor BKU ARKAS per transaksi cocok.</li>
  </ul>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'Rekonsiliasi ARKAS'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/rekonsiliasi/form.blade.php ENDPATH**/ ?>