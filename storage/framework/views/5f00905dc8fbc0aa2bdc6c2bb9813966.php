<?php $__env->startSection('content'); ?>
<?php
  $nm = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  $kota = $sekolah->kab_kota ?? 'Makassar';
?>
<div class="toolbar nonprint"><button onclick="window.print()">🖨️ Cetak / Simpan PDF</button><a href="<?php echo e(route('laporan-arkas.penutupan-kas')); ?>?bulan=<?php echo e($tahun.'-'.str_pad($bulan,2,'0',STR_PAD_LEFT)); ?>">← Kembali</a></div>

<div style="text-align:center">
  <div style="font-size:13px;font-weight:800"><?php echo e(strtoupper($sekolah->nama)); ?></div>
  <div style="font-size:13px;font-weight:800;letter-spacing:.12em;margin-bottom:8px">REGISTER PENUTUPAN KAS</div>
</div>

<table style="width:100%;font-size:12px;border-collapse:collapse">
  <tbody style="vertical-align:top">
    <tr><td style="width:46%;padding:3px 0">Tanggal Penutupan Kas</td><td style="width:2%">:</td><td><?php echo e(\Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y')); ?></td></tr>
    <tr><td>Nama Penutupan Kas/ Pemegang Kas</td><td>:</td><td><?php echo e($pemegang->nama ?? '( ……………………… )'); ?></td></tr>
    <tr><td>Tanggal Penutupan Kas Bulan lalu</td><td>:</td><td><?php echo e(\Carbon\Carbon::parse($tanggalLalu)->translatedFormat('d F Y')); ?></td></tr>
    <tr><td>Jumlah Penerimaan (D)</td><td>:</td><td><?php echo e(rp($D)); ?></td></tr>
    <tr><td>Jumlah Pengeluaran (K)</td><td>:</td><td><?php echo e(rp($K)); ?></td></tr>
    <tr><td>Saldo Buku Kas Umum ( A = D - K )</td><td>:</td><td><?php echo e(rp($A)); ?></td></tr>
  </tbody>
</table>

<div style="font-size:12px;margin:10px 0 2px">Terdiri dari :</div>
<table class="tbl" style="width:100%;font-size:11.5px;border-collapse:collapse;margin-bottom:8px">
  <thead><tr><th style="width:22px">1.</th><th style="text-align:left">Lembar Uang Kertas</th><th class="num">Nilai</th><th class="num">Jumlah</th><th>Satuan</th><th class="num">Kali Nilai</th></tr></thead>
  <tbody>
    <?php $__currentLoopData = $denLembar; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <tr><td></td><td>Lembar Uang Kertas</td><td class="num"><?php echo e(rp($d['nilai'])); ?></td><td class="num"><?php echo e($d['jumlah']); ?></td><td style="text-align:center">lembar</td><td class="num"><?php echo e(rp($d['total'])); ?></td></tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <tr><td colspan="4"></td><td style="text-align:right;font-weight:700">Jumlah sub (1)</td><td class="num" style="font-weight:700"><?php echo e(rp(collect($denLembar)->sum('total'))); ?></td></tr>
  </tbody>
  <thead><tr><th>2.</th><th style="text-align:left">Keping Uang Logam</th><th class="num">Nilai</th><th class="num">Jumlah</th><th>Satuan</th><th class="num">Kali Nilai</th></tr></thead>
  <tbody>
    <?php $__currentLoopData = $denKeping; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <tr><td></td><td>Keping Uang Logam</td><td class="num"><?php echo e(rp($d['nilai'])); ?></td><td class="num"><?php echo e($d['jumlah']); ?></td><td style="text-align:center">keping</td><td class="num"><?php echo e(rp($d['total'])); ?></td></tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <tr><td colspan="4"></td><td style="text-align:right;font-weight:700">Jumlah sub (2)</td><td class="num" style="font-weight:700"><?php echo e(rp(collect($denKeping)->sum('total'))); ?></td></tr>
  </tbody>
  <thead><tr><th>3.</th><th style="text-align:left">Saldo Bank</th><th colspan="4"></th></tr></thead>
  <tbody>
    <tr><td></td><td>Saldo Bank</td><td colspan="3"></td><td class="num"><?php echo e(rp($bank)); ?></td></tr>
    <tr><td></td><td>Pendapatan Jasa Bank</td><td colspan="3"></td><td class="num"><?php echo e(rp(0)); ?></td></tr>
    <tr><td></td><td style="font-weight:700">( B ) Jumlah 1+2+3</td><td colspan="3"></td><td class="num" style="font-weight:700"><?php echo e(rp($tunai + $bank)); ?></td></tr>
  </tbody>
</table>

<div style="font-size:12px">Perbedaan Kas ( A - B ) : <b><?php echo e(rp($perbedaan)); ?></b></div>
<div style="font-size:11px;color:#444;margin-top:2px">Penjelasan perbedaan Kas <?php echo e($perbedaan != 0 ? '(isi bila ada selisih)' : ''); ?></div>

<div style="margin-top:28px;font-size:12px">
  <div style="text-align:right;margin-bottom:14px"><?php echo e($kota); ?>, <?php echo e(\Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y')); ?></div>
  <table style="width:100%;border-collapse:collapse">
    <tr>
      <td style="width:50%;text-align:center">Yang diperiksa,<br>Bendahara Pemegang kas</td>
      <td style="width:50%;text-align:center">Yang Memeriksa,<br>Kepala Sekolah</td>
    </tr>
    <tr style="height:70px"><td></td><td></td></tr>
    <tr>
      <td style="text-align:center;font-weight:700;text-decoration:underline"><?php echo e($pemegang->nama ?? '( ……………………… )'); ?></td>
      <td style="text-align:center;font-weight:700;text-decoration:underline"><?php echo e($kepsek->nama ?? '( ……………………… )'); ?></td>
    </tr>
    <tr>
      <td style="text-align:center">NIP. <?php echo e($pemegang->nip ?? '…'); ?></td>
      <td style="text-align:center">NIP. <?php echo e($kepsek->nip ?? '…'); ?></td>
    </tr>
  </table>
</div>

<div style="page-break-before:always;margin-top:10px">
  <div style="text-align:center;font-weight:800;font-size:13px;letter-spacing:.08em">BERITA ACARA PEMERIKSAAN KAS</div>
  <div style="font-size:12px;margin-top:10px;line-height:1.7">
    Pada hari ini <?php echo e(\Carbon\Carbon::parse($tanggal)->translatedFormat('l d F Y')); ?> jam waktu sekolah setempat, kami yang bertanda tangan di bawah ini :<br>
    Bendahara Pemegang Kas : <b><?php echo e($pemegang->nama ?? '( ……………………… )'); ?></b> — NIP <?php echo e($pemegang->nip ?? '…'); ?><br>
    Kepala Sekolah : <b><?php echo e($kepsek->nama ?? '( ……………………… )'); ?></b> — NIP <?php echo e($kepsek->nip ?? '…'); ?><br><br>
    Setelah melakukan penghitungan bersama atas dana yang ditempatkan pada kas sekolah, di gelas/uang kertas sebanyak
    <b><?php echo e(rp($tunai)); ?></b> <?php echo e($tunai > 0 ? '( ' . number_format($tunai, 0, ',', '.') . ' )' : ''); ?> dan saldo bank sebesar
    <b><?php echo e(rp($bank)); ?></b> <?php echo e($bank > 0 ? '( ' . number_format($bank, 0, ',', '.') . ' )' : ''); ?>, sehingga total Buku Kas Umum pada
    <?php echo e(strtoupper($nm[$bulan])); ?> <?php echo e($tahun); ?> adalah sebesar <b><?php echo e(rp($A)); ?></b> <?php echo e('(' . number_format($A, 0, ',', '.') . ')'); ?>.<br><br>
    Adapun hasil penghitungan tersebut sudah sesuai atau benar — kas ditutup pada tanggal
    <?php echo e(\Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y')); ?>.
  </div>
  <table style="width:100%;border-collapse:collapse;margin-top:36px">
    <tr>
      <td style="width:50%;text-align:center">Yang diperiksa,<br>Bendahara Pemegang kas</td>
      <td style="width:50%;text-align:center">Yang Memeriksa,<br>Kepala Sekolah</td>
    </tr>
    <tr style="height:70px"><td></td><td></td></tr>
    <tr>
      <td style="text-align:center;font-weight:700;text-decoration:underline"><?php echo e($pemegang->nama ?? '( ……………………… )'); ?></td>
      <td style="text-align:center;font-weight:700;text-decoration:underline"><?php echo e($kepsek->nama ?? '( ……………………… )'); ?></td>
    </tr>
    <tr>
      <td style="text-align:center">NIP. <?php echo e($pemegang->nip ?? '…'); ?></td>
      <td style="text-align:center">NIP. <?php echo e($kepsek->nip ?? '…'); ?></td>
    </tr>
  </table>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.print', ['title' => 'Register Penutupan Kas'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/laporan-arkas/penutupan-kas-cetak.blade.php ENDPATH**/ ?>