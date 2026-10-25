<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\KasTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LampiranController extends Controller
{
    public function store(Request $r, KasTransaction $trx)
    {
        $data = $r->validate([
            'lampiran' => 'required|array|max:10',
            'lampiran.*' => 'file|mimes:pdf,jpg,jpeg,png|max:4096',
        ]);
        $sid = auth()->user()->school_id;
        $jml = 0;
        foreach ($data['lampiran'] as $file) {
            $path = $file->store('lampiran/'.$sid);
            $trx->attachments()->create(['path' => $path, 'nama' => $file->getClientOriginalName(), 'ukuran' => $file->getSize()]);
            $jml++;
        }

        return back()->with('ok', "$jml lampiran diunggah.");
    }

    public function download(Attachment $attachment)
    {
        $u = auth()->user();
        abort_unless($u->isDinas() || $attachment->transaksi?->school_id === $u->school_id, 403);

        return Storage::download($attachment->path, $attachment->nama);
    }

    public function destroy(Attachment $attachment)
    {
        $u = auth()->user();
        abort_unless($attachment->transaksi?->school_id === $u->school_id, 403);
        Storage::delete($attachment->path);
        $attachment->delete();

        return back()->with('ok', 'Lampiran dihapus.');
    }
}
