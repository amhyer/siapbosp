<?php

namespace App\Services;

use App\Support\Terbilang;
use Dompdf\Dompdf;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Membangun berkas Excel LPJ multi-sheet untuk satu tahap (dipakai Sekolah & Dinas). */
class LpjExportService
{
    private const RP = '#,##0';

    /**
     * @return string path berkas .xlsx sementara (hapus setelah dikirim).
     */
    public static function xlsx(array $d, $sekolah, $tmpDir = null): string
    {
        [$stages, $stage, $items, $bkuRows, $saldoAwal, $totalMasuk, $totalKeluar,
            $totalBelanja, $rekapRekening, $rekapPajak, $tahun] = $d;
        $cakupan = $d[12] ?? null;
        $namaTahap = $cakupan ?? $stage->nama;
        $kum = (bool) $cakupan;

        $book = new Spreadsheet;
        $book->getProperties()->setCreator('SiapBOSP')->setTitle("LPJ {$stage->nama} {$tahun} - {$sekolah->nama}");

        self::sheetRingkasan($book->getSheet(0)->setTitle('Ringkasan'), $sekolah, $stage, $tahun,
            $saldoAwal, $totalMasuk, $totalBelanja, $rekapRekening, $rekapPajak, $kum, $namaTahap);
        self::sheetBku($book->createSheet()->setTitle('BKU'), $stage, $tahun, $bkuRows, $saldoAwal, $kum, $namaTahap);
        self::sheetK7($book->createSheet()->setTitle('K7 Realisasi'), $stage, $tahun, $rekapRekening, $namaTahap);
        self::sheetPajak($book->createSheet()->setTitle('Pajak'), $stage, $tahun, $rekapPajak, $namaTahap);

        $path = ($tmpDir ?? sys_get_temp_dir()).'/lpj_'.$sekolah->id.'_'.$stage->id.'_'.uniqid().'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    /** Render LPJ menjadi biner PDF (Dompdf). */
    public static function pdf(array $d, $sekolah, $penandatangan): string
    {
        [$stages, $stage, $items, $bkuRows, $saldoAwal, $totalMasuk, $totalKeluar,
            $totalBelanja, $rekapRekening, $rekapPajak, $tahun] = $d;
        $cakupan = $d[12] ?? null;
        $html = view('laporan.lpj-pdf', compact('stage', 'items', 'bkuRows', 'saldoAwal',
            'totalMasuk', 'totalKeluar', 'totalBelanja', 'rekapRekening', 'rekapPajak',
            'tahun', 'sekolah', 'penandatangan', 'cakupan'))->render();
        $pdf = new Dompdf(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false]);
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHtml($html);
        $pdf->render();

        return $pdf->output();
    }

    /** Nama berkas unduhan yang rapi: LPJ_Tahap-1_2026_sd-negeri-012345.pdf */
    public static function namaBerkas(array $d, $sekolah, string $ext): string
    {
        return 'LPJ_'.str_replace(' ', '-', $d[1]->nama).'_'.$d[10].'_'.
               Str::slug($sekolah->nama).'.'.$ext;
    }

    // ---------------------------------------------------------------
    private static function titleBlock($sheet, string $judul, string $lastCol): void
    {
        $sheet->mergeCells("A1:{$lastCol}1")->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A1', $judul);
        $sheet->setCellValue('A2', 'Sumber: SiapBOSP · Dinas Pendidikan Kota Makassar');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9);
        $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private static function headerRow($sheet, int $row, array $cols): void
    {
        $sheet->fromArray($cols, null, "A{$row}");
        $last = chr(ord('A') + count($cols) - 1);
        $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
    }

