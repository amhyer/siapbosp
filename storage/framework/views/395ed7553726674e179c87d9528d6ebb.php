<?php $__env->startSection('content'); ?>
<div class="toolbar nonprint">
  <form style="display:flex;gap:.5rem;align-items:center;font-size:.82rem" method="get">
    <span>Tahap:</span>
    <select name="stage" style="border:1px solid #cbd5e1;border-radius:.5rem;padding:.35rem .6rem" onchange="this.form.submit()">
      <?php $__currentLoopData = $stages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($s->id); ?>" <?php if($stageId===$s->id): echo 'selected'; endif; ?>><?php echo e($s->nama); ?><?php echo e(in_array($s->id, $terkunciMap) ? ' 🔒' : ''); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
  </form>
  <button onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
  <a href="<?php echo e(route('laporan.lpj', ['stage' => $stageId])); ?>" style="background:#eef2ff;border-color:#c7d2fe">📦 LPJ Lengkap</a>
  <a href="<?php echo e(route('laporan.lpj.zip', ['stage' => $stageId])); ?>" style="background:#ecfdf5;border-color:#6ee7b7">⬇️ Paket ZIP</a>
  <a href="<?php echo e(route('laporan.lpj.pdf', ['stage' => $stageId])); ?>" style="background:#fef2f2;border-color:#fca5a5;font-weight:700">📕 PDF Asli</a>
  <a href="<?php echo e(route('laporan.lpj.xlsx', ['stage' => $stageId])); ?>" style="background:#f0fdf4;border-color:#86efac;font-weight:700">📗 Excel 4 Sheet</a>
  <a href="<?php echo e(route('dashboard')); ?>">← Kembali</a>
  <?php if($terkunci): ?>
    <span style="background:#fee2e2;color:#b91c1c;font-size:11px;padding:.25rem .6rem;border-radius:.5rem">🔒 Tahap ini DITUTUP bukunya</span>
    <?php if(auth()->user()->role === 'kepsek'): ?>
    <form method="post" action="<?php echo e(route('laporan.tahap.buka', $stageId)); ?>" onsubmit="return confirm('Buka kembali buku tahap ini?')"><?php echo csrf_field(); ?>
      <button style="border-color:#fca5a5;background:#fef2f2;color:#b91c1c">🔓 Buka Kembali (Kepsek)</button>
    </form>
    <?php endif; ?>
  <?php else: ?>
    <form method="post" action="<?php echo e(route('laporan.tahap.tutup', $stageId)); ?>" onsubmit="return confirm('Tutup buku tahap ini? Transaksi di tahap ini akan terkunci.')"><?php echo csrf_field(); ?>
      <button>🔒 Tutup Buku Tahap Ini</button>
    </form>
  <?php endif; ?>

  <?php if($lpj): ?>
    <?php if($lpj->status === 'dikirim'): ?>
      <span style="background:#fef3c7;color:#92400e;font-size:11px;padding:.25rem .6rem;border-radius:.5rem;font-weight:600">📨 LPJ dikirim <?php echo e($lpj->created_at->format('d/m')); ?> — menunggu tinjauan Dinas</span>
    <?php elseif($lpj->status === 'diterima'): ?>
      <span style="background:#d1fae5;color:#065f46;font-size:11px;padding:.25rem .6rem;border-radius:.5rem;font-weight:600">✅ LPJ DITERIMA Dinas<?php echo e($lpj->ditinjau_at ? ' (' . $lpj->ditinjau_at->format('d/m/Y') . ')' : ''); ?></span>
    <?php else: ?>
      <span style="background:#fee2e2;color:#991b1b;font-size:11px;padding:.25rem .6rem;border-radius:.5rem;font-weight:600" title="<?php echo e($lpj->catatan_dinas); ?>">❌ LPJ DITOLAK — <?php echo e(Str::limit($lpj->catatan_dinas, 60)); ?></span>
    <?php endif; ?>
  <?php endif; ?>
  <?php if($terkunci && (!$lpj || $lpj->status === 'ditolak')): ?>
    <form method="post" action="<?php echo e(route('laporan.lpj.kumpulkan', $stageId)); ?>" onsubmit="return confirm('Kirim LPJ tahap ini ke Dinas untuk ditinjau?')"><?php echo csrf_field(); ?>
      <button style="background:#4f46e5;color:#fff;font-weight:700;border-color:#4f46e5"><?php echo e($lpj ? '🔄 Kirim Ulang LPJ ke Dinas' : '📨 Kirim LPJ ke Dinas'); ?></button>
    </form>
  <?php endif; ?>
