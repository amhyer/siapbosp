<?php $__env->startSection('content'); ?>
<?php
  $namaSem = $semester === 1 ? 'Semester 1 (Januari–Juni)' : 'Semester 2 (Juli–Desember)';
  $bendahara = $penandatangan->first(fn ($p) => str_contains(strtolower($p->jabatan), 'bendahara'));
  $kepsek = $penandatangan->first(fn ($p) => str_contains(strtolower($p->jabatan), 'kepala')) ?: $penandatangan->last();
?>

<div class="toolbar nonprint">
  <button onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
  <a href="<?php echo e(route('bku')); ?>">← Kembali BKU</a>
  <?php if($semester === 1): ?>
    <a href="<?php echo e(route('pembukuan.rekapRealisasi', ['semester' => 2])); ?>">Semester 2 →</a>
  <?php else: ?>
    <a href="<?php echo e(route('pembukuan.rekapRealisasi', ['semester' => 1])); ?>">← Semester 1</a>
  <?php endif; ?>
  <a href="<?php echo e(route('pembukuan.bukuPembantu', ['semester' => $semester])); ?>">Buku Pembantu Semester →</a>
</div>

<div class="kop">
  <h1 style="font-size:1.15rem;margin:0"><?php echo e($sekolah->nama); ?></h1>
  <div style="font-size:.85rem"><b>REKAPITULASI REALISASI BELANJA</b></div>
  <div style="font-size:.8rem"><?php echo e($namaSem); ?> · Tahun Anggaran <?php echo e($tahun); ?></div>
  <div style="font-size:.78rem;color:#64748b">
    Sisa pagu semester ini menjadi bahan usulan SiLPA semester berikutnya.
  </div>
</div>

<table class="tbl">
  <thead>
    <tr>
      <th style="width:2rem">No.</th>
      <th style="width:9.5rem">Kode Rekening</th>
      <th>Uraian Rekening</th>
      <th class="num" style="width:7rem">Anggaran</th>
      <th class="num" style="width:7rem">Realisasi Semester</th>
      <th class="num" style="width:6.5rem">Sisa Pagu</th>
      <th class="num" style="width:3.5rem">%</th>
    </tr>
  </thead>
  <tbody>
    <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $x): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <tr>
      <td><?php echo e($i + 1); ?></td>
      <td><?php echo e($x['kode']); ?></td>
      <td><?php echo e($x['nama'] ?? '—'); ?></td>
      <td class="num"><?php echo e($x['punyaAnggaran'] ? rp($x['anggaran']) : '—'); ?></td>
      <td class="num"><?php echo e(rp($x['realisasi'])); ?></td>
      <td class="num"><?php echo e($x['sisa'] === null ? '—' : rp($x['sisa'])); ?></td>
      <td class="num"><?php echo e($x['punyaAnggaran'] && $x['anggaran'] > 0 ? round($x['realisasi'] / $x['anggaran'] * 100, 1).'%' : '—'); ?></td>
    </tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <tr><td colspan="7" style="text-align:center;color:#64748b">Tidak ada rekening dengan anggaran maupun realisasi pada semester ini.</td></tr>
    <?php endif; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="3" style="background:#0f172a;color:#fff;font-weight:700">TOTAL SEMESTER</td>
      <td class="num" style="background:#0f172a;color:#fff;font-weight:700"><?php echo e(rp($sumAnggaran)); ?></td>
      <td class="num" style="background:#0f172a;color:#fff;font-weight:700"><?php echo e(rp($sumRealisasi)); ?></td>
      <td class="num" style="background:#0f172a;color:#fff;font-weight:700"><?php echo e($sumAnggaran > 0 ? rp($sumAnggaran - $sumRealisasi) : '—'); ?></td>
      <td class="num" style="background:#0f172a;color:#fff;font-weight:700"><?php echo e($sumAnggaran > 0 ? round($sumRealisasi / $sumAnggaran * 100, 1).'%' : '—'); ?></td>
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

<?php echo $__env->make('layouts.print', ['title' => 'Rekapitulasi Realisasi Belanja'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/pembukuan/rekap-realisasi.blade.php ENDPATH**/ ?>