    private static function box($sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private static function widths($sheet, array $w): void
    {
        foreach ($w as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
    }

    // ---------------------------------------------------------------
    private static function sheetRingkasan($s, $sekolah, $stage, int $tahun,
        int $saldoAwal, int $totalMasuk, int $totalBelanja, $rekapRekening, $rekapPajak,
        bool $kumulatif = false, ?string $namaTahap = null): void
    {
        $namaTahap ??= $stage->nama;
        self::titleBlock($s, 'LAPORAN PERTANGGUNGJAWABAN (LPJ) DANA BOS — RINGKASAN', 'E');
        $ident = [
            ['Nama Sekolah', $sekolah->nama],
            ['NPSN', (string) $sekolah->npsn],
            ['Jenjang', $sekolah->jenjang],
            ['Alamat', trim(($sekolah->alamat ?? '').' '.($sekolah->desa ?? ''))],
            ['Kecamatan', $sekolah->kecamatan ?? '-'],
            ['Kota/Kab', $sekolah->kab_kota],
            ['Cakupan / Tahun', "{$namaTahap} / {$tahun}"],
        ];
        $r = 4;
        foreach ($ident as [$k, $v]) {
            $s->setCellValue("A{$r}", $k)->setCellValue("B{$r}", $v);
            $s->getStyle("A{$r}")->getFont()->setBold(true);
            $r++;
        }
        $r++;
        $s->setCellValue("A{$r}", $kumulatif ? 'IKHTISAR KAS KUMULATIF' : 'IKHTISAR KAS TAHAP INI');
        $s->getStyle("A{$r}")->getFont()->setBold(true);
        $r++;
        $hdr = $r;
        self::headerRow($s, $hdr, ['Keterangan', 'Jumlah (Rp)']);
        $saldoAkhir = $saldoAwal + $totalMasuk - $totalBelanja;
        $rows = [
            [$kumulatif ? 'Saldo awal tahun (SILPA)' : 'Saldo awal tahap (SILPA/sisa)', $saldoAwal],
            [$kumulatif ? 'Penerimaan kumulatif (BOS + lainnya)' : 'Penerimaan tahap ini (BOS + lainnya)', $totalMasuk],
            [$kumulatif ? 'Belanja kumulatif' : 'Belanja tahap ini', $totalBelanja],
            [$kumulatif ? 'Saldo akhir (kumulatif)' : 'Saldo akhir tahap', $saldoAkhir],
            ['Terbilang saldo akhir', Terbilang::of($saldoAkhir)],
        ];
        foreach ($rows as $row) {
            $r++;
            $s->fromArray($row, null, "A{$r}");
            $s->getStyle("B{$r}")->getNumberFormat()->setFormatCode(self::RP);
        }
        self::box($s, "A{$hdr}:B{$r}");

        $r += 2;
        $s->setCellValue("A{$r}", 'RINGKASAN PAJAK');
        $s->getStyle("A{$r}")->getFont()->setBold(true);
        $r++;
        $hdr2 = $r;
        self::headerRow($s, $hdr2, ['Jenis Pajak', 'Dipungut (Rp)', 'Disetor (Rp)', 'Belum Disetor (Rp)']);
        foreach ($rekapPajak as $p) {
            $r++;
            $s->fromArray([$p['nama'], $p['dipungut'], $p['disetor'], $p['belum']], null, "A{$r}");
            $s->getStyle("B{$r}:D{$r}")->getNumberFormat()->setFormatCode(self::RP);
        }
        if ($rekapPajak) {
            $r++;
            $s->fromArray(['TOTAL', array_sum(array_column($rekapPajak, 'dipungut')),
                array_sum(array_column($rekapPajak, 'disetor')), array_sum(array_column($rekapPajak, 'belum'))], null, "A{$r}");
            $s->getStyle("A{$r}:D{$r}")->getFont()->setBold(true);
            $s->getStyle("B{$r}:D{$r}")->getNumberFormat()->setFormatCode(self::RP);
        } else {
            $r++;
            $s->setCellValue("A{$r}", '(tidak ada pajak pada tahap ini)');
        }
        self::box($s, "A{$hdr2}:D{$r}");
        self::widths($s, ['A' => 34, 'B' => 22, 'C' => 18, 'D' => 18, 'E' => 14]);
    }

    private static function sheetBku($s, $stage, int $tahun, array $bkuRows, int $saldoAwal, bool $kumulatif = false, ?string $namaTahap = null): void
    {
        $namaTahap ??= $stage->nama;
        self::titleBlock($s, 'BUKU KAS UMUM (BKU) — '.strtoupper($namaTahap)." TAHUN {$tahun}", 'H');
        self::headerRow($s, 4, ['No', 'Tanggal', 'No BKU', 'No Bukti', 'Uraian', 'Penerimaan (Rp)', 'Pengeluaran (Rp)', 'Saldo (Rp)']);
        $s->freezePane('A5');
        $r = 4;
        $s->setCellValue('E'.++$r, $kumulatif ? 'SALDO AWAL TAHUN (SILPA)' : 'SALDO AWAL TAHAP');
        $s->setCellValue("H{$r}", $saldoAwal);
        $s->getStyle("A{$r}:H{$r}")->getFont()->setBold(true);
        $no = 1;
        $totM = 0;
        $totK = 0;
        foreach ($bkuRows as $row) {
            $r++;
            $trx = $row['trx'];
            $uraian = $trx->labelType().' — '.$trx->keterangan;
            $s->fromArray([$no++, $trx->tanggal->format('d/m/Y'), $trx->no_bku, $trx->no_bukti ?? '-',
                $uraian, $row['masuk'] ?: null, $row['keluar'] ?: null, $row['saldo']], null, "A{$r}");
            $totM += $row['masuk'];
            $totK += $row['keluar'];
        }
        $r++;
        $s->setCellValue("E{$r}", $kumulatif ? 'TOTAL (KUMULATIF)' : 'TOTAL TAHAP INI');
        $s->setCellValue("F{$r}", $totM);
        $s->setCellValue("G{$r}", $totK);
        $s->getStyle("A{$r}:H{$r}")->getFont()->setBold(true);
        $s->getStyle("F5:H{$r}")->getNumberFormat()->setFormatCode(self::RP);
        self::box($s, "A4:H{$r}");
        self::widths($s, ['A' => 5, 'B' => 11, 'C' => 8, 'D' => 15, 'E' => 52, 'F' => 16, 'G' => 16, 'H' => 16]);
    }

    private static function sheetK7($s, $stage, int $tahun, $rekapRekening, ?string $namaTahap = null): void
    {
        $namaTahap ??= $stage->nama;
        self::titleBlock($s, "KERTAS KERJA K7 — REALISASI BELANJA PER REKENING — {$namaTahap} {$tahun}", 'E');
        self::headerRow($s, 4, ['No', 'Kode Rekening', 'Uraian', 'Komponen', "Realisasi {$namaTahap} (Rp)"]);
        $s->freezePane('A5');
        $r = 4;
        $no = 1;
        $total = 0;
        foreach ($rekapRekening as $rek) {
            $r++;
            $s->fromArray([$no++, $rek['rekening']->kode ?? '-', $rek['rekening']->nama ?? '-',
                $rek['rekening']->komponen->nama ?? '-', $rek['jumlah']], null, "A{$r}");
            $total += $rek['jumlah'];
        }
        $r++;
        $s->setCellValue("D{$r}", 'TOTAL');
        $s->setCellValue("E{$r}", $total);
        $s->getStyle("A{$r}:E{$r}")->getFont()->setBold(true);
        $s->getStyle("E5:E{$r}")->getNumberFormat()->setFormatCode(self::RP);
        self::box($s, "A4:E{$r}");
        self::widths($s, ['A' => 5, 'B' => 16, 'C' => 46, 'D' => 24, 'E' => 20]);
    }

    private static function sheetPajak($s, $stage, int $tahun, $rekapPajak, ?string $namaTahap = null): void
    {
        $namaTahap ??= $stage->nama;
        self::titleBlock($s, "REGISTER PAJAK — {$namaTahap} TAHUN {$tahun}", 'D');
        self::headerRow($s, 4, ['Jenis Pajak', 'Dipungut (Rp)', 'Disetor ke Negara (Rp)', 'Belum Disetor (Rp)']);
        $r = 4;
        foreach ($rekapPajak as $p) {
            $r++;
            $s->fromArray([$p['nama'], $p['dipungut'], $p['disetor'], $p['belum']], null, "A{$r}");
            $s->getStyle("B{$r}:D{$r}")->getNumberFormat()->setFormatCode(self::RP);
        }
        if (! $rekapPajak) {
            $r++;
            $s->setCellValue("A{$r}", '(tidak ada pajak pada tahap ini)');
        }
        self::box($s, "A4:D{$r}");
        self::widths($s, ['A' => 40, 'B' => 18, 'C' => 22, 'D' => 18]);
    }
}