</div>
<?php $sekolah = auth()->user()->school; $stg = $stages->firstWhere('id', $stageId); ?>
<div class="kop">
  <div style="font-size:11px;text-transform:uppercase"><?php echo e($sekolah->kab_kota); ?> — <?php echo e($sekolah->provinsi); ?></div>
  <div style="font-size:18px;font-weight:800;text-transform:uppercase"><?php echo e($sekolah->nama); ?></div>
  <div style="font-size:11px">NPSN <?php echo e($sekolah->npsn); ?> · <?php echo e($sekolah->alamatLengkap()); ?></div>
  <div style="margin-top:6px;font-weight:700">LAPORAN PEMANFAATAN DANA BOSP — <?php echo e(strtoupper($stg->nama ?? '')); ?> TAHUN ANGGARAN <?php echo e($tahun); ?></div>
</div>

<table class="tbl">
  <thead><tr><th>No BKU</th><th>Tanggal</th><th>Uraian</th><th>Kode Rekening</th><th class="num">Penerimaan (Rp)</th><th class="num">Pengeluaran (Rp)</th></tr></thead>
  <tbody>
    <?php $tm=0; $tk=0; ?>
    <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php
        $isPend = $t->type==='pendapatan';
        $det = $t->details->pluck('rekening.kode')->filter()->implode(', ');
        ?>
      <?php if($t->details->isEmpty()): ?>
        <tr><td>#<?php echo e($t->no_bku); ?></td><td><?php echo e($t->tanggal->format('d/m/Y')); ?></td><td><?php echo e($t->keterangan); ?> (<?php echo e($t->labelType()); ?>)</td><td>—</td>
        <td class="num"><?php echo e($isPend ? rp($t->total) : ''); ?></td><td class="num"><?php echo e($isPend ? '' : rp($t->total)); ?></td></tr>
        <?php $isPend ? $tm+=$t->total : $tk+=$t->total; ?>
      <?php else: ?>
        <?php $__currentLoopData = $t->details; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr><td>#<?php echo e($t->no_bku); ?></td><td><?php echo e($t->tanggal->format('d/m/Y')); ?></td><td><?php echo e($d->uraian ?? $t->keterangan); ?></td><td style="font-family:monospace"><?php echo e($d->rekening->kode ?? ''); ?></td>
        <td class="num"><?php echo e($isPend ? rp($d->nilai) : ''); ?></td><td class="num"><?php echo e($isPend ? '' : rp($d->nilai)); ?></td></tr>
        <?php $isPend ? $tm+=$d->nilai : $tk+=$d->nilai; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </tbody>
  <tfoot>
    <tr style="font-weight:700"><td colspan="4" style="text-align:right">JUMLAH</td><td class="num"><?php echo e(rp($tm)); ?></td><td class="num"><?php echo e(rp($tk)); ?></td></tr>
  </tfoot>
</table>

<?php if($items->pluck('taxes')->flatten()->isNotEmpty()): ?>
<h4 style="font-size:14px;font-weight:700;margin:1.25rem 0 .4rem">Rincian Pajak</h4>
<table class="tbl">
  <thead><tr><th>Tanggal</th><th>Jenis Pajak</th><th>Kode Billing</th><th>NTPN</th><th class="num">Nilai (Rp)</th></tr></thead>
  <tbody><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php $__currentLoopData = $t->taxes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <tr><td><?php echo e($t->tanggal->format('d/m/Y')); ?></td><td><?php echo e($p->rekeningPajak->nama ?? ''); ?></td><td style="font-family:monospace"><?php echo e($p->kode_billing ?? '-'); ?></td><td style="font-family:monospace"><?php echo e($p->ntpn ?? '-'); ?></td><td class="num"><?php echo e(rp($p->nilai)); ?></td></tr>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody>
</table>
<?php endif; ?>

<?php
  $kepsek = $penandatangan->firstWhere('jabatan', 'Kepala Sekolah') ?? $penandatangan->first(fn($p)=>stripos($p->jabatan,'kepala')!==false);
  $bendahara = $penandatangan->first(fn($p)=>stripos($p->jabatan,'bendahara')!==false) ?? $penandatangan->skip(1)->first();
?>
<div class="ttd">
  <div class="ttd-grid">
    <div><div style="margin-bottom:4rem">Mengetahui,<br>Kepala Sekolah</div><div style="font-weight:700;text-decoration:underline"><?php echo e($kepsek->nama ?? '( ……………………… )'); ?></div><div>NIP. <?php echo e($kepsek->nip ?? '…'); ?></div></div>
    <div><div style="margin-bottom:4rem"><?php echo e($sekolah->kab_kota ?? ''); ?>, <?php echo e(now()->translatedFormat('d F Y')); ?><br>Bendahara</div><div style="font-weight:700;text-decoration:underline"><?php echo e($bendahara->nama ?? '( ……………………… )'); ?></div><div>NIP. <?php echo e($bendahara->nip ?? '…'); ?></div></div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.print', ['title' => 'LPJ & Tutup Buku per Tahap'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/laporan/tahap.blade.php ENDPATH**/ ?>