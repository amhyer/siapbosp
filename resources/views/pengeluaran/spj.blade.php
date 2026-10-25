@extends('layouts.print', ['title' => 'Paket Dokumen SPJ — Belanja BKU #'.$belanja->no_bku])
@section('content')
@php
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
  // Permendikdasmen No 15/2026 Pasal 12 ayat (4): dokumen perencanaan bebas
  // untuk belanja paling banyak Rp1.000.000. Di atas itu wajib ada.
  $ambangPerencanaan = 1000000;
  $wajibDokumen = (int) $totalHarga > $ambangPerencanaan;
  $noPerencanaan = $nomorDefault.'/-RENCANA';
  $noBanding = $nomorDefault.'/-BANDING';
@endphp
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
  <a href="{{ route('belanja.show', $belanja) }}">← Kembali ke belanja</a>
  <span style="color:#64748b">Isi bagian bertitik-titik (klik langsung pada garis) sebelum mencetak — nomor, tanggal, nama, kuantitas, spesifikasi.</span>
</div>

{{-- ================= 0) RINGKASAN KEPATUHAN BELANJA (Permendikdasmen 15/2026) ================= --}}
<div class="pg">
  <h3 class="judul">
    RINGKASAN KEPATUHAN BELANJA BARANG/JASA<br>
    Permendikdasmen No 15 Tahun 2026
  </h3>
  <table class="blockinfo blockinfo-bordered" style="width:100%;font-size:.8rem">
    <tr><td style="width:38%">Satuan Pendidikan</td><td>{{ $sekolah->nama }}</td></tr>
    <tr><td>Nomor Paket / BKU</td><td>{{ $nomorDefault }} · BKU #{{ $belanja->no_bku }}</td></tr>
    <tr><td>Paket Pesanan</td><td>{{ $paket }}</td></tr>
    <tr><td>Total Belanja</td><td><b>{{ rp($totalHarga) }}</b></td></tr>
    <tr><td>Status Dokumen Perencanaan</td><td>
      @if($wajibDokumen)
        <b>WAJIB</b> — belanja di atas Rp1.000.000 (Pasal 12 ayat 4)
      @else
        <b>DIKECUALIKAN</b> — belanja paling banyak Rp1.000.000 (Pasal 12 ayat 4)
      @endif
    </td></tr>
  </table>

  <p style="font-size:.82rem;margin-top:.7rem"><b>Tahapan Belanja (Pasal 11):</b> persiapan → pemilihan barang/jasa &amp; penyedia → pelaksanaan kesepakatan → pelaporan.</p>
  <table class="tbl" style="font-size:.78rem">
    <thead><tr><th style="width:32%">Bukti pelaporan (Pasal 24 ayat 2)</th><th>Dokumen dalam paket ini</th><th style="width:22%">Status</th></tr></thead>
    <tbody>
      <tr>
        <td>a. Bukti pelaksanaan persiapan Belanja</td>
        <td>Dokumen Perencanaan (KAK/RKA)</td>
        <td>
          @if($wajibDokumen)
            <span class="badge bg-emerald-100 text-emerald-700">Wajib dilampirkan</span>
          @else
            <span class="badge bg-slate-100 text-slate-600">Dikecualikan (≤ Rp1 jt)</span>
          @endif
        </td>
      </tr>
      <tr><td>b. Bukti hasil pembandingan</td><td>Bukti Perbandingan Harga (≥ 2 penyedia)</td>
        <td>
          @if($wajibDokumen)
            <span class="badge bg-emerald-100 text-emerald-700">Wajib dilampirkan</span>
          @else
            <span class="badge bg-slate-100 text-slate-600">Dikecualikan (≤ Rp1 jt)</span>
          @endif
        </td>
      </tr>
      <tr><td>c. Bukti negosiasi</td><td>Berita Acara Negosiasi</td><td><span class="badge bg-emerald-100 text-emerald-700">Terlampir</span></td></tr>
      <tr><td>d. Bukti kesepakatan belanja</td><td>Surat Pesanan (SP)</td><td><span class="badge bg-emerald-100 text-emerald-700">Terlampir</span></td></tr>
      <tr><td>e. Bukti pemeriksaan &amp; penerimaan barang/jasa</td><td>Surat Hasil Pemeriksaan + Berita Acara Serah Terima</td><td><span class="badge bg-emerald-100 text-emerald-700">Terlampir</span></td></tr>
      <tr><td>f. Bukti pembayaran</td><td>Surat Pesanan (kolom pembayaran) + bukti transfer/kuitansi</td><td><span class="badge bg-emerald-100 text-emerald-700">Terlampir</span></td></tr>
    </tbody>
  </table>

  <p style="font-size:.78rem;margin-top:.6rem">
    <b>Catatan:</b> pembayaran di luar SiPLah dilaksanakan <b>setelah barang/jasa diterima</b> (Pasal 18 ayat 5). Pelaporan disampaikan melalui sistem aplikasi rencana kegiatan dan anggaran yang disediakan Kementerian; bila sistem belum memfasilitasi, pelaporan dapat dilakukan manual (Pasal 25–26). Pengawasan dilakukan Kementerian, pemerintah daerah, dan komite sekolah (Pasal 31–33).
  </p>
