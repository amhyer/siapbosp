<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penyelarasan penuh SiapBOSP terhadap Buku Panduan ARKAS 4 v4.3.0 (September 2026)
 * & Permendikdasmen Nomor 8 Tahun 2026 (Juknis BOSP 2026), mencakup:
 * - Profil satdik (status negeri/swasta, jumlah siswa, satuan biaya, komite, periode kepsek)
 * - Kertas Kerja RKAS multi-bulan (Jan..Des), jenis revisi (Murni/Pergeseran/Perubahan), checklist ARKAS
 * - Modul Pra-ARKAS hulu: PBD / Rapor Pendidikan -> RKM (4 thn) -> RKT (tahunan) + Standar Harga (SSH/SBU)
 * - Tutup BKU bulanan dengan Bunga Bank & Pajak Bunga/Admin Bank, serta detail Nota Penyedia/SIPLah
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $t) {
            $t->string('status_sekolah', 20)->default('negeri')->after('jenjang'); // negeri | swasta
            $t->integer('jumlah_siswa')->default(0)->after('alamat');
            $t->bigInteger('satuan_biaya_bosp')->default(0)->after('jumlah_siswa');
            $t->string('nama_komite')->nullable()->after('satuan_biaya_bosp');
            $t->string('email_komite')->nullable()->after('nama_komite');
            $t->unsignedTinyInteger('akhir_jabatan_bulan')->default(12)->after('email_komite');
        });

        Schema::table('rkas_headers', function (Blueprint $t) {
            $t->string('sumber_dana', 60)->default('BOS Reguler')->after('no_rkas');
            $t->string('jenis_rkas', 20)->default('murni')->after('sumber_dana'); // murni | pergeseran | perubahan
            $t->foreignId('acuan_header_id')->nullable()->after('jenis_rkas')
                ->constrained('rkas_headers')->nullOnDelete();
        });

        Schema::table('rkas_details', function (Blueprint $t) {
            $t->text('bulan_volumes')->nullable()->after('periode_volume'); // JSON {"1":10,...,"12":0}
            $t->unsignedBigInteger('standar_harga_id')->nullable()->after('bulan_volumes');
            $t->unsignedBigInteger('rkt_id')->nullable()->after('standar_harga_id');
            $t->boolean('sudah_diinput_arkas')->default(false)->after('sumber_dana');
        });

        Schema::table('bku_tutup_bulans', function (Blueprint $t) {
            $t->bigInteger('bunga_bank')->default(0)->after('ditutup_oleh');
            $t->bigInteger('pajak_bunga')->default(0)->after('bunga_bank');
            $t->boolean('belum_lapor_pajak')->default(false)->after('pajak_bunga');
        });

        Schema::table('kas_notas', function (Blueprint $t) {
            $t->boolean('usaha_perseorangan')->default(false)->after('nama_toko');
            $t->boolean('tanpa_npwp')->default(false)->after('npwp');
            $t->bigInteger('ongkir_siplah')->default(0)->after('lunas_bayar');
            $t->bigInteger('biaya_admin_siplah')->default(0)->after('ongkir_siplah');
        });

        // 1. Hulu Pra-ARKAS: PBD / Rapor Pendidikan
        Schema::create('perencanaan_pbds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_id')->constrained()->cascadeOnDelete();
            $t->integer('tahun');
            $t->string('kode_indikator', 20); // mis. A.1, A.2, A.3, D.1, D.4, D.8, E.1, E.5
            $t->string('nama_indikator');
            $t->decimal('skor_capaian', 5, 2)->nullable();
            $t->string('prioritas', 20)->default('tinggi'); // tinggi | sedang | rendah
            $t->text('akar_masalah');
            $t->text('benahi');
            $t->string('kode_kegiatan', 20)->nullable();
            $t->timestamps();
            $t->index(['school_id', 'tahun']);
        });

        // 2. RKM (Rencana Kerja Menengah 4 Tahun)
        Schema::create('perencanaan_rkms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_id')->constrained()->cascadeOnDelete();
            $t->foreignId('pbd_id')->nullable()->constrained('perencanaan_pbds')->nullOnDelete();
            $t->integer('tahun_awal');
            $t->integer('tahun_akhir');
            $t->string('sasaran');
            $t->string('indikator_kinerja')->nullable();
            $t->string('target_capaian', 100)->nullable();
            $t->timestamps();
            $t->index(['school_id', 'tahun_awal']);
        });

        // 3. RKT (Rencana Kerja Tahunan — sebelum dituangkan ke Kertas Kerja RKAS)
        Schema::create('perencanaan_rkts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_id')->constrained()->cascadeOnDelete();
            $t->foreignId('rkm_id')->nullable()->constrained('perencanaan_rkms')->nullOnDelete();
            $t->integer('tahun');
            $t->string('kode_kegiatan', 20)->nullable();
            $t->string('nama_kegiatan');
            $t->foreignId('rekening_id')->nullable()->constrained('rekenings')->nullOnDelete();
            $t->boolean('butuh_anggaran')->default(true);
            $t->string('sumber_dana', 60)->default('BOS Reguler');
            $t->integer('volume')->default(1);
            $t->string('satuan', 40)->default('paket');
            $t->bigInteger('harga_satuan')->default(0);
            $t->bigInteger('estimasi_biaya')->default(0);
            $t->unsignedTinyInteger('bulan_target')->default(1);
            $t->string('status_rkas', 20)->default('belum'); // belum | masuk_draft
            $t->timestamps();
            $t->index(['school_id', 'tahun']);
        });

        // 4. Referensi Standar Harga (SSH / SBU Daerah — penjaga batas atas harga satuan ARKAS 4)
        Schema::create('standar_hargas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete(); // null = berlaku seluruh wilayah Dinas
            $t->string('kode_rekening', 30)->nullable();
            $t->string('kode_barang', 40)->nullable();
            $t->string('nama_barang');
            $t->string('spesifikasi')->nullable();
            $t->string('satuan', 40)->default('unit');
            $t->bigInteger('batas_harga');
            $t->string('kategori', 30)->default('ssh'); // ssh | sbu | modal
            $t->timestamps();
            $t->index(['kode_rekening']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standar_hargas');
        Schema::dropIfExists('perencanaan_rkts');
        Schema::dropIfExists('perencanaan_rkms');
        Schema::dropIfExists('perencanaan_pbds');

        Schema::table('kas_notas', fn (Blueprint $t) => $t->dropColumn([
            'usaha_perseorangan', 'tanpa_npwp', 'ongkir_siplah', 'biaya_admin_siplah',
        ]));
        Schema::table('bku_tutup_bulans', fn (Blueprint $t) => $t->dropColumn([
            'bunga_bank', 'pajak_bunga', 'belum_lapor_pajak',
        ]));
        Schema::table('rkas_details', fn (Blueprint $t) => $t->dropColumn([
            'bulan_volumes', 'standar_harga_id', 'rkt_id', 'sudah_diinput_arkas',
        ]));
        Schema::table('rkas_headers', function (Blueprint $t) {
            $t->dropConstrainedForeignId('acuan_header_id');
            $t->dropColumn(['sumber_dana', 'jenis_rkas']);
        });
        Schema::table('schools', fn (Blueprint $t) => $t->dropColumn([
            'status_sekolah', 'jumlah_siswa', 'satuan_biaya_bosp',
            'nama_komite', 'email_komite', 'akhir_jabatan_bulan',
        ]));
    }
};
