<?php

namespace App\Services;

use App\Models\LpjSubmission;
use App\Support\Csv;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Ekspor konsolidasi wilayah untuk Dinas: rekap per sekolah + matriks belanja per komponen. */
class KonsolidasiExportService
{
    private const RP = '#,##0';

    /**
     * @param  Collection  $schools  koleksi School yang sudah diperkaya RingkasanService
     * @return string path berkas .xlsx sementara
     */
    public static function xlsx($schools, int $tahun): string
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('SiapBOSP')->setTitle("Konsolidasi Wilayah {$tahun}");

        self::sheetRekap($book->getSheet(0)->setTitle('Rekap Sekolah'), $schools, $tahun);
        self::sheetMatriks($book->createSheet()->setTitle('Belanja per Komponen'), $schools, $tahun);

        $path = sys_get_temp_dir().'/konsolidasi_'.$tahun.'_'.uniqid().'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    private static function kop($sheet, string $judul, string $lastCol): void
    {
        $sheet->mergeCells("A1:{$lastCol}1")->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A1', Csv::sel($judul));
        $sheet->setCellValue('A2', 'Sumber: SiapBOSP · Dinas Pendidikan Kota Makassar');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9);
        $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private static function header($sheet, int $row, array $cols): void
    {
        $sheet->fromArray(Csv::baris($cols), null, "A{$row}");
        $last = chr(ord('A') + count($cols) - 1);
        $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
    }

