<?php

namespace App\Http\Controllers;

use App\Services\TarikArkasService;
use Illuminate\Http\Request;

/**
 * Diagnosa jalur "Tarik Data ARKAS" lewat browser (khusus localhost).
 *
 * Menjawab satu pertanyaan: apakah aplikasi di laptop ini benar-benar bisa
 * menarik data dari ARKAS? Yang ditampilkan: agen terbundel atau tidak,
 * database ARKAS ditemukan atau tidak, dan — dengan `?uji=1` — hasil tarik
 * nyata (sheet + jumlah baris) tanpa mengimpor apa pun ke database.
 */
class DiagnosaArkasController extends Controller
{
    public function index(Request $r)
    {
        $this->hanyaLokal($r);

        $tahun = (int) $r->query('tahun', now()->year);
        $uji = $r->boolean('uji');
        $status = TarikArkasService::statusLengkap($tahun, $uji);

        $baris = [];
        $baris['Aplikasi portabel'] = $status['portable']
            ? 'ya (php/php.exe ada)'
            : 'TIDAK — tombol Tarik Data ARKAS hanya muncul di aplikasi portabel';
        $baris['agen/TarikArkas/node.exe'] = $status['agen']['node'] === null
            ? 'TIDAK ADA'
            : number_format($status['agen']['node'] / 1024, 0, ',', '.').' KB ('.$status['agen']['node_mtime'].')';
        $baris['agen/TarikArkas/tarik.cjs'] = $status['agen']['tarik'] === null
            ? 'TIDAK ADA'
            : number_format($status['agen']['tarik'] / 1024, 0, ',', '.').' KB ('.$status['agen']['tarik_mtime'].')';
        $baris['Tombol tarik tersedia'] = $status['tersedia'] ? 'ya' : 'tidak';
        $baris['Database ARKAS'] = ($status['db_ada'] ? 'ditemukan — ' : 'TIDAK ditemukan di ') . $status['db_path'];

        if (is_array($status['info'])) {
            $info = $status['info'];
            $versi = $info['versi'] ?? [];
            $baris['Versi agen'] = ($info['agen'] ?? 'tidak dilaporkan').(
                empty($info['agen']) ? ' (kemungkinan agen lama)' : ''
            );
            $baris['Versi ARKAS'] = (string) ($versi['version_app'] ?? '?').' · tahun aktif '.($versi['tahun_aktif'] ?? '?');
            $baris['NPSN menurut ARKAS'] = (string) ($info['npsnFile'] ?? '?');
            $baris['Master terbaca'] = 'ref_kode '.($versi['ref_kode'] ?? 0)
                .' · ref_rekening '.($versi['ref_rekening'] ?? 0)
                .' · PTK '.($versi['ptk'] ?? 0);
        }

        $ujiHtml = '';
        if ($uji) {
            if (! $status['tersedia']) {
                $ujiHtml = '<p class="gagal">Uji tarik tidak bisa dijalankan: agen belum tersedia (lihat tabel di atas).</p>';
            } elseif (! $status['db_ada']) {
                $ujiHtml = '<p class="gagal">Uji tarik tidak bisa dijalankan: database ARKAS tidak ditemukan.</p>';
            } elseif (($status['uji']['ok'] ?? false) === false) {
                $ujiHtml = '<p class="gagal">Uji tarik GAGAL: '.e((string) ($status['uji']['pesan'] ?? 'tanpa keterangan')).'</p>';
            } else {
                $u = $status['uji'];
                $ujiHtml = '<p class="ok">Uji tarik BERHASIL — NPSN '.e((string) ($u['npsn'] ?? '?'))
                    .', berkas '.number_format((int) $u['berkas_kb'], 0, ',', '.').' KB, sheet:</p><ul>';
                foreach (($u['sheets'] ?? []) as $nama => $jumlah) {
                    $ujiHtml .= '<li>'.e((string) $nama).' — '.number_format((int) $jumlah, 0, ',', '.').' baris</li>';
                }
                $ujiHtml .= '</ul>';
                $ujiHtml .= isset($u['sheets']['RAPBS_Periode'])
                    ? '<p class="ok">Sheet RAPBS_Periode ada → kode kegiatan transaksi &amp; volume per tahap akan terisi.</p>'
                    : '<p class="gagal">Sheet RAPBS_Periode TIDAK ada → agen di laptop ini versi lama; '
                        .'transaksi akan masuk tanpa kode kegiatan dan RKAS tanpa volume per tahap. '
                        .'Perbarui paket aplikasi (agen 1.0.1 atau lebih baru).</p>';
            }
        }

        $html = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Diagnosa Tarik ARKAS — SiapBOSP</title><style>'
            .'body{font-family:ui-sans-serif,system-ui,sans-serif;margin:0;padding:24px;background:#f1f5f9;color:#1e293b}'
            .'h1{font-size:18px;margin:0 0 4px}p{font-size:13px;margin:0 0 12px;color:#64748b}'
            .'p.ok{color:#047857}p.gagal{color:#be123c}'
            .'table{border-collapse:collapse;width:100%;max-width:980px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden}'
            .'th,td{text-align:left;padding:8px 12px;font-size:13px;border-bottom:1px solid #f1f5f9;vertical-align:top}'
            .'th{width:32%;color:#475569;background:#f8fafc;font-weight:600}'
            .'td{font-family:ui-monospace,monospace;word-break:break-all}'
            .'ul{font-size:13px;max-width:980px;columns:2}'
            .'code{background:#f1f5f9;padding:1px 5px;border-radius:4px}'
            .'</style></head><body><h1>Diagnosa Tarik Data ARKAS</h1>'
            .'<p>Halaman ini hanya bisa dibuka dari komputer ini (localhost).</p><table>';

        foreach ($baris as $kunci => $nilai) {
            $html .= '<tr><th>'.e($kunci).'</th><td>'.e((string) $nilai).'</td></tr>';
        }

        $html .= '</table>'.$ujiHtml;

        $html .= '<p style="margin-top:16px">Uji tarik nyata (membaca ARKAS, <strong>tanpa</strong> mengubah database SiapBOSP): '
            .'<a href="?uji=1&amp;tahun='.$tahun.'">jalankan untuk tahun '.$tahun.'</a>.</p>';

        $html .= '<p>Bila agen TIDAK ADA: pakai paket aplikasi terbaru yang membawa folder '
            .'<code>agen/TarikArkas</code> (node.exe + tarik.cjs). Paket tanpa agen tidak bisa menarik data ARKAS.</p>'
            .'</body></html>';

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }

    /** Diagnosa hanya untuk komputer tempat aplikasi berjalan. */
    private function hanyaLokal(Request $r): void
    {
        abort_unless(
            in_array($r->ip(), ['127.0.0.1', '::1'], true)
            && in_array($r->getHost(), ['localhost', '127.0.0.1', '::1', '[::1]'], true),
            404
        );
    }
}
