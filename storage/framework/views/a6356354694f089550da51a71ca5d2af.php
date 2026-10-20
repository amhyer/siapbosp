<?php $__env->startSection('content'); ?>
<?php echo $__env->make('laporan-arkas._header', ['judul' => 'Register Penutupan Kas', 'pilihLabel' => 'Periode'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="max-w-3xl mx-auto space-y-4">
  <div class="card">
    <table class="tbl text-[12px]">
      <tbody>
        <tr><td class="w-1/2">Tanggal Penutupan Kas</td><td>: <?php echo e(\Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y')); ?></td></tr>
        <tr><td>Nama Penutupan Kas/ Pemegang Kas</td><td>: <?php echo e($pemegang->nama ?? '—'); ?></td></tr>
        <tr><td>Tanggal Penutupan Kas Bulan lalu</td><td>: <?php echo e(\Carbon\Carbon::parse($tanggalLalu)->translatedFormat('d F Y')); ?></td></tr>
        <tr><td>Jumlah Penerimaan (D)</td><td>: <b><?php echo e(rp($D)); ?></b></td></tr>
        <tr><td>Jumlah Pengeluaran (K)</td><td>: <b><?php echo e(rp($K)); ?></b></td></tr>
        <tr><td>Saldo Buku Kas Umum (A = D-K)</td><td>: <b><?php echo e(rp($A)); ?></b></td></tr>
      </tbody>
    </table>
  </div>

  <div class="card">
    <div class="text-[12px] font-semibold mb-2">Terdiri dari :</div>
    <div class="tbl-wrap"><table class="tbl text-[12px]">
      <thead><tr><th>1.</th><th>Lembar Uang Kertas</th><th class="num">Nilai</th><th class="num">Jumlah</th><th>Satuan</th><th class="num">Kali Nilai</th></tr></thead>
      <tbody>
        <?php $__currentLoopData = $denLembar; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr><td class="w-8 text-slate-400"><?php echo e($i + 1); ?></td><td>Lembar Uang Kertas</td>
          <td class="num"><?php echo e(rp($d['nilai'])); ?></td><td class="num"><?php echo e($d['jumlah']); ?></td><td>lembar</td>
          <td class="num"><?php echo e(rp($d['total'])); ?></td></tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <tr class="font-semibold"><td colspan="5" class="text-right">Jumlah sub (1)</td><td class="num"><?php echo e(rp(collect($denLembar)->sum('total'))); ?></td></tr>
      </tbody>
      <thead><tr><th>2.</th><th>Keping Uang Logam</th><th class="num">Nilai</th><th class="num">Jumlah</th><th>Satuan</th><th class="num">Kali Nilai</th></tr></thead>
      <tbody>
        <?php $__currentLoopData = $denKeping; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr><td class="w-8 text-slate-400"><?php echo e($i + 1); ?></td><td>Keping Uang Logam</td>
          <td class="num"><?php echo e(rp($d['nilai'])); ?></td><td class="num"><?php echo e($d['jumlah']); ?></td><td>keping</td>
          <td class="num"><?php echo e(rp($d['total'])); ?></td></tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <tr class="font-semibold"><td colspan="5" class="text-right">Jumlah sub (2)</td><td class="num"><?php echo e(rp(collect($denKeping)->sum('total'))); ?></td></tr>
      </tbody>
      <thead><tr><th>3.</th><th>Saldo Bank</th><th colspan="3"></th><th class="num"></th></tr></thead>
      <tbody>
        <tr><td>3.</td><td>Saldo Bank</td><td colspan="3"></td><td class="num"><?php echo e(rp($bank)); ?></td></tr>
        <tr><td></td><td>Pendapatan Jasa Bank</td><td colspan="3"></td><td class="num"><?php echo e(rp(0)); ?></td></tr>
      </tbody>
    </table></div>
    <div class="text-[12px] mt-3 space-y-1">
      <div>( B ) Jumlah 1+2+3 : <b><?php echo e(rp($tunai + $bank)); ?></b></div>
      <div>Perbedaan Kas (A - B) : <b><?php echo e(rp($perbedaan)); ?></b></div>
    </div>
  </div>

  <div class="flex flex-wrap gap-2">
    <a href="<?php echo e(route('laporan-arkas.penutupan-kas-cetak')); ?>?bulan=<?php echo e($tahun.'-'.str_pad($bulan,2,'0',STR_PAD_LEFT)); ?>" target="_blank" class="btn2 btn">🖨️ Cetak Register + Berita Acara</a>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'Register Penutupan Kas'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/laporan-arkas/penutupan-kas.blade.php ENDPATH**/ ?>