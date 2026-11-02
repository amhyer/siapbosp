<?php

namespace App\Http\Controllers;

use App\Models\BkuTutupBulan;
use App\Models\KasTransaction;
use App\Models\LpjSubmission;
use App\Models\RkasHeader;
use App\Models\School;
use App\Models\TutupBuku;
use App\Services\HapusDataService;
use Illuminate\Http\Request;

/**
 * Reset data transaksional sekolah. Khusus non-kepsek:
 * operator (data sekolahnya) + dinas/superadmin (sekolah mana pun).
 */
class HapusDataController extends Controller
{
    public function form()
    {
        abort_if(auth()->user()->role === 'kepsek', 403);
        $sekolah = auth()->user()->school;
        $ringkas = [
            'transaksi' => KasTransaction::where('school_id', $sekolah->id)->count(),
            'rkas' => RkasHeader::where('school_id', $sekolah->id)->count(),
            'tutup_buku' => TutupBuku::where('school_id', $sekolah->id)->count()
                + BkuTutupBulan::where('school_id', $sekolah->id)->count(),
            'lpj' => LpjSubmission::where('school_id', $sekolah->id)->count(),
        ];

        return view('utility.hapus-data', compact('sekolah', 'ringkas'));
    }

    public function proses(Request $r)
    {
        $u = auth()->user();
        abort_if($u->role === 'kepsek', 403);
        $r->validate(['konfirmasi' => 'required|string']);
        if (trim($r->konfirmasi) !== $u->school->npsn) {
            return back()->with('error', 'Konfirmasi salah. Ketik NPSN sekolah tepat seperti ditampilkan.');
        }
        $hasil = HapusDataService::hapusSekolah($u->school_id, $u->id, $u->name);

        return redirect()->route('dashboard')
            ->with('ok', 'Data sekolah dihapus: '.$hasil['transaksi'].' transaksi, '.$hasil['rkas'].' RKAS.');
    }

    public function dinasProses(School $school)
    {
        $u = auth()->user();
        $hasil = HapusDataService::hapusSekolah($school->id, $u->id, $u->name.' (Dinas)');

        return back()->with('ok', "Data {$school->nama} dihapus: ".$hasil['transaksi'].' transaksi, '.$hasil['rkas'].' RKAS.');
    }
}