</div>

{{-- ================= 1) DOKUMEN PERENCANAAN (persiapan belanja) ================= --}}
<div class="pg">
  <h3 class="judul">
    DOKUMEN PERENCANAAN BELANJA<br>
    Nomor: <span class="isi" contenteditable="true">{{ $noPerencanaan }}</span>
  </h3>
  @unless($wajibDokumen)
    <p style="font-size:.82rem"><b>Catatan:</b> belanja nilai Rp{{ number_format($totalHarga, 0, ',', '.') }} paling banyak Rp1.000.000 sehingga <b>dikecualikan</b> dari kewajiban dokumen perencanaan (Permendikdasmen No 15 Tahun 2026 Pasal 12 ayat 4). Halaman ini tetap dicetak sebagailampiran bila diperlukan.</p>
  @endunless
  <table class="blockinfo blockinfo-bordered" style="width:100%;font-size:.8rem">
    <tr><td style="width:38%">Nama Barang/Jasa</td><td>{{ $paket }}</td></tr>
    <tr><td>Jumlah Barang/Jasa</td><td><span class="isi" contenteditable="true">{{ $rincian->count() }}</span></td></tr>
    <tr><td>Spesifikasi / Ruang Lingkup</td><td><span class="isi" contenteditable="true"></span></td></tr>
    <tr><td>Waktu &amp; Lokasi Serah Terima</td><td><span class="isi" contenteditable="true"></span></td></tr>
    <tr><td>Alokasi Anggaran (Rekening)</td><td>
      @foreach($rincian as $d)
        {{ $d['rekening'] ? $d['rekening'].' — ' : '' }}{{ $d['uraian'] }} @if(! $loop->last)<br>@endif
      @endforeach
    </td></tr>
    <tr><td>Persyaratan Penyedia</td><td><span class="isi" contenteditable="true"></span></td></tr>
    <tr><td>Disusun oleh (Pelaksana)</td><td><span class="isi" contenteditable="true"></span></td></tr>
    <tr><td>Disetujui oleh (Kepala Satuan Pendidikan)</td><td><span class="isi" contenteditable="true"></span></td></tr>
  </table>

  <div class="ttd3 mt-8">
    <div>
      {{ $tempat }}, <span class="isi" contenteditable="true">{{ $tglId }}</span><br>
      PELAKSANA,<br><br><br><br>
      <b><span class="isi" contenteditable="true"></span></b>
    </div>
    <div>
      {{ $tempat }}, <span class="isi" contenteditable="true">{{ $tglId }}</span><br>
      KEPALA SATUAN PENDIDIKAN,<br><br><br><br>
      <b>{{ $kepsekNama }}</b><br>
      {{ $kepsekNip }}
    </div>
    <div>
      {{ $tempat }}, <span class="isi" contenteditable="true">{{ $tglId }}</span><br>
      KETUA KOMITE,<br><br><br><br>
      <b><span class="isi" contenteditable="true">{{ $komiteNama }}</span></b>
    </div>
  </div>
</div>

