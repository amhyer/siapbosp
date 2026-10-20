<?php

use App\Models\Komponen;
use App\Models\Rekening;
use Illuminate\Database\Migrations\Migration;

/**
 * Master rekening ARKAS 2025 (44 kode belanja aktif dari ARKAS-Lengkap-2025.xlsx).
 *
 * Sumber nama: sheet Ref_Rekening file tersebut. Dipetakan ke 12 komponen BOSP.
 * - 42 kode BARU di-insert; 12 kode lama (v9) dibiarkan apa adanya untuk
 *   kompatibilitas data lama.
 * - 2 kode TABRAKAN di-update ke standar ARKAS (nama + komponen):
 *   5.1.02.01.01.0001 (Honor Pengelola → Bahan Bangunan dan Konstruksi),
 *   5.1.02.04.01.0004 (Bahan Pemeliharaan → Perjalanan Dinas Paket Meeting).
 * - Down: menghapus 42 kode baru; 2 kode tabrakan dibiarkan (sudah terlanjur
 *   dipakai transaksi impor).
 */
return new class extends Migration
{
    /** [kode, nama ARKAS, kode_komponen] */
    public const MASTER = [
        ['5.1.02.01.01.0001', 'Belanja Bahan-Bahan Bangunan dan Konstruksi', '08'],
        ['5.1.02.01.01.0005', 'Belanja Bahan-Bahan Baku Praktek KBM', '03'],
        ['5.1.02.01.01.0008', 'Belanja Bahan-Bahan/Bibit Tanaman', '03'],
        ['5.1.02.01.01.0012', 'Belanja Bahan-Bahan Lainnya', '05'],
        ['5.1.02.01.01.0024', 'Belanja Alat/Bahan untuk Kegiatan Kantor-Alat Tulis Kantor', '05'],
        ['5.1.02.01.01.0026', 'Belanja Alat/Bahan untuk Kegiatan Kantor- Bahan Cetak dan Penggandaan', '03'],
        ['5.1.02.01.01.0029', 'Belanja Alat/Bahan untuk Kegiatan Kantor-Bahan Komputer', '05'],
        ['5.1.02.01.01.0030', 'Belanja Alat/Bahan untuk Kegiatan Kantor-Perabot Kantor', '05'],
        ['5.1.02.01.01.0036', 'Belanja Alat/Bahan untuk Kegiatan Kantor-Alat/Bahan untuk Kegiatan Kantor Lainnya', '05'],
        ['5.1.02.01.01.0037', 'Belanja Obat-Obat-Obatan', '05'],
        ['5.1.02.01.01.0052', 'Belanja Makanan dan Minuman Rapat', '05'],
        ['5.1.02.01.01.0053', 'Belanja Makanan dan Minuman Jamuan Tamu', '05'],
        ['5.1.02.02.01.0003', 'Honorarium Narasumber atau Pembahas, Moderator, Pembawa Acara, dan Panitia', '06'],
        ['5.1.02.02.01.0013', 'Belanja Jasa Tenaga Pendidikan / Honorarium Guru', '12'],
        ['5.1.02.02.01.0016', 'Belanja Jasa Tenaga Penanganan Prasarana dan Sarana Umum / Upah Tukang', '08'],
        ['5.1.02.02.01.0025', 'Belanja Jasa Tenaga Kesenian dan Kebudayaan', '03'],
        ['5.1.02.02.01.0026', 'Belanja Jasa Tenaga Administrasi', '05'],
        ['5.1.02.02.01.0027', 'Belanja Jasa Tenaga Operator Komputer', '05'],
        ['5.1.02.02.01.0029', 'Belanja Jasa Tenaga Ahli', '06'],
        ['5.1.02.02.01.0030', 'Belanja Jasa Tenaga Kebersihan', '07'],
        ['5.1.02.02.01.0031', 'Belanja Jasa Tenaga Keamanan', '07'],
        ['5.1.02.02.01.0035', 'Belanja Jasa Tenaga Teknisi Mekanik dan Listrik', '08'],
        ['5.1.02.02.01.0037', 'Belanja Jasa Juri Perlombaan/Pertandingan', '03'],
        ['5.1.02.02.01.0051', 'Belanja Jasa Pengolahan Sampah', '07'],
        ['5.1.02.02.01.0060', 'Belanja Tagihan Air', '07'],
        ['5.1.02.02.01.0061', 'Belanja Tagihan Listrik', '07'],
        ['5.1.02.02.01.0062', 'Belanja Langganan Jurnal/Surat Kabar/Majalah', '02'],
        ['5.1.02.02.01.0063', 'Belanja Kawat/Faksimili/Internet/TV Berlangganan', '07'],
        ['5.1.02.02.01.0066', 'Belanja Registrasi/Keanggotaan', '06'],
        ['5.1.02.02.04.0117', 'Belanja Sewa Alat Kantor Lainnya', '05'],
        ['5.1.02.03.02.0121', 'Belanja Pemeliharaan Alat Kantor dan Rumah Tangga-Alat Rumah Tangga-Alat Pendingin', '08'],
        ['5.1.02.03.02.0405', 'Belanja Pemeliharaan Komputer-Komputer Unit-Personal Computer', '08'],
        ['5.1.02.03.02.0411', 'Belanja Pemeliharaan Komputer-Peralatan Komputer-Peralatan Komputer Lainnya', '08'],
        ['5.1.02.03.03.0010', 'Belanja Pemeliharaan Bangunan Gedung-Bangunan Gedung Tempat Kerja-Bangunan Gedung Tempat Pendidikan', '08'],
        ['5.1.02.03.03.0030', 'Belanja Pemeliharaan Bangunan Gedung-Bangunan Gedung Tempat Kerja-Bangunan Gedung Tempat Kerja Lainnya', '08'],
        ['5.1.02.03.05.0001', 'Belanja Pemeliharaan Bahan Perpustakaan-Bahan Perpustakaan Tercetak-Buku Umum', '02'],
        ['5.1.02.04.01.0003', 'Belanja Perjalanan Dinas Dalam Kota / Dalam Daerah', '05'],
        ['5.1.02.04.01.0004', 'Belanja Perjalanan Dinas Paket Meeting Dalam Kota', '05'],
        ['5.2.02.05.01.0005', 'Belanja Modal Alat Kantor Lainnya', '08'],
        ['5.2.02.05.02.0004', 'Belanja Modal Alat Pendingin', '08'],
        ['5.2.02.05.02.0006', 'Belanja Modal Alat Rumah Tangga Lainnya (Home Use)', '08'],
        ['5.2.02.06.01.0001', 'Belanja Modal Peralatan Studio Audio', '09'],
        ['5.2.02.06.01.0002', 'Belanja Modal Peralatan Studio Video dan Film', '09'],
        ['5.2.02.10.01.0001', 'Belanja Modal Komputer Jaringan', '09'],
    ];

    public function up(): void
    {
        $komponenId = Komponen::pluck('id', 'kode')->all();
        foreach (self::MASTER as [$kode, $nama, $kom]) {
            Rekening::updateOrCreate(
                ['kode' => $kode],
                ['nama' => $nama, 'tipe' => 'belanja', 'komponen_id' => $komponenId[$kom] ?? null]
            );
        }
    }

    public function down(): void
    {
        // Hapus 42 kode baru; 2 kode tabrakan (0001 & 0004) disengaja dibiarkan.
        $lama = [
            '5.1.01.02.01.0001', '5.1.02.01.01.0001', '5.1.02.04.01.0001', '5.1.02.04.01.0004',
            '5.1.02.04.01.0006', '5.1.02.01.02.0002', '5.1.02.05.01.0001', '5.1.02.05.03.0002',
            '5.1.02.05.03.0004', '5.1.06.09.03.0001', '5.2.01.05.01.0002', '5.1.02.04.01.0021',
            '4.3.1.01.01.0001', '4.3.1.01.01.0002',
        ];
        $baru = array_diff(array_column(self::MASTER, 0), $lama);
        Rekening::whereIn('kode', $baru)->delete();
    }
};
