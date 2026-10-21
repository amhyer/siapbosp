<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\{User, School, BosType, Komponen, Rekening, RekeningPajak, Stage, Allocation,
                RkasHeader, KasTransaction, Penandatangan, Announcement};

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ===== AKUN DINAS =====
        User::create(['name' => 'Superadmin', 'email' => 'superadmin@siapbosp.id', 'password' => 'password', 'role' => 'superadmin', 'school_id' => null]);
        User::create(['name' => 'Admin Dinas Pendidikan', 'email' => 'dinas@siapbosp.id', 'password' => 'password', 'role' => 'dinas', 'school_id' => null]);

        // ===== SEKOLAH DEMO =====
        $s1 = School::create(['npsn' => '60100001', 'nama' => 'SD Negeri 012345 Mamajang', 'jenjang' => 'SD', 'desa' => 'Mamajang Dalam', 'kecamatan' => 'Mamajang', 'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan', 'alamat' => 'Jl. Andi Mangerangi No. 10']);
        $s2 = School::create(['npsn' => '60100002', 'nama' => 'SD Negeri 067890 Losari', 'jenjang' => 'SD', 'desa' => 'Losari', 'kecamatan' => 'Ujung Pandang', 'kab_kota' => 'Kota Makassar', 'provinsi' => 'Sulawesi Selatan', 'alamat' => 'Jl. Datu Museng No. 12']);

        $op1 = User::create(['name' => 'Operator Satu', 'email' => 'operator@sekolah.demo', 'password' => 'password', 'role' => 'operator', 'school_id' => $s1->id]);
        User::create(['name' => 'Kepala Sekolah Satu', 'email' => 'kepsek@sekolah.demo', 'password' => 'password', 'role' => 'kepsek', 'school_id' => $s1->id]);
        User::create(['name' => 'Operator Dua', 'email' => 'operator2@sekolah.demo', 'password' => 'password', 'role' => 'operator', 'school_id' => $s2->id]);
        User::create(['name' => 'Kepala Sekolah Dua', 'email' => 'kepsek2@sekolah.demo', 'password' => 'password', 'role' => 'kepsek', 'school_id' => $s2->id]);

        // ===== REFERENSI =====
        $tReg = BosType::create(['kode' => 'reguler', 'nama' => 'BOS Reguler']);
        BosType::create(['kode' => 'kinerja', 'nama' => 'BOS Kinerja']);
        $tDaerah = BosType::create(['kode' => 'bosda', 'nama' => 'BOSP Daerah']);
        BosType::create(['kode' => 'silpa', 'nama' => 'SILPA Tahun Sebelumnya']);

        $kom = [];
        foreach ([
            '01' => 'Pelaksanaan Penerimaan Peserta Didik Baru',
            '02' => 'Pengembangan Perpustakaan',
            '03' => 'Kegiatan Pembelajaran dan Ekstrakurikuler',
            '04' => 'Kegiatan Asesmen dan Evaluasi Pembelajaran',
            '05' => 'Kegiatan Administrasi Sekolah',
            '06' => 'Pengembangan Profesi Guru dan Tenaga Kependidikan',
            '07' => 'Pembiayaan Langganan Daya dan Jasa',
            '08' => 'Pemeliharaan Sarana dan Prasarana Sekolah',
            '09' => 'Penyediaan Alat Multimedia Pembelajaran',
            '10' => 'Penyelenggaraan Kegiatan Peningkatan Kompetensi Keahlian',
            '11' => 'Penyelenggaraan Kegiatan dalam Mendukung Keterserapan Lulusan',
            '12' => 'Pembayaran Honor',
        ] as $k => $n) $kom[$k] = Komponen::create(['kode' => $k, 'nama' => $n]);

        $rHibah1 = Rekening::create(['kode' => '4.3.1.01.01.0001', 'nama' => 'Pendapatan Hibah BOS — Pemerintah Pusat', 'tipe' => 'pendapatan']);
        $rHibah2 = Rekening::create(['kode' => '4.3.1.01.01.0002', 'nama' => 'Pendapatan Hibah BOSP — Pemerintah Daerah', 'tipe' => 'pendapatan']);
        $rek = [];
        foreach ([
            ['5.1.01.02.01.0001', 'Belanja Honorarium GTK bukan ASN', '12'],
            ['5.1.02.01.01.0001', 'Belanja Honorarium Pengelola Sekolah', '05'],
            ['5.1.02.04.01.0001', 'Belanja Alat Tulis Kantor (ATK)', '05'],
            ['5.1.02.04.01.0004', 'Belanja Bahan Pemeliharaan Sarana & Prasarana', '08'],
            ['5.1.02.04.01.0006', 'Belanja Bahan Cetak (Perbanyakan Bahan Ajar)', '03'],
            ['5.1.02.01.02.0002', 'Belanja Jasa Kantor (fotokopi/bendahara dsb.)', '05'],
            ['5.1.02.05.01.0001', 'Belanja Langganan Listrik', '07'],
            ['5.1.02.05.03.0002', 'Belanja Langganan Telepon dan Data Internet', '07'],
            ['5.1.02.05.03.0004', 'Belanja Jasa Kebersihan dan Keamanan Sekolah', '07'],
            ['5.1.06.09.03.0001', 'Belanja Pelatihan/Perjadin Pengembangan Guru', '06'],
            ['5.2.01.05.01.0002', 'Belanja Modal Alat Multimedia Pembelajaran', '09'],
            ['5.1.02.04.01.0021', 'Belanja Bahan Bacaan Buku Perpustakaan', '02'],
        ] as [$k, $n, $c]) $rek[$k] = Rekening::create(['kode' => $k, 'nama' => $n, 'tipe' => 'belanja', 'komponen_id' => $kom[$c]->id]);

        $pPpn = RekeningPajak::create(['kode' => 'ppn', 'nama' => 'PPN (Pajak Pertambahan Nilai)']);
        RekeningPajak::create(['kode' => 'pph21', 'nama' => 'PPh Pasal 21']);
        RekeningPajak::create(['kode' => 'pph22', 'nama' => 'PPh Pasal 22']);
        RekeningPajak::create(['kode' => 'pph23', 'nama' => 'PPh Pasal 23']);
        RekeningPajak::create(['kode' => 'pph42', 'nama' => 'PPh Pasal 4 Ayat 2']);

        $stages = [];
        foreach ([2025, 2026] as $y) {
            for ($i = 1; $i <= 5; $i++) $stages[$y][$i] = Stage::create(['tahun' => $y, 'nama' => "Tahap $i", 'urutan' => $i]);
        }

        // ===== PAGU 2026 =====
        Allocation::withoutGlobalScope('school')->insert([
            ['school_id' => $s1->id, 'tahun' => 2026, 'bos_type_id' => $tReg->id, 'stage_id' => null, 'nilai' => 150000000, 'created_at' => now(), 'updated_at' => now()],
            ['school_id' => $s2->id, 'tahun' => 2026, 'bos_type_id' => $tReg->id, 'stage_id' => null, 'nilai' => 88500000, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // ===== RKAS DEMO (sekolah 1, disetujui) =====
        $rkas = RkasHeader::withoutGlobalScopes()->create(['school_id' => $s1->id, 'tahun' => 2026, 'revisi_ke' => 1, 'no_rkas' => '420/012-RKAS/2026', 'status' => 'disetujui']);
        foreach ([
            ['5.1.01.02.01.0001', 'Honor GTK non ASN (penjaga & cleaning service), 12 bulan', 'OB', 12, 2500000, 'BOS Reguler'],
            ['5.1.02.04.01.0001', 'Pengadaan ATK triwulan I', 'paket', 1, 5000000, 'BOS Reguler'],
            ['5.1.02.05.01.0001', 'Langganan listrik tahun 2026', 'tahun', 1, 12000000, 'BOS Reguler'],
            ['5.1.02.04.01.0006', 'Penggandaan modul semester genap', 'bendel', 50, 65000, 'BOS Reguler'],
        ] as [$kode, $u, $sat, $bny, $harga, $sd]) {
            $rkas->details()->create(['rekening_id' => Rekening::where('kode', $kode)->first()->id, 'uraian' => $u, 'satuan' => $sat, 'banyak' => $bny, 'harga_satuan' => $harga, 'jumlah' => $bny * $harga, 'sumber_dana' => $sd]);
        }

        // ===== TRANSAKSI DEMO SEKOLAH 1 (2026) =====
        $mk = function (array $a) use ($s1) { return KasTransaction::withoutGlobalScopes()->create($a + ['school_id' => $s1->id, 'created_by' => User::where('email', 'operator@sekolah.demo')->first()->id]); };
        // 1. Pendapatan tahap 1 cair (bank +60jt)
        $t1 = $mk(['tahun' => 2026, 'type' => 'pendapatan', 'no_bku' => 1, 'tanggal' => '2026-03-04', 'bos_type_id' => $tReg->id, 'stage_id' => $stages[2026][1]->id, 'metode_bayar' => 'transfer', 'keterangan' => 'Pencairan BOS Reguler Tahap 1 tahun 2026', 'total' => 60000000]);
        $t1->details()->create(['rekening_id' => $rHibah1->id, 'uraian' => 'Hibah BOS Reguler Pusat', 'nilai' => 45000000]);
        $t1->details()->create(['rekening_id' => $rHibah2->id, 'uraian' => 'Hibah BOSP Daerah', 'nilai' => 15000000]);
        // 2. Ambil simpanan 5 jt
        $mk(['tahun' => 2026, 'type' => 'ambil_simpanan', 'no_bku' => 2, 'tanggal' => '2026-03-06', 'metode_bayar' => 'tunai', 'keterangan' => 'Penarikan tunai untuk belanja langsung', 'total' => 5000000]);
        // 3. Belanja ATK tunai Rp 3.325.000 dengan PPN Rp 325.000
        $t3 = $mk(['tahun' => 2026, 'type' => 'belanja', 'no_bku' => 3, 'no_bukti' => 'BKT-001/KAS/III/2026', 'tanggal' => '2026-03-07', 'bos_type_id' => $tReg->id, 'stage_id' => $stages[2026][1]->id, 'metode_bayar' => 'tunai', 'keterangan' => 'Pengadaan ATK triwulan I (Toko Dakko Indah)', 'total' => 3325000]);
        $t3->details()->create(['rekening_id' => $rek['5.1.02.04.01.0001']->id, 'uraian' => 'Pengadaan ATK triwulan I', 'nilai' => 3325000]);
        $t3->taxes()->create(['rekening_pajak_id' => $pPpn->id, 'kode_billing' => '875123456789012', 'nilai' => 325000, 'status' => 'belum']);
        // 4. Belanja honor Q1 (transfer)
        $t4 = $mk(['tahun' => 2026, 'type' => 'belanja', 'no_bku' => 4, 'no_bukti' => 'BKT-002/KAS/III/2026', 'tanggal' => '2026-03-28', 'bos_type_id' => $tReg->id, 'stage_id' => $stages[2026][1]->id, 'metode_bayar' => 'transfer', 'keterangan' => 'Honor penjaga & cleaning service Januari–Maret 2026', 'total' => 7500000]);
        $t4->details()->create(['rekening_id' => $rek['5.1.01.02.01.0001']->id, 'uraian' => 'Honor 3 bulan pertama', 'nilai' => 7500000]);

        // ===== UTILITY DEMO =====
        Penandatangan::withoutGlobalScopes()->insert([
            ['school_id' => $s1->id, 'jabatan' => 'Kepala Sekolah', 'nama' => 'Drs. H. Andi Kurniawan, M.Pd.', 'nip' => '196801011990031004', 'pangkat' => 'Pembina, IV/a', 'created_at' => now(), 'updated_at' => now()],
            ['school_id' => $s1->id, 'jabatan' => 'Bendahara', 'nama' => 'Nurhaedah, S.Pd.', 'nip' => '198505152010012008', 'pangkat' => 'Penata Muda Tk.I, III/b', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Announcement::create(['judul' => 'Selamat datang di SiapBOSP', 'isi' => 'Sistem akuntansi Dana BOSP multi sekolah sudah aktif. Cek menu Referensi untuk kode rekening terbaru, dan susun RKAS sebelum mencatat belanja.', 'created_by' => User::where('email', 'dinas@siapbosp.id')->first()->id]);

        $this->command->info('✅ Seeder selesai. Login: operator@sekolah.demo / kepsek@sekolah.demo / dinas@siapbosp.id (password).');
    }
}