{{-- ================= 2) BUKTI PERBANDINGAN HARGA ================= --}}
<div class="pg">
  <h3 class="judul">
    BUKTI PERBANDINGAN HARGA DAN KUALITAS<br>
    Nomor: <span class="isi" contenteditable="true">{{ $noBanding }}</span>
  </h3>
  @unless($wajibDokumen)
    <p style="font-size:.82rem"><b>Catatan:</b> belanja paling banyak Rp1.000.000 sehingga bebas dokumen perencanaan; halaman ini tetap dicetak sebagai lampiran bukti.</p>
  @endunless
  <p style="font-size:.82rem">Perbandingan harga dan kualitas dilakukan terhadap <b>2 (dua) Penyedia atau lebih</b> (Pasal 15 ayat 1); dapat dikecualikan bila barang/jasa hanya tersedia pada 1 Penyedia (Pasal 15 ayat 2).</p>
  <table class="tbl" style="font-size:.78rem">
    <thead><tr><th style="width:26%">Uraian</th><th class="num">Penyedia 1</th><th class="num">Penyedia 2</th><th class="num">Penyedia 3</th></tr></thead>
    <tbody>
      <tr>
        <td>Nama Penyedia</td>
        <td><span class="isi" contenteditable="true"></span></td>
        <td><span class="isi" contenteditable="true"></span></td>
        <td><span class="isi" contenteditable="true"></span></td>
      </tr>
      <tr>
        <td>Harga Penawaran</td>
        <td><span class="isi" contenteditable="true">Rp 0</span></td>
        <td><span class="isi" contenteditable="true">Rp 0</span></td>
        <td><span class="isi" contenteditable="true">Rp 0</span></td>
      </tr>
      <tr>
        <td>Kualitas / Spesifikasi</td>
        <td><span class="isi" contenteditable="true"></span></td>
        <td><span class="isi" contenteditable="true"></span></td>
        <td><span class="isi" contenteditable="true"></span></td>
      </tr>
    </tbody>
  </table>
  <p style="font-size:.82rem;margin-top:.6rem"><b>Kesimpulan:</b> barang/jasa yang ditetapkan adalah <span class="isi" contenteditable="true"></span> dengan nilai <span class="isi" contenteditable="true">Rp 0</span> — sesuai hasil perbandingan dan/atau hasil negosiasi (Pasal 17).</p>
  <p style="font-size:.78rem">Dasar: Permendikdasmen No 15 Tahun 2026 Pasal 15–17. Penyedia wajib memiliki NPWP, identitas Penyedia, dan kemampuan menyediakan barang/jasa (Pasal 9).</p>

  <div class="ttd3 mt-8">
    <div>
      {{ $tempat }}, <span class="isi" contenteditable="true">{{ $tglId }}</span><br>
      PELAKSANA,<br><br><br><br>
      <b><span class="isi" contenteditable="true"></span></b>
    </div>
    <div>
      {{ $tempat }}, <span class="isi" contenteditable="true">{{ $tglId }}</span><br>
      KEPALA SATUAN PENDIDIKAN,<br><br><br><br>
      <b>{{ $kepsekNama }}</b><br>
      {{ $kepsekNip }}
    </div>
    <div>
      {{ $tempat }}, <span class="isi" contenteditable="true">{{ $tglId }}</span><br>
      KETUA KOMITE,<br><br><br><br>
      <b><span class="isi" contenteditable="true">{{ $komiteNama }}</span></b>
    </div>
  </div>
</div>

