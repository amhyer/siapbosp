<?php

namespace App\Http\Controllers;

use App\Models\BosType;
use App\Models\Komponen;
use App\Models\Rekening;
use App\Models\RekeningPajak;
use App\Models\Stage;

class ReferensiController extends Controller
{
    public function index()
    {
        return view('referensi.index', [
            'rekenings' => Rekening::with('komponen')->orderBy('kode')->get(),
            'pajaks' => RekeningPajak::orderBy('kode')->get(),
            'komponens' => Komponen::orderBy('kode')->get(),
            'bosTypes' => BosType::orderBy('id')->get(),
            'stages' => Stage::orderBy('tahun')->orderBy('urutan')->get(),
        ]);
    }
}
