<?php $__env->startSection('content'); ?>
<?php
  $hariId = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][$tgl->dayOfWeek];
  $tglId = $tgl->translatedFormat('d F Y');
  $tempat = $sekolah->kab_kota ?: $sekolah->kecamatan;
  $alamatSetdik = collect([$sekolah->alamat, $sekolah->kecamatan, $sekolah->kab_kota, $sekolah->provinsi])->filter()->implode(', ');
  $penyediaNama = $nota?->nama_toko;
  $penyediaAlamat = $nota?->alamat_toko;
  $penyediaTelp = $nota?->no_telp;
  $penyediaNpwp = $nota?->npwp;
  $paket = $belanja->keterangan;
  $noSp = $nomorDefault;
  $noNego = $nomorDefault.'/-NEGO';
  $noHp = $nomorDefault.'/-HP';
  $noBast = $nomorDefault.'/-BAST';
  $kepsekNama = $kepsek?->nama;
  $kepsekNip = $kepsek?->nip ? 'NIP. '.$kepsek->nip : '';
  $komiteNama = $komite?->nama;
  $totalPembayaran = $totalHarga; // format ARKAS: harga + kirim (PPN hanya info)
?>
<style>
  .isi{display:inline-block;min-width:6rem;padding:0 .3rem;border-bottom:1px dotted #64748b}
  .isi:empty::before{content:'\00a0\00a0\00a0\00a0\00a0\00a0\00a0\00a0\00a0\00a0\00a0\00a0'}
  .isi{outline:none}
  .isi:focus{background:#fef9c3}
  h3.judul{text-align:center;font-size:15px;margin:0 0 .6rem}
  .blockinfo td{border:none;padding:.1rem .4rem;vertical-align:top}
  .blockinfo{border-collapse:collapse}
  .blockinfo-bordered td{border:1px solid #94a3b8;padding:.25rem .5rem}
  .ttd3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:2rem;text-align:center;font-size:.82rem;margin-top:2.4rem}
</style>

<div class="toolbar nonprint">
  <button onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
  <a href="<?php echo e(route('belanja.show', $belanja)); ?>">← Kembali ke belanja</a>
  <span style="color:#64748b">Isi bagian bertitik-titik (klik langsung pada garis) sebelum mencetak — nomor, tanggal, nama, kuantitas, spesifikasi.</span>
</div>


<div>
  <h3 class="judul">
    <span class="isi" contenteditable="true"><?php echo e($noSp); ?></span><br>
    SURAT PESANAN (SP)
  </h3>
  <div style="display:flex;justify-content:space-between;gap:2rem;font-size:.82rem">
    <table class="blockinfo">
      <tr><td>Nama Satuan Pendidikan:</td></tr>
      <tr><td><b><?php echo e($sekolah->nama); ?></b></td></tr>
      <tr><td>Alamat Satdik: <?php echo e($alamatSetdik); ?></td></tr>
    </table>
    <table class="blockinfo">
      <tr><td>Nomor SP: <span class="isi" contenteditable="true"><?php echo e($noSp); ?></span></td></tr>
      <tr><td>Tanggal SP: <?php echo e($tglId); ?></td></tr>
      <tr><td>Sumber Dana: <?php echo e($belanja->bosType?->nama); ?></td></tr>
      <tr><td>Tanggal Negosiasi: <?php echo e($tglId); ?></td></tr>
      <tr><td>Waktu Pengerjaan Pesanan: <span class="isi" contenteditable="true"></span></td></tr>
      <tr><td>Waktu Penyelesaian Pesanan: <span class="isi" contenteditable="true"></span></td></tr>
    </table>
  </div>

  <p style="font-size:.82rem">Paket Pesanan: <b><?php echo e($paket); ?></b></p>

  <table class="blockinfo" style="font-size:.82rem">
    <tr><td>Penyedia:</td></tr>
    <tr><td>Nama Penyedia: <span class="isi" contenteditable="true"><?php echo e($penyediaNama); ?></span></td></tr>
    <tr><td>Alamat Penyedia: <span class="isi" contenteditable="true"><?php echo e($penyediaAlamat); ?></span></td></tr>
    <tr><td>NPWP Penyedia: <span class="isi" contenteditable="true"><?php echo e($penyediaNpwp); ?></span></td></tr>
    <tr><td>Pembeli:</td></tr>
    <tr><td>Nama Perwakilan Satdik: <span class="isi" contenteditable="true"><?php echo e($kepsekNama); ?></span></td></tr>
    <tr><td>Alamat Satdik: <?php echo e($alamatSetdik); ?></td></tr>
  </table>

  <p style="font-size:.82rem">Rincian Pekerjaan:</p>
  <table class="tbl">
    <thead><tr><th>No.</th><th>Uraian Barang/Jasa</th><th class="num">Qty</th><th>Satuan</th><th class="num">Harga Satuan</th><th class="num">Total Harga</th></tr></thead>
    <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $rincian; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <tr>
        <td><?php echo e($i + 1); ?></td>
        <td><?php echo e($d['uraian']); ?> <?php if($d['rekening']): ?><span class="text-slate-400">(<?php echo e($d['rekening']); ?>)</span><?php endif; ?></td>
        <td class="num"><span class="isi" contenteditable="true">1</span></td>
        <td><span class="isi" contenteditable="true"></span></td>
        <td class="num"><?php echo e(rp($d['nilai'])); ?></td>
        <td class="num"><?php echo e(rp($d['nilai'])); ?></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <tr><td colspan="6">—</td></tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr><td colspan="5" class="num"><b>Total Harga</b></td><td class="num"><b><?php echo e(rp($totalHarga)); ?></b></td></tr>
      <tr><td colspan="5" class="num">Biaya Pengiriman</td><td class="num"><span class="isi" contenteditable="true">Rp 0</span></td></tr>
      <tr><td colspan="5" class="num">PPN (dipungut)</td><td class="num"><?php echo e(rp($pajak)); ?></td></tr>
      <tr><td colspan="5" class="num"><b>Total Pembayaran</b></td><td class="num"><b><?php echo e(rp($totalPembayaran)); ?></b></td></tr>
      <tr><td colspan="6"><i>Terbilang: <?php echo e(ucfirst(terbilang((int) $totalPembayaran)).' rupiah'); ?></i></td></tr>
    </tfoot>
  </table>

  <p class="font-bold" style="font-size:.82rem;margin-top:.8rem">Hak dan Kewajiban Penyedia dan Satuan Pendidikan:</p>
  <ol style="font-size:.78rem">
    <li>Penyedia berkewajiban menyediakan barang/jasa sesuai dengan spesifikasi, jumlah, dan jadwal pada Surat Pesanan ini.</li>
    <li>Penyedia berhak melakukan penagihan dalam bentuk Proforma Invoice sebelum barang/jasa diserahkan.</li>
    <li>Penyedia berkewajiban memberikan Invoice sebagai bukti pelunasan setelah barang/jasa diterima.</li>
    <li>Pelaksana dalam kapasitas mewakili Satuan Pendidikan berhak memeriksa dan menguji barang/jasa yang diserahkan.</li>
    <li>Pelaksana berhak menolak barang/jasa yang tidak sesuai dengan spesifikasi yang disepakati.</li>
    <li>Pelaksana wajib membayar sesuai hasil negosiasi yang disepakati kedua belah pihak.</li>
    <li>Segala perselisihan diselesaikan secara musyawarah untuk mufakat.</li>
  </ol>

  <div class="ttd-grid mt-8">
    <div>
      <?php echo e($tempat); ?>, <span class="isi" contenteditable="true"><?php echo e($tglId); ?></span><br>
      Ketua Komite Pelaksana<br>
      Satuan Pendidikan<br><br><br><br>
      <b><span class="isi" contenteditable="true"><?php echo e($komiteNama); ?></span></b>
    </div>
    <div>
      <br>Pesanan diterima oleh Pelaksana,<br><br><br><br>
      <b><?php echo e($kepsekNama); ?></b><br>
      <?php echo e($kepsekNip); ?>

    </div>
  </div>
</div>


<div class="pg">
  <h3 class="judul">
    DOKUMEN BERITA ACARA NEGOSIASI<br>
    Nomor: <span class="isi" contenteditable="true"><?php echo e($noNego); ?></span>
  </h3>
  <table class="blockinfo" style="width:100%;font-size:.82rem">
    <tr>
      <td style="width:50%">Pelaksana<br><b><?php echo e($sekolah->nama); ?></b><br><?php echo e($alamatSetdik); ?></td>
      <td>Penyedia<br><b><span class="isi" contenteditable="true"><?php echo e($penyediaNama); ?></span></b><br><span class="isi" contenteditable="true"><?php echo e($penyediaAlamat); ?></span></td>
    </tr>
  </table>
  <p style="font-size:.82rem">Tanggal Terimanya Barang dan Jasa: <span class="isi" contenteditable="true">—</span></p>
  <p style="font-size:.82rem"><b>Hasil Negosiasi:</b></p>
  <table class="tbl">
    <thead><tr><th>No.</th><th>Nama Barang</th><th class="num">Qty</th><th class="num">Harga Penawaran</th><th class="num">Hasil Negosiasi</th><th>Keterangan</th></tr></thead>
    <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $rincian; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <tr>
        <td><?php echo e($i + 1); ?></td><td><?php echo e($d['uraian']); ?></td>
        <td class="num"><span class="isi" contenteditable="true">1</span></td>
        <td class="num"><?php echo e(rp($d['nilai'])); ?></td>
        <td class="num"><?php echo e(rp($d['nilai'])); ?></td>
        <td><span class="isi" contenteditable="true"></span></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <tr><td colspan="6">—</td></tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr><td colspan="4" class="num"><b>Total</b></td><td class="num"><b><?php echo e(rp($totalHarga)); ?></b></td><td></td></tr>
      <tr><td colspan="4" class="num">Biaya Pengiriman</td><td class="num"><span class="isi" contenteditable="true">Rp 0</span></td><td></td></tr>
    </tfoot>
  </table>

  <div class="ttd-grid mt-8">
    <div>
      Calon Penyedia<br><br><br><br>
      <b><span class="isi" contenteditable="true"></span></b>
    </div>
    <div>
      <?php echo e($tempat); ?>, <span class="isi" contenteditable="true"><?php echo e($tglId); ?></span><br>
      Pelaksana<br><br><br><br>
      <b><?php echo e($kepsekNama); ?></b><br>
      <?php echo e($kepsekNip); ?>

    </div>
  </div>
</div>


<div class="pg">
  <h3 class="judul">
    SURAT HASIL PEMERIKSAAN<br>
    Nomor: <span class="isi" contenteditable="true"><?php echo e($noHp); ?></span>
  </h3>
  <p style="font-size:.82rem">Pada hari ini, <?php echo e($hariId); ?> tanggal <span class="isi" contenteditable="true"><?php echo e($tglId); ?></span>, pihak pemeriksa telah memeriksa hasil pekerjaan dengan surat pesanan:</p>
  <table class="blockinfo blockinfo-bordered" style="font-size:.82rem">
    <tr><td>Nomor Surat Pesanan</td><td class="num"><?php echo e($noSp); ?></td></tr>
    <tr><td>Tanggal</td><td class="num"><?php echo e($tgl->translatedFormat('d/m/Y')); ?></td></tr>
    <tr><td>Nama Pekerjaan</td><td class="num"><?php echo e($paket); ?></td></tr>
    <tr><td>Tahun</td><td class="num"><?php echo e($belanja->tahun); ?></td></tr>
  </table>
  <p style="font-size:.82rem">Yang bertandatangan di bawah ini:</p>
  <table class="blockinfo" style="font-size:.82rem">
    <tr><td>Nama</td><td class="num"><?php echo e($kepsekNama); ?></td></tr>
    <tr><td>Jabatan</td><td class="num">Kepala Sekolah</td></tr>
    <tr><td>Nama Satuan Pendidikan</td><td class="num"><?php echo e($sekolah->nama); ?></td></tr>
    <tr><td>Alamat Satuan Pendidikan</td><td class="num"><?php echo e($alamatSetdik); ?></td></tr>
  </table>
  <p style="font-size:.82rem">Telah melakukan pemeriksaan terhadap hasil pekerjaan sesuai dengan spesifikasi Surat Pesanan. Hasil pemeriksaan sebagai berikut:</p>
  <table class="tbl">
    <thead><tr><th>No.</th><th>Uraian Barang/Jasa</th><th class="num">Qty</th><th>Spesifikasi</th><th>Kondisi</th></tr></thead>
    <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $rincian; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <tr>
        <td><?php echo e($i + 1); ?></td>
        <td><?php echo e($d['uraian']); ?></td>
        <td class="num"><span class="isi" contenteditable="true">1</span></td>
        <td><span class="isi" contenteditable="true"></span></td>
        <td><span class="isi" contenteditable="true">Baik</span></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <tr><td colspan="5">—</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <p style="font-size:.82rem">Demikian Surat Pemeriksaan Hasil Pekerjaan ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.</p>

  <div class="ttd-grid mt-8">
    <div>
      PIHAK PERTAMA,<br><br><br><br>
      <b><span class="isi" contenteditable="true"></span></b>
    </div>
    <div>
      <?php echo e($tempat); ?>, <span class="isi" contenteditable="true"><?php echo e($tglId); ?></span><br>
      PIHAK KEDUA,<br><br><br><br>
      <b><?php echo e($kepsekNama); ?></b><br>
      <?php echo e($kepsekNip); ?>

    </div>
  </div>
</div>


<div class="pg">
  <h3 class="judul">
    BERITA ACARA SERAH TERIMA<br>
    Nomor: <span class="isi" contenteditable="true"><?php echo e($noBast); ?></span>
  </h3>
  <p style="font-size:.82rem">Pada hari ini, <?php echo e($hariId); ?> tanggal <span class="isi" contenteditable="true"><?php echo e($tglId); ?></span>, kami yang tercantum di bawah ini:</p>
  <table class="blockinfo" style="width:100%;font-size:.82rem">
    <tr>
      <td style="width:50%">
        Nama: <span class="isi" contenteditable="true"></span><br>
        Nama Penyedia: <b><span class="isi" contenteditable="true"><?php echo e($penyediaNama); ?></span></b><br>
        Alamat Penyedia: <span class="isi" contenteditable="true"><?php echo e($penyediaAlamat); ?></span><br>
        Sebagai pihak yang menyerahkan, selanjutnya disebut <b>PIHAK PERTAMA</b>
      </td>
      <td>
        Nama: <?php echo e($kepsekNama); ?><br>
        Jabatan: Kepala Sekolah<br>
        Nama Satuan Pendidikan: <?php echo e($sekolah->nama); ?><br>
        Alamat Satuan Pendidikan: <?php echo e($alamatSetdik); ?><br>
        Sebagai pihak yang menerima, selanjutnya disebut <b>PIHAK KEDUA</b>
      </td>
    </tr>
  </table>
  <table class="blockinfo blockinfo-bordered" style="font-size:.82rem">
    <tr><td>Nomor Surat Pesanan</td><td class="num"><?php echo e($noSp); ?></td></tr>
    <tr><td>Tanggal Pesanan</td><td class="num"><?php echo e($tgl->translatedFormat('d/m/Y')); ?></td></tr>
    <tr><td>Nama Pekerjaan</td><td class="num"><?php echo e($paket); ?></td></tr>
    <tr><td>Tanggal Diterima</td><td class="num"><span class="isi" contenteditable="true">—</span></td></tr>
  </table>
  <p style="font-size:.82rem">PIHAK PERTAMA menyerahkan hasil pekerjaan Pengadaan Barang/Jasa melalui Berita Acara Serah Terima ini kepada PIHAK KEDUA.</p>
  <p style="font-size:.82rem">Berita Acara Serah Terima ini berfungsi sebagai bukti serah terima hasil pekerjaan yang telah diperiksa dan dinyatakan memenuhi persyaratan.</p>

  <div class="ttd3">
    <div>
      <?php echo e($tempat); ?>, <span class="isi" contenteditable="true"><?php echo e($tglId); ?></span><br>
      PIHAK PERTAMA,<br><br><br><br>
      <b><span class="isi" contenteditable="true"></span></b>
    </div>
    <div>
      PIHAK KEDUA,<br><br><br><br>
      <b><?php echo e($kepsekNama); ?></b><br>
      <?php echo e($kepsekNip); ?>

    </div>
    <div>
      PEMERIKSA BARANG,<br><br><br><br>
      <b><?php echo e($kepsekNama); ?></b><br>
      <?php echo e($kepsekNip); ?>

    </div>
  </div>

  <p style="font-size:.82rem;margin-top:1rem"><b>Lampiran Rincian Pekerjaan:</b></p>
  <table class="tbl">
    <thead><tr><th>No.</th><th>Uraian Barang/Jasa</th><th class="num">Jumlah Dipesan</th><th class="num">Diterima Kondisi Baik</th><th class="num">Diterima Kondisi Rusak</th></tr></thead>
    <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $rincian; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <tr>
        <td><?php echo e($i + 1); ?></td><td><?php echo e($d['uraian']); ?></td>
        <td class="num"><span class="isi" contenteditable="true">1</span></td>
        <td class="num"><span class="isi" contenteditable="true">1</span></td>
        <td class="num"><span class="isi" contenteditable="true">0</span></td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <tr><td colspan="5">—</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>


<div class="pg">
  <h3 class="judul">DOKUMEN PENGADAAN BARANG DAN JASA</h3>
  <table class="tbl" style="font-size:.8rem">
    <tbody>
      <tr><th colspan="2" style="background:#e2e8f0">Nama Penyedia</th></tr>
      <tr><td>Penyedia</td><td><span class="isi" contenteditable="true"><?php echo e($penyediaNama); ?></span></td></tr>
      <tr><td>Alamat Penyedia</td><td><span class="isi" contenteditable="true"><?php echo e($penyediaAlamat); ?></span></td></tr>
      <tr><td>Nomor Telp.</td><td><span class="isi" contenteditable="true"><?php echo e($penyediaTelp); ?></span></td></tr>
      <tr><td>NPWP</td><td><span class="isi" contenteditable="true"><?php echo e($penyediaNpwp); ?></span></td></tr>
      <tr><td>Nama Perwakilan Penyedia</td><td><span class="isi" contenteditable="true"></span></td></tr>
      <tr><th colspan="2" style="background:#e2e8f0">Berita Acara Negosiasi</th></tr>
      <tr><td>Nomor</td><td><?php echo e($noNego); ?></td></tr>
      <tr><td>Tanggal Negosiasi</td><td><?php echo e($tglId); ?></td></tr>
      <tr><td>Tanggal Terima</td><td><span class="isi" contenteditable="true">—</span></td></tr>
      <tr><td>Biaya Pengiriman</td><td>Rp 0</td></tr>
      <tr><th colspan="2" style="background:#e2e8f0">Surat Pesanan</th></tr>
      <tr><td>Paket Pesanan</td><td><?php echo e($paket); ?></td></tr>
      <tr><td>Nomor</td><td><?php echo e($noSp); ?></td></tr>
      <tr><td>Tanggal</td><td><?php echo e($tgl->translatedFormat('d/m/Y')); ?></td></tr>
      <tr><td>Waktu Pelaksanaan</td><td><span class="isi" contenteditable="true">—</span></td></tr>
      <tr><td>Waktu Penyelesaian</td><td><span class="isi" contenteditable="true">—</span></td></tr>
      <tr><th colspan="2" style="background:#e2e8f0">Surat Hasil Pemeriksaan</th></tr>
      <tr><td>Nomor Pemeriksaan</td><td><?php echo e($noHp); ?></td></tr>
      <tr><td>Tanggal</td><td><span class="isi" contenteditable="true">—</span></td></tr>
      <tr><td>Nomor BAST</td><td><?php echo e($noBast); ?></td></tr>
      <tr><th colspan="2" style="background:#e2e8f0">Rincian</th></tr>
      <tr><td>Jumlah Barang</td><td><?php echo e($rincian->count()); ?></td></tr>
      <tr><td>Kuantitas</td><td><?php echo e($rincian->count()); ?></td></tr>
      <tr><td>Total Belanja (Hasil Negosiasi)</td><td><b><?php echo e(rp($totalHarga)); ?></b></td></tr>
    </tbody>
  </table>
  <p class="text-xs mt-4 text-slate-400">BKU #<?php echo e($belanja->no_bku); ?> · <?php echo e($tgl->translatedFormat('d/m/Y')); ?> · <?php echo e($belanja->stage?->nama); ?> · Satuan Pendidikan: <?php echo e($sekolah->nama); ?></p>
</div>

<script>
  document.querySelectorAll('.isi').forEach(function (el) {
    el.onkeydown = function (e) {
      if (e.key === 'Enter') { e.preventDefault(); this.blur(); }
    };
  });
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.print', ['title' => 'Paket Dokumen SPJ — Belanja BKU #'.$belanja->no_bku], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\LENOVO\Downloads\siapbosp-app-v12\resources\views/pengeluaran/spj.blade.php ENDPATH**/ ?>