{{-- ================= 1) SURAT PESANAN (SP) ================= --}}
<div>
  <h3 class="judul">
    <span class="isi" contenteditable="true">{{ $noSp }}</span><br>
    SURAT PESANAN (SP)
  </h3>
  <div style="display:flex;justify-content:space-between;gap:2rem;font-size:.82rem">
    <table class="blockinfo">
      <tr><td>Nama Satuan Pendidikan:</td></tr>
      <tr><td><b>{{ $sekolah->nama }}</b></td></tr>
      <tr><td>Alamat Satdik: {{ $alamatSetdik }}</td></tr>
    </table>
    <table class="blockinfo">
      <tr><td>Nomor SP: <span class="isi" contenteditable="true">{{ $noSp }}</span></td></tr>
      <tr><td>Tanggal SP: {{ $tglId }}</td></tr>
      <tr><td>Sumber Dana: {{ $belanja->bosType?->nama }}</td></tr>
      <tr><td>Tanggal Negosiasi: {{ $tglId }}</td></tr>
      <tr><td>Waktu Pengerjaan Pesanan: <span class="isi" contenteditable="true"></span></td></tr>
      <tr><td>Waktu Penyelesaian Pesanan: <span class="isi" contenteditable="true"></span></td></tr>
    </table>
  </div>

  <p style="font-size:.82rem">Paket Pesanan: <b>{{ $paket }}</b></p>

  <table class="blockinfo" style="font-size:.82rem">
    <tr><td>Penyedia:</td></tr>
    <tr><td>Nama Penyedia: <span class="isi" contenteditable="true">{{ $penyediaNama }}</span></td></tr>
    <tr><td>Alamat Penyedia: <span class="isi" contenteditable="true">{{ $penyediaAlamat }}</span></td></tr>
    <tr><td>NPWP Penyedia: <span class="isi" contenteditable="true">{{ $penyediaNpwp }}</span></td></tr>
    <tr><td>Pembeli:</td></tr>
    <tr><td>Nama Perwakilan Satdik: <span class="isi" contenteditable="true">{{ $kepsekNama }}</span></td></tr>
    <tr><td>Alamat Satdik: {{ $alamatSetdik }}</td></tr>
  </table>

  <p style="font-size:.82rem">Rincian Pekerjaan:</p>
  <table class="tbl">
    <thead><tr><th>No.</th><th>Uraian Barang/Jasa</th><th class="num">Qty</th><th>Satuan</th><th class="num">Harga Satuan</th><th class="num">Total Harga</th></tr></thead>
    <tbody>
      @forelse ($rincian as $i => $d)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $d['uraian'] }} @if ($d['rekening'])<span class="text-slate-400">({{ $d['rekening'] }})</span>@endif</td>
        <td class="num"><span class="isi" contenteditable="true">1</span></td>
        <td><span class="isi" contenteditable="true"></span></td>
        <td class="num">{{ rp($d['nilai']) }}</td>
        <td class="num">{{ rp($d['nilai']) }}</td>
      </tr>
      @empty
      <tr><td colspan="6">—</td></tr>
      @endforelse
    </tbody>
    <tfoot>
      <tr><td colspan="5" class="num"><b>Total Harga</b></td><td class="num"><b>{{ rp($totalHarga) }}</b></td></tr>
      <tr><td colspan="5" class="num">Biaya Pengiriman</td><td class="num"><span class="isi" contenteditable="true">Rp 0</span></td></tr>
      <tr><td colspan="5" class="num">PPN (dipungut)</td><td class="num">{{ rp($pajak) }}<div class="text-[10px] text-slate-400">tarif 12% sejak 2026 (11% sebelum 2026)</div></td></tr>
      <tr><td colspan="5" class="num"><b>Total Pembayaran</b></td><td class="num"><b>{{ rp($totalPembayaran) }}</b></td></tr>
      <tr><td colspan="6"><i>Terbilang: {{ ucfirst(terbilang((int) $totalPembayaran)).' rupiah' }}</i></td></tr>
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
      {{ $tempat }}, <span class="isi" contenteditable="true">{{ $tglId }}</span><br>
      Ketua Komite Pelaksana<br>
      Satuan Pendidikan<br><br><br><br>
      <b><span class="isi" contenteditable="true">{{ $komiteNama }}</span></b>
    </div>
    <div>
      <br>Pesanan diterima oleh Pelaksana,<br><br><br><br>
      <b>{{ $kepsekNama }}</b><br>
      {{ $kepsekNip }}
    </div>
  </div>
</div>

