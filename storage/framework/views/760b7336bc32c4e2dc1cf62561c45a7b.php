<?php $__env->startSection('subtitle', 'Ringkasan kas, serapan, dan aktivitas tahun ' . $tahun); ?>
<?php $__env->startSection('content'); ?>
<div class="page-head">
  <div>
    <p class="page-desc">Posisi kas terakhir, status pajak, dan progres per tahap penyaluran.</p>
  </div>
  <div class="flex flex-wrap gap-2">
    <a href="<?php echo e(route('belanja.create')); ?>" class="btn btn-sm">➕ Input Belanja</a>
    <a href="<?php echo e(route('laporan.tahap')); ?>" class="btn2 btn-sm">🖨️ LPJ per Tahap</a>
  </div>
</div>


<?php if($pajakBelum > 0 && now()->day >= 12 && now()->day <= 20): ?>
<div class="card border-l-4 border-amber-500 flex flex-wrap items-center gap-3 text-sm">
  <span class="text-xl">⏰</span>
  <div class="flex-1 min-w-[16rem] leading-snug">
    <b>Tenggat pajak: setor &amp; lapor paling lambat <u>tgl 20</u></b>
    <span class="text-slate-500"> — <?php echo e(rp($pajakBelum)); ?> belum disetor (bukti lapor = NTPN).</span>
  </div>
  <a href="<?php echo e(route('setorpajak.index')); ?>" class="btn btn-sm">🗃️ Setor &amp; Lapor Pajak</a>
</div>
<?php endif; ?>
<?php if($bkuTerbuka && now()->day >= 24): ?>
<div class="card border-l-4 border-indigo-500 flex flex-wrap items-center gap-3 text-sm">
  <span class="text-xl">📕</span>
  <div class="flex-1 min-w-[16rem] leading-snug">
    <b>Akhir bulan: saatnya tutup BKU.</b>
    <span class="text-slate-500"> BKU bulan <?php echo e(\Carbon\Carbon::parse($bkuTerbuka.'-01')->translatedFormat('F Y')); ?> masih terbuka — tutup dulu agar bulan berikutnya bisa mulai.</span>
  </div>
  <a href="<?php echo e(route('bku', ['bulan' => $bkuTerbuka])); ?>" class="btn btn-sm">Buka BKU →</a>
</div>
<?php endif; ?>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

  <div class="card relative overflow-hidden">
    <div class="absolute left-0 top-0 h-full w-1.5 bg-emerald-500"></div>
    <div class="text-xs text-slate-500">Saldo Kas Tunai</div>
    <div class="text-xl font-bold text-emerald-700 mt-1"><?php echo e(rp($saldo['tunai'])); ?></div>
    <div class="mt-2 flex items-center justify-between text-[11px]">
      <span class="text-slate-400">dari total kas</span>
      <span class="badge bg-emerald-100 text-emerald-700"><?php echo e($tunaiPct); ?>%</span>
    </div>
    <div class="mt-1 h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-emerald-500" style="width:<?php echo e($tunaiPct); ?>%"></div></div>
  </div>

  <div class="card relative overflow-hidden">
    <div class="absolute left-0 top-0 h-full w-1.5 bg-sky-500"></div>
    <div class="text-xs text-slate-500">Saldo Kas Bank</div>
    <div class="text-xl font-bold text-sky-700 mt-1"><?php echo e(rp($saldo['bank'])); ?></div>
    <div class="mt-2 flex items-center justify-between text-[11px]">
      <span class="text-slate-400">dari total kas</span>
      <span class="badge bg-sky-100 text-sky-700"><?php echo e($bankPct); ?>%</span>
    </div>
    <div class="mt-1 h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-sky-500" style="width:<?php echo e($bankPct); ?>%"></div></div>
  </div>

  <div class="card relative overflow-hidden">
    <div class="absolute left-0 top-0 h-full w-1.5 bg-indigo-500"></div>
    <div class="text-xs text-slate-500">Dana Diterima (<?php echo e($tahun); ?>)</div>
    <div class="text-xl font-bold text-indigo-700 mt-1"><?php echo e(rp($pendapatan)); ?></div>
    <div class="mt-2 flex items-center justify-between text-[11px]">
      <span class="text-slate-400">dari pagu <?php echo e(rp($pagu)); ?></span>
      <span class="badge bg-indigo-100 text-indigo-700"><?php echo e($diterimaPct); ?>%</span>
    </div>
    <div class="mt-1 h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-indigo-500" style="width:<?php echo e(min(100,$diterimaPct)); ?>%"></div></div>
  </div>

  <div class="card flex items-center gap-4">
    <div class="w-16 h-16 rounded-full flex items-center justify-center shrink-0"
         style="background:conic-gradient(#6366f1 <?php echo e(min(100,$serapan)*3.6); ?>deg, #e2e8f0 0deg)">
      <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center text-sm font-bold text-indigo-700"><?php echo e($serapan); ?>%</div>
    </div>
    <div>
      <div class="text-xs text-slate-500">Serapan Anggaran</div>
      <div class="text-sm font-bold text-slate-700"><?php echo e(rp($belanja)); ?></div>
      <div class="text-[11px] text-slate-400">Dibelanjakan · bulan ini <?php echo e(rp($bulanIni)); ?></div>
    </div>
  </div>
