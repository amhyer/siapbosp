<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Master satuan belanja (diimpor dari ARKAS `ref_satuan`). */
class Satuan extends Model
{
    protected $guarded = [];

    public function uraian()
    {
        return $this->hasMany(Uraian::class);
    }
}