{{-- ================= 2) DOKUMEN BERITA ACARA NEGOSIASI ================= --}}
<div class="pg">
  <h3 class="judul">
    DOKUMEN BERITA ACARA NEGOSIASI<br>
    Nomor: <span class="isi" contenteditable="true">{{ $noNego }}</span>
  </h3>
  <table class="blockinfo" style="width:100%;font-size:.82rem">
    <tr>
      <td style="width:50%">Pelaksana<br><b>{{ $sekolah->nama }}</b><br>{{ $alamatSetdik }}</td>
      <td>Penyedia<br><b><span class="isi" contenteditable="true">{{ $penyediaNama }}</span></b><br><span class="isi" contenteditable="true">{{ $penyediaAlamat }}</span></td>
    </tr>
  </table>
  <p style="font-size:.82rem">Tanggal Terimanya Barang dan Jasa: <span class="isi" contenteditable="true">—</span></p>
  <p style="font-size:.82rem"><b>Hasil Negosiasi:</b></p>
  <table class="tbl">
    <thead><tr><th>No.</th><th>Nama Barang</th><th class="num">Qty</th><th class="num">Harga Penawaran</th><th class="num">Hasil Negosiasi</th><th>Keterangan</th></tr></thead>
    <tbody>
      @forelse ($rincian as $i => $d)
      <tr>
        <td>{{ $i + 1 }}</td><td>{{ $d['uraian'] }}</td>
        <td class="num"><span class="isi" contenteditable="true">1</span></td>
        <td class="num">{{ rp($d['nilai']) }}</td>
        <td class="num">{{ rp($d['nilai']) }}</td>
        <td><span class="isi" contenteditable="true"></span></td>
      </tr>
      @empty
      <tr><td colspan="6">—</td></tr>
      @endforelse
    </tbody>
    <tfoot>
      <tr><td colspan="4" class="num"><b>Total</b></td><td class="num"><b>{{ rp($totalHarga) }}</b></td><td></td></tr>
      <tr><td colspan="4" class="num">Biaya Pengiriman</td><td class="num"><span class="isi" contenteditable="true">Rp 0</span></td><td></td></tr>
    </tfoot>
  </table>

  <div class="ttd-grid mt-8">
    <div>
      Calon Penyedia<br><br><br><br>
      <b><span class="isi" contenteditable="true"></span></b>
    </div>
    <div>
      {{ $tempat }}, <span class="isi" contenteditable="true">{{ $tglId }}</span><br>
      Pelaksana<br><br><br><br>
      <b>{{ $kepsekNama }}</b><br>
      {{ $kepsekNip }}
    </div>
  </div>
</div>

{{-- ================= 3) SURAT HASIL PEMERIKSAAN ================= --}}
<div class="pg">
  <h3 class="judul">
    SURAT HASIL PEMERIKSAAN<br>
    Nomor: <span class="isi" contenteditable="true">{{ $noHp }}</span>
  </h3>
  <p style="font-size:.82rem">Pada hari ini, {{ $hariId }} tanggal <span class="isi" contenteditable="true">{{ $tglId }}</span>, pihak pemeriksa telah memeriksa hasil pekerjaan dengan surat pesanan:</p>
  <table class="blockinfo blockinfo-bordered" style="font-size:.82rem">
    <tr><td>Nomor Surat Pesanan</td><td class="num">{{ $noSp }}</td></tr>
    <tr><td>Tanggal</td><td class="num">{{ $tgl->translatedFormat('d/m/Y') }}</td></tr>
    <tr><td>Nama Pekerjaan</td><td class="num">{{ $paket }}</td></tr>
    <tr><td>Tahun</td><td class="num">{{ $belanja->tahun }}</td></tr>
  </table>
  <p style="font-size:.82rem">Yang bertandatangan di bawah ini:</p>
  <table class="blockinfo" style="font-size:.82rem">
    <tr><td>Nama</td><td class="num">{{ $kepsekNama }}</td></tr>
    <tr><td>Jabatan</td><td class="num">Kepala Sekolah</td></tr>
    <tr><td>Nama Satuan Pendidikan</td><td class="num">{{ $sekolah->nama }}</td></tr>
    <tr><td>Alamat Satuan Pendidikan</td><td class="num">{{ $alamatSetdik }}</td></tr>
  </table>
  <p style="font-size:.82rem">Telah melakukan pemeriksaan terhadap hasil pekerjaan sesuai dengan spesifikasi Surat Pesanan. Hasil pemeriksaan sebagai berikut:</p>
  <table class="tbl">
    <thead><tr><th>No.</th><th>Uraian Barang/Jasa</th><th class="num">Qty</th><th>Spesifikasi</th><th>Kondisi</th></tr></thead>
    <tbody>
      @forelse ($rincian as $i => $d)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $d['uraian'] }}</td>
        <td class="num"><span class="isi" contenteditable="true">1</span></td>
        <td><span class="isi" contenteditable="true"></span></td>
        <td><span class="isi" contenteditable="true">Baik</span></td>
      </tr>
      @empty
      <tr><td colspan="5">—</td></tr>
      @endforelse
    </tbody>
  </table>
  <p style="font-size:.82rem">Demikian Surat Pemeriksaan Hasil Pekerjaan ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.</p>

  <div class="ttd-grid mt-8">
    <div>
      PIHAK PERTAMA,<br><br><br><br>
      <b><span class="isi" contenteditable="true"></span></b>
    </div>
    <div>
      {{ $tempat }}, <span class="isi" contenteditable="true">{{ $tglId }}</span><br>
      PIHAK KEDUA,<br><br><br><br>
      <b>{{ $kepsekNama }}</b><br>
      {{ $kepsekNip }}
    </div>
  </div>
