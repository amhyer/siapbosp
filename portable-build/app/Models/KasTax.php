<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KasTax extends Model
{
    protected $guarded = [];

    public function rekeningPajak()
    {
        return $this->belongsTo(RekeningPajak::class);
    }

    public function transaksi()
    {
        return $this->belongsTo(KasTransaction::class, 'kas_transaction_id');
    }
}
