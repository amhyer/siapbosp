<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rekening extends Model
{
    protected $guarded = [];

    public function komponen()
    {
        return $this->belongsTo(Komponen::class);
    }

    public function scopeBelanja($q)
    {
        return $q->where('tipe', 'belanja');
    }

    public function scopePendapatan($q)
    {
        return $q->where('tipe', 'pendapatan');
    }
}