</div>

{{-- ================= 4) BERITA ACARA SERAH TERIMA ================= --}}
<div class="pg">
  <h3 class="judul">
    BERITA ACARA SERAH TERIMA<br>
    Nomor: <span class="isi" contenteditable="true">{{ $noBast }}</span>
  </h3>
  <p style="font-size:.82rem">Pada hari ini, {{ $hariId }} tanggal <span class="isi" contenteditable="true">{{ $tglId }}</span>, kami yang tercantum di bawah ini:</p>
  <table class="blockinfo" style="width:100%;font-size:.82rem">
    <tr>
      <td style="width:50%">
        Nama: <span class="isi" contenteditable="true"></span><br>
        Nama Penyedia: <b><span class="isi" contenteditable="true">{{ $penyediaNama }}</span></b><br>
        Alamat Penyedia: <span class="isi" contenteditable="true">{{ $penyediaAlamat }}</span><br>
        Sebagai pihak yang menyerahkan, selanjutnya disebut <b>PIHAK PERTAMA</b>
      </td>
      <td>
        Nama: {{ $kepsekNama }}<br>
        Jabatan: Kepala Sekolah<br>
        Nama Satuan Pendidikan: {{ $sekolah->nama }}<br>
        Alamat Satuan Pendidikan: {{ $alamatSetdik }}<br>
        Sebagai pihak yang menerima, selanjutnya disebut <b>PIHAK KEDUA</b>
      </td>
    </tr>
  </table>
  <table class="blockinfo blockinfo-bordered" style="font-size:.82rem">
    <tr><td>Nomor Surat Pesanan</td><td class="num">{{ $noSp }}</td></tr>
    <tr><td>Tanggal Pesanan</td><td class="num">{{ $tgl->translatedFormat('d/m/Y') }}</td></tr>
    <tr><td>Nama Pekerjaan</td><td class="num">{{ $paket }}</td></tr>
    <tr><td>Tanggal Diterima</td><td class="num"><span class="isi" contenteditable="true">—</span></td></tr>
  </table>
  <p style="font-size:.82rem">PIHAK PERTAMA menyerahkan hasil pekerjaan Pengadaan Barang/Jasa melalui Berita Acara Serah Terima ini kepada PIHAK KEDUA.</p>
  <p style="font-size:.82rem">Berita Acara Serah Terima ini berfungsi sebagai bukti serah terima hasil pekerjaan yang telah diperiksa dan dinyatakan memenuhi persyaratan.</p>

  <div class="ttd3">
    <div>
      {{ $tempat }}, <span class="isi" contenteditable="true">{{ $tglId }}</span><br>
      PIHAK PERTAMA,<br><br><br><br>
      <b><span class="isi" contenteditable="true"></span></b>
    </div>
    <div>
      PIHAK KEDUA,<br><br><br><br>
      <b>{{ $kepsekNama }}</b><br>
      {{ $kepsekNip }}
    </div>
    <div>
      PEMERIKSA BARANG,<br><br><br><br>
      <b>{{ $kepsekNama }}</b><br>
      {{ $kepsekNip }}
    </div>
  </div>

  <p style="font-size:.82rem;margin-top:1rem"><b>Lampiran Rincian Pekerjaan:</b></p>
  <table class="tbl">
    <thead><tr><th>No.</th><th>Uraian Barang/Jasa</th><th class="num">Jumlah Dipesan</th><th class="num">Diterima Kondisi Baik</th><th class="num">Diterima Kondisi Rusak</th></tr></thead>
    <tbody>
      @forelse ($rincian as $i => $d)
      <tr>
        <td>{{ $i + 1 }}</td><td>{{ $d['uraian'] }}</td>
        <td class="num"><span class="isi" contenteditable="true">1</span></td>
        <td class="num"><span class="isi" contenteditable="true">1</span></td>
        <td class="num"><span class="isi" contenteditable="true">0</span></td>
      </tr>
      @empty
      <tr><td colspan="5">—</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

