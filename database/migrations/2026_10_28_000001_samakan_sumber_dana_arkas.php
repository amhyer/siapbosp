<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Samakan nama sumber dana dengan `ref_sumber_dana` ARKAS Desktop 4.3.1.
 *
 * ARKAS menamai sumber dananya "BOS Daerah", "BOS Afirmasi", "SiLPA BOS
 * Kinerja", "Lainnya", dan memisahkan SiLPA per sumber dana. Ejaan yang
 * dipakai SiapBOSP sejak awal ("BOSP …", "Sumber Dana Lainnya") tetap
 * dipetakan ke tab yang sama (RkasHeader::TAB_SUMBER_DANA), tetapi dokumen
 * baru sebaiknya memakai nama resmi supaya judul RKAS/BKU sama dengan ARKAS.
 *
 * Idempoten: pengulangan tidak mengubah apa pun.
 */
return new class extends Migration
{
    /** Ejaan lama → ejaan resmi ARKAS 4.3.1. */
    private const GANTI_NAMA = [
        'BOSP Daerah' => 'BOS Daerah',
        'BOSP Afirmasi' => 'BOS Afirmasi',
        'SiLPA BOSP Kinerja' => 'SiLPA BOS Kinerja',
        'Sumber Dana Lainnya' => 'Lainnya',
    ];

    /** Jenis sumber dana yang belum ada di instalasi lama. */
    private const JENIS_BARU = [
        'silpa_reguler' => 'SiLPA BOS Reguler',
        'silpa_afirmasi' => 'SiLPA BOS Afirmasi',
    ];

    /** Tabel yang menyimpan nama sumber dana pada dokumen. */
    private const TABEL_DOKUMEN = ['rkas_headers', 'rkas_details', 'perencanaan_rkts'];

    public function up(): void
    {
        $kini = now();

        foreach (self::JENIS_BARU as $kode => $nama) {
            DB::table('bos_types')->updateOrInsert(
                ['kode' => $kode],
                ['nama' => $nama, 'updated_at' => $kini, 'created_at' => $kini]
            );
        }

        foreach (self::GANTI_NAMA as $lama => $baru) {
            // Jenis BOS (nama dipakai RkasHeader::bosTypeIds untuk filter tab).
            DB::table('bos_types')->where('nama', $lama)
                ->update(['nama' => $baru, 'updated_at' => $kini]);

            // Nama sumber dana pada dokumen.
            foreach (self::TABEL_DOKUMEN as $tabel) {
                if (! Schema::hasColumn($tabel, 'sumber_dana')) {
                    continue;
                }

                DB::table($tabel)->where('sumber_dana', $lama)->update(['sumber_dana' => $baru]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::GANTI_NAMA as $lama => $baru) {
            DB::table('bos_types')->where('nama', $baru)
                ->update(['nama' => $lama, 'updated_at' => now()]);

            foreach (self::TABEL_DOKUMEN as $tabel) {
                if (! Schema::hasColumn($tabel, 'sumber_dana')) {
                    continue;
                }

                DB::table($tabel)->where('sumber_dana', $baru)->update(['sumber_dana' => $lama]);
            }
        }

        DB::table('bos_types')->whereIn('kode', array_keys(self::JENIS_BARU))->delete();
    }
};
