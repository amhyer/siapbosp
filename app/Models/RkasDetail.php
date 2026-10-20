<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RkasDetail extends Model
{
    protected $guarded = [];

    public function header()
    {
        return $this->belongsTo(RkasHeader::class, 'rkas_header_id');
    }

    public function rekening()
    {
        return $this->belongsTo(Rekening::class);
    }
}
