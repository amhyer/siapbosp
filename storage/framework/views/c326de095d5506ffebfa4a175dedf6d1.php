<?php $__env->startSection('subtitle', 'Reset seluruh data transaksional — khusus non-Kepala Sekolah'); ?>
<?php $__env->startSection('content'); ?>
<div class="card max-w-2xl border-rose-200">
  <h3 class="card-title mb-2">🗑️ Hapus seluruh data <?php echo e($sekolah->nama); ?></h3>
  <p class="page-desc mb-3">Tindakan ini <b>tidak bisa dibatalkan</b>. Yang dihapus: <?php echo e($ringkas['transaksi']); ?> transaksi kas (+rincian, pajak, berkas lampiran), <?php echo e($ringkas['rkas']); ?> RKAS, <?php echo e($ringkas['tutup_buku']); ?> tutup buku, <?php echo e($ringkas['lpj']); ?> pengajuan LPJ, dan log aktivitas sekolah.</p>
  <p class="page-desc mb-3">Yang <b>tetap aman</b>: profil sekolah, akun pengguna, penandatangan, dan pagu/alokasi.</p>
  <form method="post" action="<?php echo e(route('hapusdata.proses')); ?>" onsubmit="return confirm('YAKIN menghapus seluruh data? Tindakan ini permanen.')" class="space-y-4">
    <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
    <div>
      <label class="lbl" for="konfirmasi">Ketik NPSN <code class="font-mono"><?php echo e($sekolah->npsn); ?></code> untuk konfirmasi</label>
      <input id="konfirmasi" name="konfirmasi" class="inp font-mono" placeholder="<?php echo e($sekolah->npsn); ?>" required autocomplete="off">
    </div>
    <button class="btn-danger">🗑️ Ya, Hapus Semua Data</button>
    <a href="<?php echo e(route('dashboard')); ?>" class="btn2 btn-sm">Batal</a>
  </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'Hapus Data Sekolah'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/utility/hapus-data.blade.php ENDPATH**/ ?>