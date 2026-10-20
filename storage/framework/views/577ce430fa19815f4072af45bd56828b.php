<?php
  $bnya = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
?>
<div class="flex flex-wrap justify-between items-end gap-2 mb-3">
  <div>
    <div class="font-bold text-[15px]"><?php echo e($judul); ?></div>
    <div class="text-[12px] text-slate-500"><?php echo e(auth()->user()->school->nama); ?> — <?php echo e($kop['sumber'] . $kop['revisiLabel']); ?> — Tahun Anggaran <?php echo e($kop['tahun']); ?></div>
  </div>
  <?php if(isset($bulan) && $bulan): ?>
  <form method="get" class="flex gap-2 items-center text-sm">
    <span class="text-slate-500"><?php echo e($pilihLabel ?? 'Bulan'); ?>:</span>
    <select name="bulan" class="inp !w-auto" onchange="this.form.submit()">
      <?php $__currentLoopData = $namaBulan; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $n => $nm): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?> <option value="<?php echo e($n); ?>" <?php if($n == $bulan): echo 'selected'; endif; ?>><?php echo e($nm); ?> <?php echo e($kop['tahun']); ?></option> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
  </form>
  <?php endif; ?>
</div>
<?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/laporan-arkas/_header.blade.php ENDPATH**/ ?>