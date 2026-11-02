<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $guarded = [];

    public function transaksi()
    {
        return $this->belongsTo(KasTransaction::class, 'kas_transaction_id');
    }

    public function ukuranLabel(): string
    {
        $kb = $this->ukuran / 1024;

        return $kb >= 1024 ? number_format($kb / 1024, 1).' MB' : number_format($kb, 0).' KB';
    }
}