    private static function sheetRekap($s, $schools, int $tahun): void
    {
        self::kop($s, "KONSOLIDASI PENYERAPAN DANA BOS SEMUA SEKOLAH — TAHUN {$tahun}", 'K');
        self::header($s, 4, ['Sekolah', 'NPSN', 'Jenjang', 'Kecamatan', 'Pagu (Rp)', 'Diterima (Rp)',
            'Belanja (Rp)', 'Serapan', 'Pajak Belum Setor (Rp)', 'Status LPJ Terakhir', 'Aktivitas Terakhir']);
        $s->freezePane('A5');

        // Status LPJ terakhir tiap sekolah (tahun berjalan)
        $lpjTerakhir = LpjSubmission::with('stage')->where('tahun', $tahun)
            ->orderByDesc('created_at')->get()->groupBy('school_id')->map(fn ($g) => $g->first());

        $r = 4;
        $tot = ['pagu' => 0, 'pendapatan' => 0, 'belanja' => 0, 'pajak' => 0];
        foreach ($schools as $sc) {
            $r++;
            $lpj = $lpjTerakhir[$sc->id] ?? null;
            $lpjTxt = $lpj ? (($lpj->stage->nama ?? '?').' · '.strtoupper($lpj->status)
                .($lpj->ditinjau_at ? ' ('.$lpj->ditinjau_at->format('d/m').')' : '')) : '(belum pernah kirim)';
            $s->fromArray(Csv::baris([$sc->nama, (string) $sc->npsn, $sc->jenjang, $sc->kecamatan ?? '-',
                (int) $sc->pagu, (int) $sc->pendapatan, (int) $sc->belanja, (float) $sc->serapan,
                (int) $sc->pajakBelum, $lpjTxt,
                $sc->last ? Carbon::parse($sc->last)->format('d/m/Y H:i') : '—']), null, "A{$r}");
            $s->getStyle("E{$r}:G{$r}")->getNumberFormat()->setFormatCode(self::RP);
            $s->getStyle("H{$r}")->getNumberFormat()->setFormatCode('0.0"%"');
            $s->getStyle("I{$r}")->getNumberFormat()->setFormatCode(self::RP);
            // warna serapan
            $color = $sc->serapan >= 80 ? '16A34A' : ($sc->serapan >= 40 ? 'D97706' : 'DC2626');
            $s->getStyle("H{$r}")->getFont()->setBold(true)->getColor()->setRGB($color);
            $tot['pagu'] += (int) $sc->pagu;
            $tot['pendapatan'] += (int) $sc->pendapatan;
            $tot['belanja'] += (int) $sc->belanja;
            $tot['pajak'] += (int) $sc->pajakBelum;
        }
        $r++;
        $serapanRata = $tot['pagu'] > 0 ? round($tot['belanja'] / $tot['pagu'] * 100, 1) : 0;
        $s->fromArray(['TOTAL WILAYAH ('.$schools->count().' sekolah)', '', '', '',
            $tot['pagu'], $tot['pendapatan'], $tot['belanja'], $serapanRata, $tot['pajak'], '', ''], null, "A{$r}");
        $s->getStyle("A{$r}:K{$r}")->getFont()->setBold(true);
        $s->getStyle("A{$r}:K{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DBEAFE');
        $s->getStyle("E{$r}:G{$r}")->getNumberFormat()->setFormatCode(self::RP);
        $s->getStyle("H{$r}")->getNumberFormat()->setFormatCode('0.0"%"');
        $s->getStyle("I{$r}")->getNumberFormat()->setFormatCode(self::RP);
        $s->getStyle("A4:K{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        foreach (['A' => 32, 'B' => 12, 'C' => 8, 'D' => 14, 'E' => 16, 'F' => 16, 'G' => 16, 'H' => 10, 'I' => 18, 'J' => 24, 'K' => 16] as $c => $w) {
            $s->getColumnDimension($c)->setWidth($w);
        }
        $s->setCellValue('A'.($r + 2), 'Diekspor dari SiapBOSP pada '.now()->translatedFormat('d F Y H:i').' WITA');
        $s->getStyle('A'.($r + 2))->getFont()->setItalic(true)->setSize(8);
    }

    private static function sheetMatriks($s, $schools, int $tahun): void
    {
        // Satu query: belanja per (sekolah × komponen)
        $rows = DB::table('kas_details as d')
            ->join('kas_transactions as t', 'd.kas_transaction_id', '=', 't.id')
            ->leftJoin('rekenings as r', 'd.rekening_id', '=', 'r.id')
            ->leftJoin('komponens as k', 'r.komponen_id', '=', 'k.id')
            ->where('t.tahun', $tahun)->where('t.type', 'belanja')
            ->selectRaw("t.school_id sid, coalesce(k.nama, '(tanpa komponen)') kom, sum(d.nilai) j")
            ->groupBy('sid', 'kom')->get();

        $komponens = $rows->pluck('kom')->unique()->sort()->values();
        $grid = [];
        foreach ($rows as $row) {
            $grid[$row->kom][$row->sid] = (int) $row->j;
        }

        $lastCol = chr(ord('A') + $schools->count() + 1); // komponen + N sekolah + total
        self::kop($s, "MATRIKS BELANJA PER KOMPONEN × SEKOLAH — TAHUN {$tahun}", $lastCol);
        $head = ['Komponen'];
        foreach ($schools as $sc) {
            $head[] = Str::limit($sc->nama, 24);
        }
        $head[] = 'TOTAL (Rp)';
        self::header($s, 4, $head);
        $s->freezePane('B5');

        $r = 4;
        $colTot = array_fill(0, $schools->count() + 1, 0);
        foreach ($komponens as $kom) {
            $r++;
            $s->setCellValue("A{$r}", Csv::sel($kom));
            $baris = 0;
            foreach ($schools as $i => $sc) {
                $nilai = $grid[$kom][$sc->id] ?? 0;
                $s->setCellValue(chr(ord('B') + $i).$r, $nilai ?: null);
                $baris += $nilai;
                $colTot[$i] += $nilai;
                $colTot[count($schools)] += $nilai;
            }
            $s->setCellValue($lastCol.$r, $baris);
        }
        $r++;
        $s->setCellValue("A{$r}", 'TOTAL');
        foreach ($colTot as $i => $v) {
            $s->setCellValue(chr(ord('B') + $i).$r, $v);
        }
        $s->getStyle("A{$r}:{$lastCol}{$r}")->getFont()->setBold(true);
        $s->getStyle("A{$r}:{$lastCol}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DBEAFE');
        $s->getStyle("B5:{$lastCol}{$r}")->getNumberFormat()->setFormatCode(self::RP);
        if ($r > 5) {
            $s->getStyle("A4:{$lastCol}{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        $s->getColumnDimension('A')->setWidth(34);
        for ($i = 0; $i <= $schools->count(); $i++) {
            $s->getColumnDimension(chr(ord('B') + $i))->setWidth(18);
        }
    }
}
