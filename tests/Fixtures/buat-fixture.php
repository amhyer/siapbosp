<?php

// Generator fixture tests/Fixtures/arkas-mini.xlsx — dijalankan sekali via:
//   php tests/Fixtures/buat-fixture.php
// Isi: 1 sekolah (NPSN 90000001), tahun 2025, mencakup kasus uji
// idempoten, isolasi, tutup buku, NPSN asing, rekening tak dikenal, pajak.

require __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function sheet(Spreadsheet $wb, string $nama, array $header, array $rows): void
{
    $s = $wb->createSheet();
    $s->setTitle($nama);
    $s->fromArray(['NAV'], null, 'A1'); // baris navigasi (dilewati pembaca)
    $s->fromArray($header, null, 'A2');
    $s->fromArray($rows, null, 'A3');
}

$wb = new Spreadsheet();
$wb->removeSheetByIndex(0);

sheet($wb, 'Sekolah',
    ['sekolah_id', 'nama', 'npsn', 'alamat'],
    [['SCH-A', 'SD Negeri A', '90000001', 'Jl. Merdeka 1']]);

sheet($wb, 'Anggaran',
    ['id_anggaran', 'id_ref_sumber_dana', 'jumlah', 'is_approve', 'is_aktif', 'tahun_anggaran', 'soft_delete', 'last_update'],
    [
        ['AGG-LAMA', 1, 90000000, 1, 1, 2025, 0, '2025-01-01'],
        ['AGG-BARU', 1, 100000000, 1, 1, 2025, 0, '2025-06-01'],
    ]);

sheet($wb, 'Penjab',
    ['id_penjab', 'ks', 'nip_ks', 'bendahara', 'nip_bendahara', 'komite', 'tahun', 'tanggal_mulai', 'soft_delete'],
    [['PJ-1', 'Budi Santoso', '111', 'Siti Aminah', '222', '', 2025, '2025-01-01', 0]]);

sheet($wb, 'Salur',
    ['tahap', 'jenis', 'nilai_salur_bersih', 'tidak_terima_dana', 'tahun'],
    [['1', 'dana_salur', 100000000, 0, 2025]]);

sheet($wb, 'Ref_Rekening',
    ['kode_rekening', 'rekening'],
    [['9.9.99.99.99.9999', 'Kode Asing Contoh'], ['5.1.02.01.01.0001', 'Honor Pengelola']]);

sheet($wb, 'Aktivasi_BKU',
    ['tanggal_aktivasi', 'saldo_awal_bank', 'saldo_awal_tunai', 'soft_delete'],
    [['2025-01-01', 500000, 250000, 0]]);

sheet($wb, 'PTK',
    ['ptk_id', 'nama', 'soft_delete'],
    [['PTK-1', 'Guru A', 0], ['PTK-2', 'Guru B', 0]]);

sheet($wb, 'RAPBS',
    ['id_rapbs', 'id_ref_kode', 'kode_rekening', 'uraian', 'volume', 'satuan', 'harga_satuan', 'jumlah', 'soft_delete', 'tahun_anggaran', 'sumber_dana'],
    [
        ['RAPBS-1', 'RK-1', '5.1.02.01.01.0001', 'Honor Pengelola', 2, 'paket', 500000, 1000000, 0, 2025, 'BOS Reguler'],
        ['RAPBS-2', '', '9.9.99.99.99.9999', 'Kode Asing', 1, 'paket', 100000, 100000, 0, 2025, 'BOS Reguler'],
        ['RAPBS-3', '', '5.1.02.01.01.0001', 'Dihapus ARKAS', 1, 'paket', 50000, 50000, 1, 2025, 'BOS Reguler'],
    ]);

sheet($wb, 'Ref_Kode',
    ['id_ref_kode', 'id_kode', 'uraian_kode', 'id_level_kode'],
    [['RK-P', '03.', 'Pengembangan Standar Proses', 1], ['RK-1', '03.03.06.', 'Kepramukaan', 2]]);

sheet($wb, 'RAPBS_Periode',
    ['id_rapbs_periode', 'id_rapbs', 'volume'],
    [['PER-1', 'RAPBS-1', 2]]);

