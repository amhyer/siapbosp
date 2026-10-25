<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\RkasHeader;
use App\Support\Csv;
use App\Support\Terbilang;
use Dompdf\Dompdf;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Ekspor RKAS profesional: PDF landscape (Dompdf) & Excel multi-sheet — untuk Sekolah & Dinas. */
class RkasExportService
{
    private const RP = '#,##0';

    /** Biner PDF dokumen RKAS (A4 landscape). */
    public static function pdf($h, $sekolah, $penandatangan): string
    {
        $penerimaan = Allocation::with('bosType')->where('school_id', $sekolah->id)
            ->where('tahun', $h->tahun)->whereNull('stage_id')->orderBy('id')->get();
        $html = view('rkas.pdf', compact('h', 'sekolah', 'penandatangan', 'penerimaan'))->render();
        $pdf = new Dompdf(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false]);
        $pdf->setPaper('A4', 'landscape');
        $pdf->loadHtml($html);
        $pdf->render();

        return $pdf->output();
    }

    public static function namaBerkas($h, $sekolah, string $ext): string
    {
        return 'RKAS_TA-'.$h->tahun.'_rev'.$h->revisi_ke.'_'.
               Str::slug($sekolah->nama).'.'.$ext;
    }

    /** Path berkas .xlsx sementara (sheet RKAS + Ringkasan Komponen). */
    public static function xlsx($h, $sekolah): string
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('SiapBOSP')->setTitle("RKAS TA {$h->tahun} - {$sekolah->nama}");

        // Kelompokkan per komponen
        $grup = $h->details->groupBy(fn ($d) => $d->rekening->komponen->nama ?? '(tanpa komponen)')->sortKeys();

        // ===== Sheet 1: Rincian RKAS =====
        $s = $book->getSheet(0)->setTitle('RKAS');
        $s->mergeCells('A1:I1')->mergeCells('A2:I2');
        $s->setCellValue('A1', 'RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS) — DANA BOSP');
        $s->setCellValue('A2', Csv::sel("TA {$h->tahun} · Revisi ke-{$h->revisi_ke} · No: ".($h->no_rkas ?? '-').
            ' · Status: '.(RkasHeader::LABEL_STATUS[$h->status] ?? $h->status)));
        $s->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $s->getStyle('A2')->getFont()->setItalic(true)->setSize(9);
        $s->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $ident = [['Nama Sekolah', $sekolah->nama], ['NPSN', (string) $sekolah->npsn],
            ['Kecamatan', $sekolah->kecamatan ?? '-'], ['Kota/Kab', $sekolah->kab_kota]];
        $r = 4;
        foreach ($ident as [$k, $v]) {
            $s->setCellValue("A{$r}", $k)->setCellValue("B{$r}", Csv::sel($v));
            $s->getStyle("A{$r}")->getFont()->setBold(true);
            $r++;
        }
        $r++;
        $hdr = $r;
        $s->fromArray(['No', 'Kode Rekening', 'Rekening', 'Uraian', 'Satuan', 'Banyak', 'Harga Satuan (Rp)', 'Jumlah (Rp)', 'Sumber Dana'], null, "A{$r}");
        $s->getStyle("A{$r}:I{$r}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $s->freezePane('A'.($r + 1));

        $no = 1;
        $total = 0;
        foreach ($grup as $komponen => $details) {
            $r++;
            $s->mergeCells("A{$r}:I{$r}");
            $s->setCellValue("A{$r}", Csv::sel(strtoupper($komponen)));
            $s->getStyle("A{$r}:I{$r}")->getFont()->setBold(true);
            $s->getStyle("A{$r}:I{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DBEAFE');
            $sub = 0;
            foreach ($details->sortBy(fn ($d) => $d->rekening->kode ?? '') as $d) {
                $r++;
                $s->fromArray(Csv::baris([$no++, $d->rekening->kode ?? '-', $d->rekening->nama ?? '-', $d->uraian,
                    $d->satuan, $d->banyak, (int) $d->harga_satuan, (int) $d->jumlah, $d->sumber_dana]), null, "A{$r}");
                $s->getStyle("G{$r}:H{$r}")->getNumberFormat()->setFormatCode(self::RP);
                $sub += (int) $d->jumlah;
            }
            $r++;
            $s->setCellValue("G{$r}", Csv::sel("Subtotal {$komponen}"));
            $s->setCellValue("H{$r}", $sub);
            $s->getStyle("A{$r}:I{$r}")->getFont()->setItalic(true)->setBold(true);
            $s->getStyle("H{$r}")->getNumberFormat()->setFormatCode(self::RP);
            $total += $sub;
        }
        $r++;
        $s->setCellValue("G{$r}", 'TOTAL RKAS');
        $s->setCellValue("H{$r}", $total);
        $s->getStyle("A{$r}:I{$r}")->getFont()->setBold(true)->setSize(11);
        $s->getStyle("A{$r}:I{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FDE68A');
        $s->getStyle("H{$r}")->getNumberFormat()->setFormatCode(self::RP);
        $r++;
        $s->mergeCells("A{$r}:I{$r}");
        $s->setCellValue("A{$r}", 'Terbilang: '.ucfirst(Terbilang::of($total)));
        $s->getStyle("A{$r}")->getFont()->setItalic(true)->setSize(9);
        $s->getStyle("A{$hdr}:I{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR);
        foreach (['A' => 5, 'B' => 14, 'C' => 26, 'D' => 40, 'E' => 10, 'F' => 9, 'G' => 16, 'H' => 16, 'I' => 14] as $c => $w) {
            $s->getColumnDimension($c)->setWidth($w);
        }

        // ===== Sheet 2: Ringkasan per Komponen =====
        $s2 = $book->createSheet()->setTitle('Ringkasan Komponen');
        $s2->mergeCells('A1:C1');
        $s2->setCellValue('A1', Csv::sel("RINGKASAN RKAS PER KOMPONEN — {$sekolah->nama} TA {$h->tahun}"));
        $s2->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $s2->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $s2->fromArray(['Komponen', 'Jumlah (Rp)', 'Porsi'], null, 'A3');
        $s2->getStyle('A3:C3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $r2 = 3;
        foreach ($grup as $komponen => $details) {
            $j = (int) $details->sum('jumlah');
            $r2++;
            $s2->fromArray(Csv::baris([$komponen, $j, $total > 0 ? $j / $total : 0]), null, "A{$r2}");
            $s2->getStyle("B{$r2}")->getNumberFormat()->setFormatCode(self::RP);
            $s2->getStyle("C{$r2}")->getNumberFormat()->setFormatCode('0.0%');
        }
        $r2++;
        $s2->fromArray(['TOTAL', $total, 1], null, "A{$r2}");
        $s2->getStyle("A{$r2}:C{$r2}")->getFont()->setBold(true);
        $s2->getStyle("B{$r2}")->getNumberFormat()->setFormatCode(self::RP);
        $s2->getStyle("C{$r2}")->getNumberFormat()->setFormatCode('0.0%');
        $s2->getStyle("A3:C{$r2}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        foreach (['A' => 42, 'B' => 18, 'C' => 10] as $c => $w) {
            $s2->getColumnDimension($c)->setWidth($w);
        }

        $path = sys_get_temp_dir().'/rkas_'.$h->id.'_'.uniqid().'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }
}
