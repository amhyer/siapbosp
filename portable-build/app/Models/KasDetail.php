<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KasDetail extends Model
{
    protected $guarded = [];

    public function rekening()
    {
        return $this->belongsTo(Rekening::class);
    }

    public function transaksi()
    {
        return $this->belongsTo(KasTransaction::class, 'kas_transaction_id');
    }

    public function rkasDetail()
    {
        return $this->belongsTo(RkasDetail::class);
    }
}
