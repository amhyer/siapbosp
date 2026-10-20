<?php $__env->startSection('content'); ?>
<?php if(count($rekomendasi ?? [])): ?>
<div class="card !border-amber-300 !bg-amber-50/60">
  <h3 class="card-title mb-1">⚠️ Sudah pernah dibelanjakan — disarankan masuk revisi</h3>
  <p class="page-desc mb-2">Rekening di bawah sudah ada realisasinya tahun <?php echo e($tahun); ?>. Klik <b>+ Bawa</b> untuk memasukkannya ke rincian revisi ini (pergeseran/perubahan).</p>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Kode</th><th>Uraian RKAS</th><th class="num">Anggaran</th><th class="num">Realisasi</th><th>Sumber</th><th></th></tr></thead>
    <tbody>
      <?php $__currentLoopData = $rekomendasi; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $rc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td class="font-mono text-xs"><?php echo e($rc['kode']); ?></td>
        <td><?php echo e(Str::limit($rc['uraian'], 45)); ?></td>
        <td class="num"><?php echo e(rp($rc['anggaran'])); ?></td>
        <td class="num font-semibold text-rose-700"><?php echo e(rp($rc['realisasi'])); ?></td>
        <td><?php if($rc['siplah']): ?><span class="badge bg-sky-100 text-sky-700">SIPLah</span><?php else: ?><span class="badge bg-slate-100 text-slate-600">langsung</span><?php endif; ?></td>
        <td><button type="button" class="btn btn-sm" onclick='bawaRekomendasi(<?php echo json_encode($rc, 15, 512) ?>)'>+ Bawa</button></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
<form method="post" action="<?php echo e(route('rkas.store')); ?>" class="space-y-5">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="tahun" value="<?php echo e($tahun); ?>">
  <div class="card grid sm:grid-cols-3 gap-4">
    <div><label class="text-xs font-semibold text-slate-600">Tahun</label><input class="inp mt-1 bg-slate-50" value="<?php echo e($tahun); ?>" disabled></div>
    <div><label class="text-xs font-semibold text-slate-600">No RKAS/Bukti</label><input name="no_rkas" class="inp mt-1" placeholder="mis. 420/001/RKAS/2026"></div>
    <div><label class="text-xs font-semibold text-slate-600">Catatan</label><input name="catatan" class="inp mt-1" placeholder="opsional"></div>
  </div>
  <div class="card">
    <div class="flex justify-between items-center mb-3">
      <h3 class="font-semibold text-slate-700">Rincian Kegiatan</h3>
      <button type="button" class="btn2 btn" onclick="tambahBaris()">➕ Tambah Baris</button>
    </div>
    <div class="overflow-x-auto">
      <table class="tbl" id="tblRkas">
        <thead><tr>
          <th style="min-width:220px">Kode Rekening</th><th style="min-width:220px">Uraian</th>
          <th>Satuan</th><th>Banyak</th><th>Harga Satuan (Rp)</th><th class="num">Jumlah</th><th>Sumber Dana</th><th></th>
        </tr></thead>
        <tbody></tbody>
        <tfoot><tr><td colspan="5" class="text-right font-semibold">TOTAL</td><td class="num font-bold" id="grand">Rp 0</td><td colspan="2"></td></tr></tfoot>
      </table>
    </div>
    <div class="mt-4"><button class="btn">💾 Simpan RKAS</button></div>
  </div>
</form>
<template id="tpl">
<tr>
  <td><select name="rekening_id[]" class="inp" required>
    <option value="">— pilih —</option>
    <?php $__currentLoopData = $rekenings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($r->id); ?>"><?php echo e($r->kode); ?> · <?php echo e($r->nama); ?><?php if($r->komponen): ?> (<?php echo e($r->komponen->kode); ?>)<?php endif; ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </select></td>
  <td><input name="uraian[]" class="inp" required placeholder="uraian kegiatan"></td>
  <td><input name="satuan[]" class="inp" value="paket" style="width:90px"></td>
  <td><input name="banyak[]" type="number" min="1" value="1" class="inp qty" style="width:80px" oninput="hitung()"></td>
  <td><input name="harga[]" type="number" min="0" value="0" class="inp hrg" style="width:130px" oninput="hitung()"></td>
  <td class="num jml">Rp 0</td>
  <td><select name="sumber[]" class="inp" style="width:130px"><option>BOS Reguler</option><option>BOS Kinerja</option><option>BOSP Daerah</option><option>SILPA</option></select></td>
  <td><button type="button" class="badge bdel" onclick="this.closest('tr').remove();hitung()">✖</button></td>
</tr>
</template>
<script>
function tambahBaris(){ const t=document.getElementById('tblRkas').querySelector('tbody'); t.appendChild(document.getElementById('tpl').content.cloneNode(true)); }
function bawaRekomendasi(rc){
  tambahBaris();
  const tr=document.getElementById('tblRkas').querySelector('tbody tr:last-child');
  const sel=tr.querySelector('select[name="rekening_id[]"]');
  [...sel.options].forEach(o=>{ if(o.value==String(rc.rekening_id)) sel.value=o.value; });
  tr.querySelector('input[name="uraian[]"]').value=rc.uraian||'';
  tr.querySelector('.hrg').value=Math.max(rc.anggaran, rc.realisasi);
  tr.querySelector('.qty').value=1;
  hitung();
  tr.scrollIntoView({behavior:'smooth',block:'center'});
}
function fmt(n){ return 'Rp ' + n.toLocaleString('id-ID'); }
function hitung(){ let g=0; document.querySelectorAll('#tblRkas tbody tr').forEach(tr=>{ const q=+tr.querySelector('.qty').value||0,h=+tr.querySelector('.hrg').value||0,j=q*h; g+=j; tr.querySelector('.jml').textContent=fmt(j); }); document.getElementById('grand').textContent=fmt(g); }
tambahBaris();
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'Susun RKAS'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/rkas/create.blade.php ENDPATH**/ ?>