</div>


<div class="grid gap-4 lg:grid-cols-3">
  <div class="card">
    <div class="flex items-center justify-between">
      <h3 class="font-semibold text-slate-700 text-sm">🧾 Status Pajak <span class="text-[10px] text-slate-400">(wajib lapor)</span></h3>
      <span class="badge <?php echo e($pajakPct===100.0?'bg-emerald-100 text-emerald-700':'bg-amber-100 text-amber-700'); ?>"><?php echo e($pajakPct); ?>% disetor</span>
    </div>
    <div class="mt-3 h-2.5 rounded-full bg-amber-100"><div class="h-2.5 rounded-full bg-emerald-500" style="width:<?php echo e($pajakPct); ?>%"></div></div>
    <div class="mt-3 grid grid-cols-3 gap-2 text-center text-[11px] text-slate-500">
      <div class="bg-slate-50 rounded-lg py-2"><div class="font-bold text-slate-700 text-sm"><?php echo e(rp($pajakTerima)); ?></div>Diterima</div>
      <div class="bg-slate-50 rounded-lg py-2"><div class="font-bold text-emerald-700 text-sm"><?php echo e(rp($pajakSudah)); ?></div>Sudah setor</div>
      <div class="bg-slate-50 rounded-lg py-2"><div class="font-bold <?php echo e($pajakBelum>0?'text-amber-600':'text-slate-400'); ?> text-sm"><?php echo e(rp($pajakBelum)); ?></div>Belum setor</div>
    </div>
    <?php if($pajakBelum > 0): ?>
      <div class="mt-3 text-[10px] text-slate-500 leading-snug">Zona pajak ARKAS: setor & lapor <b>paling lambat tgl 20</b> bulan berikutnya — bukti lapor = NTPN.</div>
      <a href="<?php echo e(route('setorpajak.index')); ?>" class="mt-1 btn w-full justify-center text-xs">➜ Segera Setor & Lapor Pajak</a>
    <?php endif; ?>
  </div>

  <div class="card lg:col-span-2">
    <h3 class="font-semibold text-slate-700 text-sm mb-3">📈 Realisasi per Tahap Penyaluran</h3>
    <div class="space-y-3">
      <?php $__empty_1 = true; $__currentLoopData = $perTahap; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <div>
        <div class="flex justify-between text-xs mb-1">
          <span class="font-semibold text-slate-600"><?php echo e($tp['nama']); ?></span>
          <span class="text-slate-400">cair <?php echo e(rp($tp['pendapatan'])); ?> · dibelanjakan <b class="text-slate-600"><?php echo e(rp($tp['belanja'])); ?></b></span>
          <span class="font-bold <?php echo e($tp['persenSerapan']>=80?'text-emerald-600':($tp['persenSerapan']>=40?'text-amber-500':'text-rose-500')); ?>"><?php echo e($tp['persenSerapan']); ?>%</span>
        </div>
        <div class="h-2.5 rounded-full bg-slate-100">
          <div class="h-2.5 rounded-full <?php echo e($tp['persenSerapan']>=80?'bg-emerald-500':($tp['persenSerapan']>=40?'bg-amber-400':'bg-rose-400')); ?>" style="width:<?php echo e(min(100,$tp['persenSerapan'])); ?>%"></div>
        </div>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <p class="text-sm text-slate-400">Belum ada dana cair atau belanja per tahap.</p>
      <?php endif; ?>
    </div>
  </div>
</div>