sheet($wb, 'KAS_Umum',
    ['tanggal_transaksi', 'no_bukti', 'uraian', 'kode_rekening', 'jenis_bku', 'kode_bku', 'saldo', 'status_bku', 'volume', 'is_ppn', 'is_pph_21', 'is_pph_22', 'is_pph_23', 'is_pph_4', 'is_sspd', 'is_lock', 'tahun_anggaran', 'sumber_dana', 'id_anggaran', 'soft_delete', 'id_kas_umum', 'id_rapbs_periode', 'id_kas_nota'],
    [
        ['2025-01-22', 'BBU01', 'Terima Dana BOSP Tahap 1 2025', '', 'Terima Dana BOS', 'BBU', 100000000, '', '', 0, 0, 0, 0, 0, 0, 0, 2025, 'BOS Reguler', 'AGG-1', 0, 'KAS-BARU-1', '', ''],
        ['2025-02-10', 'BPU01', 'Belanja ATK', '5.1.02.01.01.0001', 'Kas Keluar', 'BPU', 5000000, '', 5, 0, 0, 0, 0, 0, 0, 0, 2025, 'BOS Reguler', 'AGG-1', 0, '', 'PER-1', 'NOTA-1'],
        ['2025-03-15', 'BPU02', 'Honor Kebersihan', '9.9.99.99.99.9999', 'Kas Keluar', 'BPU', 2000000, '', '', 0, 0, 0, 0, 0, 0, 0, 2025, 'BOS Reguler', 'AGG-1', 0, '', '', ''],
        ['2025-02-10', '', 'Terima PPh 21', '5.1.02.01.01.0001', 'Pajak Belanja Terima', 'PBT', 100000, '', '', 0, 1, 0, 0, 0, 0, 0, 2025, 'BOS Reguler', 'AGG-1', 0, '', '', ''],
        ['2025-02-11', '', 'Setor PPh 21', '5.1.02.01.01.0001', 'Pajak Belanja Setor', 'PBS', 100000, '', '', 0, 1, 0, 0, 0, 0, 0, 2025, 'BOS Reguler', 'AGG-1', 0, '', '', ''],
        ['2025-04-01', '', 'Tarik Tunai', '', 'Tarik Tunai', 'BBU', 10000000, '', '', 0, 0, 0, 0, 0, 0, 0, 2025, 'BOS Reguler', 'AGG-1', 0, '', '', ''],
        ['2025-04-01', '', 'Pergeseran Uang di Bank', '', 'Pergeseran Tunai', 'PBS', 10000000, '', '', 0, 0, 0, 0, 0, 0, 0, 2025, 'BOS Reguler', 'AGG-1', 0, '', '', ''],
        ['2025-01-01', '', 'Saldo Bank Bulan Desember 2024', '', 'Saldo Awal Bank', 'SAB', 0, '', '', 0, 0, 0, 0, 0, 0, 0, 2025, 'BOS Reguler', 'AGG-1', 0, '', '', ''],
        ['2025-05-01', 'BPU09', 'Belanja Dihapus ARKAS', '5.1.02.01.01.0001', 'Kas Keluar', 'BPU', 999000, '', '', 0, 0, 0, 0, 0, 0, 0, 2025, 'BOS Reguler', 'AGG-1', 1, '', '', ''],
    ]);

sheet($wb, 'Nota',
    ['id_kas_nota', 'no_bukti', 'tanggal_nota', 'no_nota', 'nama_toko', 'alamat_toko', 'no_telp', 'npwp', 'total', 'kode_rekening', 'has_ppn', 'has_pph_22', 'soft_delete', 'is_beli_di_siplah'],
    [['NOTA-1', 'BPU01', '2025-02-10', 'N-2025-01', 'Toko ATK', 'Jl. Cendana No. 10', '0411123456', '123456789012345', 5000000, '5.1.02.01.01.0001', 0, 0, 0, 1]]);

sheet($wb, 'Pajak_NTPN',
    ['id_kas_nota', 'ntpn', 'soft_delete'],
    [['NOTA-1', '1234567890123456', 0]]);

$out = __DIR__ . '/arkas-mini.xlsx';
(new Xlsx($wb))->save($out);
echo "Fixture ditulis: $out\n";

// Varian NPSN asing untuk uji penolakan.
$wb2 = \PhpOffice\PhpSpreadsheet\IOFactory::load($out);
$sek = $wb2->getSheetByName('Sekolah');
$sek->setCellValue('C3', '00000000');
$out2 = __DIR__ . '/arkas-asing.xlsx';
(new Xlsx($wb2))->save($out2);
echo "Fixture ditulis: $out2\n";