{{-- ================= 5) DOKUMEN PENGADAAN (REKAP) ================= --}}
<div class="pg">
  <h3 class="judul">DOKUMEN PENGADAAN BARANG DAN JASA</h3>
  <table class="tbl" style="font-size:.8rem">
    <tbody>
      <tr><th colspan="2" style="background:#e2e8f0">Nama Penyedia</th></tr>
      <tr><td>Penyedia</td><td><span class="isi" contenteditable="true">{{ $penyediaNama }}</span></td></tr>
      <tr><td>Alamat Penyedia</td><td><span class="isi" contenteditable="true">{{ $penyediaAlamat }}</span></td></tr>
      <tr><td>Nomor Telp.</td><td><span class="isi" contenteditable="true">{{ $penyediaTelp }}</span></td></tr>
      <tr><td>NPWP</td><td><span class="isi" contenteditable="true">{{ $penyediaNpwp }}</span></td></tr>
      <tr><td>Nama Perwakilan Penyedia</td><td><span class="isi" contenteditable="true"></span></td></tr>
      <tr><th colspan="2" style="background:#e2e8f0">Dokumen Perencanaan (Persiapan Belanja)</th></tr>
    <tr><td>Nomor</td><td>{{ $noPerencanaan }} — {{ $wajibDokumen ? 'WAJIB dilampirkan' : 'dikecualikan (nilai ≤ Rp1.000.000)' }}</td></tr>
    <tr><th colspan="2" style="background:#e2e8f0">Bukti Perbandingan Harga</th></tr>
    <tr><td>Nomor</td><td>{{ $noBanding }} — {{ $wajibDokumen ? 'WAJIB dilampirkan' : 'dikecualikan (nilai ≤ Rp1.000.000)' }}</td></tr>
    <tr><th colspan="2" style="background:#e2e8f0">Berita Acara Negosiasi</th></tr>
      <tr><td>Nomor</td><td>{{ $noNego }}</td></tr>
      <tr><td>Tanggal Negosiasi</td><td>{{ $tglId }}</td></tr>
      <tr><td>Tanggal Terima</td><td><span class="isi" contenteditable="true">—</span></td></tr>
      <tr><td>Biaya Pengiriman</td><td>Rp 0</td></tr>
      <tr><th colspan="2" style="background:#e2e8f0">Surat Pesanan</th></tr>
      <tr><td>Paket Pesanan</td><td>{{ $paket }}</td></tr>
      <tr><td>Nomor</td><td>{{ $noSp }}</td></tr>
      <tr><td>Tanggal</td><td>{{ $tgl->translatedFormat('d/m/Y') }}</td></tr>
      <tr><td>Waktu Pelaksanaan</td><td><span class="isi" contenteditable="true">—</span></td></tr>
      <tr><td>Waktu Penyelesaian</td><td><span class="isi" contenteditable="true">—</span></td></tr>
      <tr><th colspan="2" style="background:#e2e8f0">Surat Hasil Pemeriksaan</th></tr>
      <tr><td>Nomor Pemeriksaan</td><td>{{ $noHp }}</td></tr>
      <tr><td>Tanggal</td><td><span class="isi" contenteditable="true">—</span></td></tr>
      <tr><td>Nomor BAST</td><td>{{ $noBast }}</td></tr>
      <tr><th colspan="2" style="background:#e2e8f0">Rincian</th></tr>
      <tr><td>Jumlah Barang</td><td>{{ $rincian->count() }}</td></tr>
      <tr><td>Kuantitas</td><td>{{ $rincian->count() }}</td></tr>
      <tr><td>Total Belanja (Hasil Negosiasi)</td><td><b>{{ rp($totalHarga) }}</b></td></tr>
    </tbody>
  </table>
  <p class="text-xs mt-4 text-slate-400">BKU #{{ $belanja->no_bku }} · {{ $tgl->translatedFormat('d/m/Y') }} · {{ $belanja->stage?->nama }} · Satuan Pendidikan: {{ $sekolah->nama }}</p>
</div>

<script nonce="{{ request()->attributes->get('csp_nonce') }}">
  document.querySelectorAll('.isi').forEach(function (el) {
    el.onkeydown = function (e) {
      if (e.key === 'Enter') { e.preventDefault(); this.blur(); }
    };
  });
</script>
@endsection
