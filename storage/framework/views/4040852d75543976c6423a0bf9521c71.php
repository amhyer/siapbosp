<?php $__env->startSection('content'); ?>
<?php
  $namaSem = $semester === 1 ? 'Semester 1 (Januari–Juni)' : 'Semester 2 (Juli–Desember)';
  $bendahara = $penandatangan->first(fn ($p) => str_contains(strtolower($p->jabatan), 'bendahara'));
  $kepsek = $penandatangan->first(fn ($p) => str_contains(strtolower($p->jabatan), 'kepala')) ?: $penandatangan->last();
?>
<style>
  .grp th{background:#dbeafe;text-align:left}
  .sub td{background:#f1f5f9;font-weight:600}
</style>

<div class="toolbar nonprint">
  <button onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
  <a href="<?php echo e(route('bku')); ?>">← Kembali BKU</a>
  <?php if($semester === 1): ?>
    <a href="<?php echo e(route('pembukuan.bukuPembantu', ['semester' => 2])); ?>">Semester 2 →</a>
  <?php else: ?>
    <a href="<?php echo e(route('pembukuan.bukuPembantu', ['semester' => 1])); ?>">← Semester 1</a>
  <?php endif; ?>
  <a href="<?php echo e(route('pembukuan.rekapRealisasi', ['semester' => $semester])); ?>">Rekap Realisasi Semester →</a>
</div>

<div class="kop">
  <h1 style="font-size:1.15rem;margin:0"><?php echo e($sekolah->nama); ?></h1>
  <div style="font-size:.85rem"><b>BUKU PEMBANTU RINCIAN OBJEK BELANJA</b></div>
  <div style="font-size:.8rem"><?php echo e($namaSem); ?> · Tahun Anggaran <?php echo e($tahun); ?></div>
  <div style="font-size:.78rem;color:#64748b">Periode <?php echo e(\Carbon\Carbon::parse($awal)->translatedFormat('d F Y')); ?> s.d. <?php echo e(\Carbon\Carbon::parse($akhir)->translatedFormat('d F Y')); ?></div>
</div>

<?php $__empty_1 = true; $__currentLoopData = $kelompok; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $kode => $g): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
  <table class="tbl">
    <thead>
      <tr class="grp">
        <th colspan="4"><?php echo e($kode); ?> — <?php echo e($g['nama']); ?></th>
        <th class="num">Saldo Awal</th>
        <th class="num"><?php echo e(rp($g['awalSaldo'])); ?></th>
      </tr>
      <tr><th style="width:2rem">No.</th><th style="width:6rem">No. Bukti</th><th style="width:5.5rem">Tanggal</th><th>Uraian</th><th class="num" style="width:7rem">Pengeluaran</th><th class="num" style="width:7rem">Saldo</th></tr>
    </thead>
    <tbody>
      <?php $__empty_2 = true; $__currentLoopData = $g['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
      <tr>
        <td><?php echo e($i + 1); ?></td>
        <td><?php echo e($row['bukti'] ?: '—'); ?></td>
        <td><?php echo e(\Carbon\Carbon::parse($row['tgl'])->format('d/m/Y')); ?></td>
        <td><?php echo e($row['uraian']); ?></td>
        <td class="num"><?php echo e(rp($row['nilai'])); ?></td>
        <td class="num"><?php echo e(rp($row['saldo'])); ?></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
      <tr><td colspan="6" style="text-align:center;color:#64748b">Tidak ada pengeluaran pada semester ini.</td></tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr class="sub">
        <td colspan="5">Jumlah Mutasi Semester · Saldo Akhir</td>
        <td class="num"><?php echo e(rp($g['mutasi'])); ?> / <?php echo e(rp($g['akhir'])); ?></td>
      </tr>
    </tfoot>
  </table>
  <div style="height:.6rem"></div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
  <p style="text-align:center;color:#64748b">Belum ada belanja pada tahun anggaran <?php echo e($tahun); ?>.</p>
<?php endif; ?>

<table class="tbl" style="margin-top:.5rem">
  <tfoot>
    <tr>
      <td style="background:#0f172a;color:#fff;font-weight:700">TOTAL MUTASI PENGELUARAN SEMESTER</td>
      <td class="num" style="background:#0f172a;color:#fff;font-weight:700"><?php echo e(rp($totalMutasi)); ?></td>
    </tr>
  </tfoot>
</table>

<div class="ttd-grid mt-8">
  <div>
    Mengetahui/Menyetujui,<br>Bendahara Sekolah<br><br><br><br>
    <b><?php echo e($bendahara?->nama ?? '..................'); ?></b><br>
    <?php echo e($bendahara?->nip ? 'NIP. '.$bendahara->nip : ''); ?>

  </div>
  <div>
    Mengetahui/Menyetujui,<br>Kepala Sekolah<br><br><br><br>
    <b><?php echo e($kepsek?->nama ?? '..................'); ?></b><br>
    <?php echo e($kepsek?->nip ? 'NIP. '.$kepsek->nip : ''); ?>

  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.print', ['title' => 'Buku Pembantu Rincian Objek Belanja'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/pembukuan/buku-pembantu.blade.php ENDPATH**/ ?>