<div class="grid gap-4 lg:grid-cols-3">

  <div class="card">
    <h3 class="font-semibold text-slate-700 text-sm mb-4">📊 Belanja Bulanan</h3>
    <div class="flex items-end gap-1.5 h-32">
      <?php $bulanSingkat = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des']; ?>
      <?php $__currentLoopData = $perBulan; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $nilai): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="flex-1 flex flex-col items-center gap-1 group" title="<?php echo e($bulanSingkat[$idx]); ?>: <?php echo e(rp($nilai)); ?>">
          <span class="text-[9px] text-slate-400 opacity-0 group-hover:opacity-100"><?php echo e($maxBulan>0 ? round($nilai/$maxBulan*100) : 0); ?>%</span>
          <div class="w-full rounded-t <?php echo e($nilai>0?'bg-indigo-400 group-hover:bg-indigo-600':''); ?>" style="height:<?php echo e($maxBulan>0 ? max(3, round($nilai/$maxBulan*100)) : 0); ?>%"></div>
          <span class="text-[9px] text-slate-400"><?php echo e($bulanSingkat[$idx]); ?></span>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </div>

  <div class="card">
    <h3 class="font-semibold text-slate-700 text-sm mb-3">🥧 Komposisi Belanja (Top 5 Rekening)</h3>
    <div class="space-y-3">
      <?php $__empty_1 = true; $__currentLoopData = $topRekening; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <div>
        <div class="flex justify-between text-xs mb-1">
          <span class="text-slate-600"><span class="font-mono text-[10px] text-slate-400"><?php echo e($tr['kode']); ?></span> <?php echo e(Str::limit($tr['nama'],30)); ?></span>
          <span class="font-bold text-slate-700"><?php echo e($tr['persen']); ?>%</span>
        </div>
        <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-violet-500" style="width:<?php echo e($tr['persen']); ?>%"></div></div>
      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <p class="text-sm text-slate-400">Belum ada belanja.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h3 class="font-semibold text-slate-700 text-sm mb-3">📣 Pengumuman Dinas</h3>
    <?php $__empty_1 = true; $__currentLoopData = $pengumuman; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <div class="border-l-4 border-indigo-200 pl-3 py-1.5 mb-2">
        <div class="font-semibold text-sm"><?php echo e($p->judul); ?></div>
        <div class="text-xs text-slate-500"><?php echo e(Str::limit($p->isi, 90)); ?></div>
        <div class="text-[10px] text-slate-400 mt-0.5"><?php echo e($p->created_at->format('d/m/Y H:i')); ?></div>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <p class="text-sm text-slate-400">Tidak ada pengumuman.</p>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h3 class="card-title mb-3">🕘 Transaksi Terbaru</h3>
  <div class="tbl-wrap">
  <table class="tbl">
    <thead><tr><th>BKU</th><th>Tanggal</th><th>Jenis</th><th>Uraian</th><th class="num">Nilai</th></tr></thead>
    <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $terbaru; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <tr>
        <td>#<?php echo e($t->no_bku); ?></td>
        <td><?php echo e($t->tanggal->format('d/m/Y')); ?></td>
        <td><span class="badge <?php echo e($t->type==='pendapatan'?'bg-emerald-100 text-emerald-700':($t->type==='belanja'?'bg-rose-100 text-rose-700':'bg-slate-100 text-slate-600')); ?>"><?php echo e($t->labelType()); ?></span></td>
        <td><?php echo e(Str::limit($t->keterangan, 60)); ?></td>
        <td class="num"><?php echo e(rp($t->total)); ?></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <tr><td colspan="5" class="text-center text-slate-400 py-6">Belum ada transaksi tahun <?php echo e($tahun); ?>.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
<div class="grid gap-4 lg:grid-cols-2">
  <div class="card">
    <h3 class="font-semibold text-slate-700 text-sm mb-3">📈 Tren Belanja per Bulan — interaktif (<?php echo e($tahun); ?>)</h3>
    <div class="relative h-56"><canvas id="chBulanSkl"></canvas></div>
  </div>
  <div class="card">
    <h3 class="font-semibold text-slate-700 text-sm mb-3">🎯 Anggaran vs Realisasi per Komponen</h3>
    <?php if(count($serapanKomponen ?? [])): ?>
      <div class="relative h-56"><canvas id="chKompSkl"></canvas></div>
    <?php else: ?>
      <p class="text-xs text-slate-400 py-10 text-center">Belum ada RKAS disahkan maupun belanja tahun ini.</p>
    <?php endif; ?>
  </div>
</div>
<?php $__env->startPush('scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function(){
  if(!window.Chart) return;
  const LBL=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
  const fmtUang=v=>'Rp '+(v>=1000000?(v/1000000).toLocaleString('id-ID')+' jt':(v/1000).toLocaleString('id-ID')+' rb');
  new Chart(document.getElementById('chBulanSkl'),{type:'line',
    data:{labels:LBL,datasets:[{data:<?php echo json_encode(array_values($perBulan), 15, 512) ?>,borderColor:'#0891b2',backgroundColor:'rgba(8,145,178,.15)',fill:true,tension:.35,pointRadius:3,pointHoverRadius:6}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>fmtUang(c.parsed.y)}}},scales:{y:{ticks:{callback:fmtUang}}}}});
  const elK=document.getElementById('chKompSkl');
  if(elK){
    new Chart(elK,{type:'bar',
      data:{labels:<?php echo json_encode(($serapanKomponen ?? collect())->pluck('nama'), 15, 512) ?>,datasets:[
        {label:'Anggaran RKAS',data:<?php echo json_encode(($serapanKomponen ?? collect())->pluck('anggaran'), 15, 512) ?>,backgroundColor:'#c7d2fe',borderRadius:4},
        {label:'Realisasi',data:<?php echo json_encode(($serapanKomponen ?? collect())->pluck('realisasi'), 15, 512) ?>,backgroundColor:'#4f46e5',borderRadius:4}]},
      options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{tooltip:{callbacks:{label:c=>c.dataset.label+': '+fmtUang(c.parsed.x)}}},scales:{x:{ticks:{callback:fmtUang}}}}});
  }
})();
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', ['title' => 'Dashboard'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/dashboard.blade.php ENDPATH**/ ?>