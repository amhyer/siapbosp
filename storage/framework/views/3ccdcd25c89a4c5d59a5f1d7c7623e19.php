<?php $__env->startSection('content'); ?>
<div class="grid lg:grid-cols-3 gap-5">
<div class="card">
  <div class="flex justify-between text-xs text-slate-500 mb-3"><span>Saldo Bank:</span><b class="text-sky-700"><?php echo e(rp($saldo['bank'])); ?></b></div>
  <h3 class="font-semibold text-slate-700 mb-3">Input Pendapatan</h3>
  <form method="post" action="<?php echo e(route('pendapatan.store')); ?>" class="space-y-3">
    <?php echo csrf_field(); ?>
    <div><label class="text-xs font-semibold text-slate-600">Tanggal Transaksi</label><input type="date" name="tanggal" class="inp mt-1" value="<?php echo e(date('Y-m-d')); ?>" required></div>
    <div><label class="text-xs font-semibold text-slate-600">Jenis BOS</label>
      <select name="bos_type_id" class="inp mt-1"><?php $__currentLoopData = $bosTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($b->id); ?>"><?php echo e($b->nama); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
    <div><label class="text-xs font-semibold text-slate-600">Tahap</label>
      <select name="stage_id" class="inp mt-1"><?php $__currentLoopData = $stages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($s->id); ?>"><?php echo e($s->nama); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
    <div><label class="text-xs font-semibold text-slate-600">Keterangan</label><input name="keterangan" class="inp mt-1" placeholder="mis. Pencairan Tahap 1" required></div>
    <div class="border-t pt-3">
      <div class="flex justify-between items-center mb-2"><span class="text-xs font-semibold text-slate-600">Rincian</span>
        <button type="button" class="badge bdan" onclick="tambah()">➕ Tambah Rincian</button></div>
      <div id="rows" class="space-y-2"></div>
      <div class="flex justify-between text-sm font-bold mt-3"><span>Total</span><span id="grand">Rp 0</span></div>
    </div>
    <button class="btn w-full justify-center">💾 Simpan (masuk Kas Bank)</button>
  </form>
</div>
<div class="card">
  <h3 class="font-semibold text-slate-700 text-sm mb-2">💰 Bunga Bank</h3>
  <p class="text-[11px] text-slate-400 mb-2 leading-snug">Penerimaan insidental dari bunga rekening sekolah — di luar RKAS, langsung masuk Kas Bank. Ikut mengangkat saldo dan menjadi bagian SiLPA akhir tahun.</p>
  <form method="post" action="<?php echo e(route('pendapatan.bunga')); ?>" class="space-y-2">
    <?php echo csrf_field(); ?>
    <input type="date" name="tanggal" class="inp text-sm" value="<?php echo e(date('Y-m-d')); ?>" required>
    <select name="bos_type_id" class="inp text-sm"><?php $__currentLoopData = $bosTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($b->id); ?>"><?php echo e($b->nama); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select>
    <select name="stage_id" class="inp text-sm"><?php $__currentLoopData = $stages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($s->id); ?>"><?php echo e($s->nama); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select>
    <input type="number" name="nilai" min="1" step="1" class="inp text-sm" placeholder="Nilai bunga (Rp)" required>
    <button class="btn btn-sm w-full justify-center">💰 Catat Bunga Bank</button>
  </form>
</div>
<div class="card lg:col-span-2">
  <?php $kelebihan = $pagu > 0 && $diterimaTahun > $pagu; ?>
  <div class="rounded-lg p-3 mb-3 text-xs <?php if($kelebihan): ?> bg-rose-50 border border-rose-200 <?php else: ?> bg-slate-50 border border-slate-200 <?php endif; ?>">
    <div class="grid grid-cols-3 gap-2 text-center">
      <div><div class="text-slate-500">Pagu BOSP <?php echo e($tahun); ?></div><div class="font-bold text-sm"><?php echo e(rp($pagu)); ?></div></div>
      <div><div class="text-slate-500">Diterima <?php echo e($tahun); ?></div><div class="font-bold text-sm <?php echo e($kelebihan ? 'text-rose-700' : 'text-emerald-700'); ?>"><?php echo e(rp($diterimaTahun)); ?></div></div>
      <div><div class="text-slate-500">SILPA Tahun Lalu</div><div class="font-bold text-sm"><?php echo e(rp($silpa)); ?></div></div>
    </div>
    <?php if($kelebihan): ?>
      <div class="mt-2 text-rose-700 font-semibold">⚠️ Total penerimaan melewati pagu — cek penyaluran/konfirmasi dana di Dinas.</div>
    <?php endif; ?>
  </div>
  <h3 class="font-semibold text-slate-700 mb-3">Riwayat Pendapatan <?php echo e($tahun); ?></h3>
  <form method="get" class="flex gap-2 mb-3">
    <input type="text" name="q" value="<?php echo e($q ?? ''); ?>" placeholder="🔍 Cari keterangan / no bukti..." class="inp !py-1.5 text-sm w-64">
    <button class="btn2 btn text-sm">Cari</button>
    <?php if(!empty($q)): ?><a href="<?php echo e(route('pendapatan.index')); ?>" class="text-xs text-slate-400 hover:text-rose-500 self-center">✖ reset</a><?php endif; ?>
  </form>
  <table class="tbl">
    <thead><tr><th>BKU</th><th>Tanggal</th><th>Uraian</th><th>Rincian</th><th class="num">Nilai</th><th></th></tr></thead>
    <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <tr>
        <td>#<?php echo e($t->no_bku); ?></td><td><?php echo e($t->tanggal->format('d/m/Y')); ?></td><td><?php echo e($t->keterangan); ?></td>
        <td class="text-xs text-slate-500"><?php $__currentLoopData = $t->details; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($d->rekening->kode ?? ''); ?> — <?php echo e(rp($d->nilai)); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></td>
        <td class="num font-semibold text-emerald-700"><?php echo e(rp($t->total)); ?></td>
        <td><form method="post" action="<?php echo e(route('penerimaan.destroy', $t)); ?>" onsubmit="return confirm('Hapus pendapatan ini?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="badge bdel">✖</button></form></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <tr><td colspan="6" class="text-center text-slate-400 py-6">Belum ada pendapatan.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <?php echo e($items->links()); ?>

</div>
</div>
<template id="tpl">
  <div class="flex gap-2">
    <select name="rekening_id[]" class="inp" required><?php $__currentLoopData = $rekenings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($r->id); ?>"><?php echo e($r->kode); ?> · <?php echo e($r->nama); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select>
    <input name="nilai[]" type="number" min="1" value="0" class="inp nilai" style="width:150px" oninput="hitung()">
    <button type="button" class="badge bdel" onclick="this.parentElement.remove();hitung()">✖</button>
  </div>
</template>
<script>
function tambah(){document.getElementById('rows').appendChild(document.getElementById('tpl').content.cloneNode(true));}
function hitung(){let g=0;document.querySelectorAll('.nilai').forEach(i=>g+=+i.value||0);document.getElementById('grand').textContent='Rp '+g.toLocaleString('id-ID');}
tambah();
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'Pendapatan'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/penerimaan/pendapatan.blade.php ENDPATH**/ ?>